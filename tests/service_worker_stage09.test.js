// Executes the repository Service Worker in a worker-like VM; no real network.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const SCRIPT = 'http://localhost/estudAi_1.1/app/public/sw.js';
const BASE = 'http://localhost/estudAi_1.1/app/';
const results = [];
const unhandled = [];
process.on('unhandledRejection', reason => unhandled.push(reason));
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

function key(value, ignoreSearch = false) {
  const url = new URL(typeof value === 'string' ? value : value.url, SCRIPT);
  if (ignoreSearch) url.search = '';
  return url.href;
}
function worker(options = {}) {
  const stores = new Map(), deleted = [], calls = [], handlers = new Map();
  const faults = { storage: false, write: false };
  const context = {
    URL, Request, Response, Promise, encodeURIComponent,
    self: {
      location: new URL(SCRIPT), registration: { scope: new URL('./', SCRIPT).href },
      addEventListener(type, fn) { handlers.set(type, fn); },
      skipWaiting: async () => {}, clients: { claim: async () => {} }
    },
    fetch: async request => {
      const url = key(request); calls.push(url);
      if (options.offline) throw new TypeError('Offline test');
      if (options.invalidResponse) return undefined;
      return new Response(options.body || 'NETWORK', { status: options.status || 200 });
    }
  };
  function cache(name) {
    if (!stores.has(name)) stores.set(name, new Map());
    const data = stores.get(name);
    return {
      async match(request, opts = {}) {
        if (faults.storage) throw new Error('Storage unavailable');
        const wanted = key(request, opts.ignoreSearch);
        for (const [url, response] of data) {
          if (key(url, opts.ignoreSearch) === wanted) return response.clone();
        }
      },
      async put(request, response) {
        if (faults.write) throw new Error('Quota exceeded');
        assert.ok(response instanceof Response);
        data.set(key(request), response.clone());
      },
      async add(request) {
        const response = await context.fetch(key(request));
        if (!response?.ok) throw new Error('Cannot precache');
        return this.put(request, response);
      }
    };
  }
  context.caches = {
    async open(name) { if (faults.storage) throw new Error('Storage unavailable'); return cache(name); },
    async keys() { if (faults.storage) throw new Error('Storage unavailable'); return [...stores.keys()]; },
    async delete(name) { deleted.push(name); return stores.delete(name); },
    async match(request, opts = {}) {
      if (faults.storage) throw new Error('Storage unavailable');
      const names = opts.cacheName ? [opts.cacheName] : [...stores.keys()];
      for (const name of names) {
        if (!stores.has(name)) continue;
        const result = await cache(name).match(request, opts);
        if (result) return result;
      }
    }
  };
  vm.createContext(context);
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../app/public/sw.js'), 'utf8'), context);
  const current = vm.runInContext('STATIC_CACHE', context);
  return {
    current, calls, deleted, faults, stores,
    async rememberWhileBodyIsConsumed() {
      const response = new Response('BODY');
      const saving = context.rememberResponse('index.html', response);
      await response.text();
      await saving;
      const stored = await cache(current).match('index.html');
      assert.equal(await stored?.text(), 'BODY');
    },
    async seed(name, url, body, status = 200) { await cache(name).put(url, new Response(body, { status })); },
    async fetch(url, mode = 'cors', method = 'GET') {
      const pending = []; let handled = false, response;
      handlers.get('fetch')({
        request: { url: key(url), mode, method },
        respondWith(promise) { handled = true; response = Promise.resolve(promise); },
        waitUntil(promise) { pending.push(Promise.resolve(promise)); }
      });
      const value = handled ? await response : undefined;
      await Promise.allSettled(pending); await flush();
      if (handled) assert.ok(value instanceof Response, 'respondWith must resolve to Response');
      return { handled, response: value };
    },
    async lifecycle(type) {
      const pending = [];
      handlers.get(type)({ waitUntil(promise) { pending.push(Promise.resolve(promise)); } });
      await Promise.all(pending); await flush();
    }
  };
}
async function test(name, fn) {
  const before = unhandled.length;
  try {
    await fn(); await flush();
    assert.equal(unhandled.length, before, 'Unhandled asynchronous cache rejection');
    results.push({ test: name, result: 'PASS' });
  } catch (error) {
    results.push({ test: name, result: 'FAIL', reason: error.message });
  }
}

(async () => {
  await test('cold_offline_navigation_without_fallback_has_valid_503', async () => {
    const w = worker({ offline: true }), r = await w.fetch('index.html', 'navigate');
    assert.equal(r.response.status, 503);
    assert.match(r.response.headers.get('Content-Type'), /text\/html/);
  });
  await test('cold_offline_static_miss_has_valid_error_response', async () => {
    const w = worker({ offline: true }), r = await w.fetch('../src/assets/icons/not-cached.svg');
    assert.equal(r.response.status, 503);
  });
  await test('query_navigation_reuses_cached_HTML', async () => {
    const w = worker({ offline: true });
    await w.seed(w.current, 'index.html', 'LOGIN');
    await w.seed(w.current, 'offline.html', 'OFFLINE');
    assert.equal(await (await w.fetch('index.html?utm_source=chatgpt.com', 'navigate')).response.text(), 'LOGIN');
  });
  await test('query_folder_navigation_reuses_cached_index', async () => {
    const w = worker({ offline: true });
    await w.seed(w.current, './', 'INDEX');
    assert.equal(await (await w.fetch('./?utm_source=chatgpt.com', 'navigate')).response.text(), 'INDEX');
  });
  await test('offline_page_used_when_target_missing', async () => {
    const w = worker({ offline: true }); await w.seed(w.current, 'offline.html', 'OFFLINE');
    assert.equal(await (await w.fetch('unvisited.html', 'navigate')).response.text(), 'OFFLINE');
  });
  await test('warm_static_remains_available_offline', async () => {
    const w = worker({ offline: true }); await w.seed(w.current, '../src/styles/app.css', 'CSS');
    assert.equal(await (await w.fetch('../src/styles/app.css')).response.text(), 'CSS');
  });
  await test('legacy_own_static_cache_survives_update', async () => {
    const w = worker({ offline: true });
    await w.seed('estudai-static-0.1.0-alpha-recovery-08', '../src/pages/app.js', 'LEGACY');
    await w.lifecycle('activate');
    assert.equal(await (await w.fetch('../src/pages/app.js')).response.text(), 'LEGACY');
  });
  await test('activation_preserves_other_project_caches', async () => {
    const w = worker();
    await w.seed('estudai-static-other-project', 'http://localhost/other/app.js', 'OTHER');
    await w.seed('little-rio-cache', 'http://localhost:8082/', 'LR');
    await w.lifecycle('activate');
    assert.ok(w.stores.has('estudai-static-other-project'));
    assert.ok(w.stores.has('little-rio-cache'));
    assert.equal(w.deleted.length, 0);
  });
  for (const [name, url, mode, method] of [
    ['API_GET', 'http://localhost/estudAi_1.1/server/api/auth.php?action=status', 'cors', 'GET'],
    ['API_POST', 'http://localhost/estudAi_1.1/server/api/auth.php', 'cors', 'POST'],
    ['root_favicon', 'http://localhost/favicon.ico', 'no-cors', 'GET'],
    ['other_project', 'http://localhost/another-project/index.html', 'navigate', 'GET'],
    ['external_font', 'https://fonts.googleapis.com/css2?family=Inter', 'no-cors', 'GET']
  ]) {
    await test('no_interception_' + name, async () => {
      const w = worker({ offline: true });
      assert.equal((await w.fetch(url, mode, method)).handled, false);
      assert.equal(w.calls.length, 0);
    });
  }
  await test('network_navigation_error_status_is_not_hidden', async () => {
    const w = worker({ status: 500 }); await w.seed(w.current, 'index.html', 'OLD');
    assert.equal((await w.fetch('index.html', 'navigate')).response.status, 500);
  });
  await test('network_static_error_status_is_not_faked', async () => {
    const w = worker({ status: 403 });
    assert.equal((await w.fetch('../src/styles/missing.css')).response.status, 403);
  });
  await test('cache_storage_failure_does_not_break_online_navigation', async () => {
    const w = worker(); w.faults.storage = true;
    assert.equal((await w.fetch('index.html', 'navigate')).response.status, 200);
  });
  await test('cache_storage_failure_offline_still_returns_Response', async () => {
    const w = worker({ offline: true }); w.faults.storage = true;
    assert.equal((await w.fetch('index.html', 'navigate')).response.status, 503);
  });
  await test('cache_write_failure_has_no_unhandled_rejection', async () => {
    const w = worker(); w.faults.write = true;
    assert.equal((await w.fetch('../src/styles/app.css')).response.status, 200);
  });
  await test('navigation_is_cached_without_tracking_query', async () => {
    const w = worker();
    await w.fetch('index.html?utm_source=chatgpt.com', 'navigate');
    const values = w.stores.get(w.current);
    assert.ok(values.has(new URL('index.html', SCRIPT).href));
    assert.ok(![...values.keys()].some(k => k.includes('utm_source')));
  });
  await test('defensive_invalid_network_result_is_never_forwarded', async () => {
    const w = worker({ invalidResponse: true });
    assert.equal((await w.fetch('index.html', 'navigate')).response.status, 503);
  });
  await test('cache_clone_precedes_client_body_consumption', async () => {
    await worker().rememberWhileBodyIsConsumed();
  });
  const failed = results.filter(r => r.result === 'FAIL').length;
  console.log(JSON.stringify({ result: failed ? 'FAIL' : 'PASS', tests: results.length, failed, results, real_network_requests: 0 }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => { console.error(error.message); process.exitCode = 1; });

