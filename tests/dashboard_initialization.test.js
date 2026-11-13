// Execute the real frontend scripts with a simulated DOM and HTTP responses.
// This verifies control flow and fault handling, not rendering or browser APIs.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const fixturePath = process.argv[2];
if (!fixturePath) throw new Error('Pass the private sanitized HTTP fixture path');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const html = fs.readFileSync(path.join(root, 'app/public/app.html'), 'utf8');
const ids = [...html.matchAll(/\bid="([^"]+)"/g)].map(match => match[1]);
const sourceFiles = ['app/src/config/api.js', 'app/src/services/http.js', 'app/src/pages/app.js'];
const results = [];

function element(id) {
  const classes = new Set();
  return {
    id, value: '', textContent: '', innerHTML: '', style: {}, dataset: {}, checked: false,
    disabled: false, children: [],
    classList: {
      add: (...names) => names.forEach(name => classes.add(name)),
      remove: (...names) => names.forEach(name => classes.delete(name)),
      contains: name => classes.has(name),
      toggle(name, force) {
        const add = force === undefined ? !classes.has(name) : force;
        if (add) classes.add(name); else classes.delete(name);
      }
    },
    addEventListener() {}, setAttribute() {}, remove() {},
    appendChild(child) { this.children.push(child); },
    querySelectorAll() { return []; }, querySelector() { return null; }
  };
}

async function scenario(name, options = {}) {
  const nodes = new Map(ids.map(id => [id, element(id)]));
  const calls = [], storageWrites = [], redirects = [], events = new Map();
  const stored = options.storage === undefined ? '{}' : options.storage;
  const data = JSON.parse(JSON.stringify(fixture));
  data['diagnostico.php?action=carregar_ultimo'] = { ok: true, resultado: null };
  if (options.changeFixture) options.changeFixture(data);
  const document = {
    getElementById: id => nodes.get(id) || null,
    querySelectorAll: selector => options.selectors?.[selector] || [],
    querySelector(selector) { return selector.startsWith('#') ? nodes.get(selector.slice(1)) || null : null; },
    createElement: tag => element(tag), documentElement: element('html'),
    addEventListener() {}
  };
  const context = {
    document, Headers, Response, URL, URLSearchParams,
    CSS: { escape: value => String(value) }, navigator: {},
    console: { warn() {}, error() {}, log() {} },
    location: {
      get href() { return redirects.at(-1) || 'app.html'; },
      set href(value) { redirects.push(value); }
    },
    localStorage: {
      getItem() { if (options.storageThrows) throw new Error('Storage unavailable'); return stored; },
      setItem(key, value) { storageWrites.push({ key, value }); },
      removeItem() { throw new Error('Storage must not be cleared'); }
    },
    setTimeout: () => 1, clearTimeout() {}, setInterval: () => 1, clearInterval() {},
    CustomEvent: class { constructor(type) { this.type = type; } },
    addEventListener(type, callback) { const list = events.get(type) || []; list.push(callback); events.set(type, list); },
    dispatchEvent(event) { (events.get(event.type) || []).forEach(callback => callback(event)); },
    fetch: async (url, request = {}) => {
      const parsed = new URL(url, 'http://localhost/estudAi_1.1/app/public/app.html');
      const key = parsed.pathname.split('/').pop() + parsed.search;
      calls.push({ key, method: request.method || 'GET', hasCSRF: request.headers?.has('X-CSRF-Token') || false });
      assert.ok(Object.hasOwn(data, key), 'Unexpected initialization request: ' + key);
      const failure = options.failures?.[key];
      if (failure === 'network') throw new TypeError('Failed to fetch');
      if (failure === 'json') return new Response('not-json', { status: 200 });
      const status = typeof failure === 'number' ? failure : 200;
      const body = status === 200 ? data[key] : { erro: 'Modulo indisponivel.' };
      return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    }
  };
  context.window = context;
  vm.createContext(context);
  for (const file of sourceFiles) vm.runInContext(fs.readFileSync(path.join(root, file), 'utf8'), context, { filename: file });
  const ready = await context.EstudAIDashboardReady;
  const state = vm.runInContext('({sessionExpired:state.sessionExpired, errors:{...state.moduleErrors}, complete:state.onboardingCompleto})', context);
  assert.equal(storageWrites.length, 0, 'Initialization must not overwrite storage');
  assert.ok(calls.every(call => call.method === 'GET'), 'Initialization must not POST');
  assert.ok(calls.every(call => call.key === 'diagnostico.php?action=carregar_ultimo' || !/diagnostico|ia[.]php|cron|calendario|simulados/.test(call.key)), 'Out-of-scope initialization');
  assert.ok(calls.length <= 15, 'Unexpected retries or duplicate requests');
  assert.ok(redirects.length <= 1, 'Expired session must redirect only once');
  const result = { ready, state, nodes, calls, context };
  if (options.verify) options.verify(result);
  results.push({ test: name, result: 'PASS', requests: calls.length });
  return result;
}

async function main() {
  const normal = await scenario('real_sanitized_HTTP_contracts_empty_dashboard', { verify(r) {
    assert.equal(r.ready, true); assert.equal(r.calls.length, 15);
    assert.equal(r.state.complete, true); assert.equal(Object.keys(r.state.errors).length, 0);
    assert.equal(r.nodes.get('prog-mat-txt').textContent, '0/0');
    assert.match(r.nodes.get('dashboard-routine-summary').textContent, /Nenhuma tarefa/);
  } });
  for (const value of ['{broken', 'null', '[]', '42', '"text"']) {
    await scenario('invalid_localStorage_' + value, { storage: value, verify(r) { assert.equal(r.ready, true); assert.equal(r.state.complete, true); } });
  }
  await scenario('storage_access_denied', { storageThrows: true, verify(r) { assert.equal(r.ready, true); } });
  await scenario('questions_403_history_500_goals_500_are_isolated', {
    failures: { 'questoes.php?action=carregar': 403, 'historico.php': 500, 'metas.php?action=carregar': 500 },
    verify(r) {
      assert.equal(r.ready, true); assert.equal(r.state.complete, true);
      for (const key of ['questoes', 'historico', 'metas']) assert.equal(r.state.errors[key], true);
      assert.equal(r.nodes.get('stat-dias').textContent, 'Indisponivel');
      assert.equal(r.nodes.get('meta-total').textContent, 'Indisponivel');
      assert.equal(r.nodes.get('conquistas-count').textContent, '0/22');
    }
  });
  await scenario('malformed_statistics_JSON_isolated', { failures: { 'estatisticas.php?action=estudai_geral': 'json' }, verify(r) {
    assert.equal(r.ready, true); assert.equal(r.state.errors.estatisticas, true);
    assert.equal(r.nodes.get('dash-week-done').textContent, 'Indisponivel');
  } });
  await scenario('history_network_failure_isolated', { failures: { 'historico.php': 'network' }, verify(r) { assert.equal(r.ready, true); assert.equal(r.state.errors.historico, true); } });
  await scenario('multiple_rejected_promises_keep_other_modules', {
    failures: { 'questoes.php?action=carregar': 'network', 'historico.php': 'network', 'metas.php?action=carregar': 'network' },
    verify(r) {
      assert.equal(r.ready, true);
      for (const key of ['questoes', 'historico', 'metas']) assert.equal(r.state.errors[key], true);
      assert.equal(r.nodes.get('conquistas-count').textContent, '0/22');
    }
  });
  await scenario('routine_failure_does_not_claim_zero_tasks', { failures: { 'tarefas-estudo.php?action=semana': 500 }, verify(r) {
    assert.equal(r.ready, true); assert.equal(r.state.errors.rotina, true);
    assert.match(r.nodes.get('dashboard-routine-summary').textContent, /indisponiveis/);
  } });
  await scenario('profile_500_keeps_other_modules_and_does_not_open_empty_onboarding', { failures: { 'onboarding.php?action=status': 500 }, verify(r) {
    assert.equal(r.ready, true); assert.equal(r.state.errors.perfil, true);
    assert.equal(r.nodes.get('modal-onboarding').classList.contains('active'), false);
    assert.equal(r.nodes.get('core-perfil-status').textContent, 'Indisponivel');
  } });
  await scenario('anonymous_session_redirects_before_modules', { changeFixture(data) { data['auth.php?action=status'] = { logado: false }; }, verify(r) {
    assert.equal(r.ready, false); assert.equal(r.calls.length, 1); assert.equal(r.context.location.href, 'index.html');
  } });
  await scenario('session_401_redirects', { failures: { 'auth.php?action=status': 401 }, verify(r) { assert.equal(r.ready, false); assert.equal(r.context.location.href, 'index.html'); assert.equal(r.calls.length, 1); } });
  await scenario('auth_500_stops_critical_initialization', { failures: { 'auth.php?action=status': 500 }, verify(r) { assert.equal(r.ready, false); assert.equal(r.calls.length, 1); } });
  await scenario('invalid_auth_JSON_stops_critical_initialization', { failures: { 'auth.php?action=status': 'json' }, verify(r) {
    assert.equal(r.ready, false); assert.equal(r.calls.length, 1);
  } });
  await scenario('profile_401_redirects_before_secondary_modules', { failures: { 'onboarding.php?action=status': 401 }, verify(r) {
    assert.equal(r.ready, false); assert.equal(r.calls.length, 2); assert.equal(r.context.location.href, 'index.html');
  } });
  await scenario('secondary_401_is_not_hidden', { failures: { 'metas.php?action=carregar': 401 }, verify(r) {
    assert.equal(r.ready, false); assert.equal(r.context.location.href, 'index.html'); assert.equal(r.state.sessionExpired, true);
  } });
  await scenario('simultaneous_401_responses_redirect_once', { failures: { 'metas.php?action=carregar': 401, 'historico.php': 401 }, verify(r) {
    assert.equal(r.ready, false); assert.equal(r.context.location.href, 'index.html');
  } });
  await scenario('incomplete_onboarding_opens_wizard_without_other_requests', { changeFixture(data) {
    data['onboarding.php?action=status'] = { ok: true, onboarding_completo: false, perfil: null };
  }, verify(r) { assert.equal(r.ready, true); assert.equal(r.calls.length, 2); assert.equal(r.nodes.get('modal-onboarding').classList.contains('active'), true); } });
  await scenario('numeric_PDO_strings_are_not_concatenated', { changeFixture(data) {
    data['historico.php'].totais = { dias_estudados: '2', total_acertos: '3', total_erros: '2' };
  }, verify(r) { assert.equal(r.ready, true); assert.equal(r.nodes.get('stat-pct').textContent, '60%'); } });
  vm.runInContext("updateDashboard({questoes:[{id:1,materia:'Matemática'}],respostas:{1:{marcada:'A'}},finalizado:false})", normal.context);
  assert.equal(normal.nodes.get('prog-mat-txt').textContent, '1/1');
  results.push({ test: 'actual_question_denominator_and_accent_normalization', result: 'PASS' });
  console.log(JSON.stringify({ result: 'PASS', scenarios: results.length, results, simulated_DOM: true, visual_validation: false, real_network_requests: 0 }));
}

if (require.main === module) {
  main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
module.exports = { scenario };
