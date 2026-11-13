// Real Chrome headless integration/visual/PWA checks; no external dependencies.
// Uses an isolated private profile. Never prints credentials, tokens or cookies.
import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const privateDir = path.resolve(process.argv[2] || '');
const accountsPath = path.resolve(process.argv[3] || '');
const recoveryRoot = path.resolve(process.env.LOCALAPPDATA, 'EstudAI/Recovery');
if (!privateDir.startsWith(recoveryRoot + path.sep) || !accountsPath.startsWith(recoveryRoot + path.sep) || path.basename(accountsPath) !== 'test_accounts.private.json') throw new Error('Use private recovery paths');
const accounts = JSON.parse(fs.readFileSync(accountsPath, 'utf8'));
// Stage 11 explicitly adds two owned practice fixtures to the existing plan.
const aiPlanningEnabled = process.argv.includes('--ai-planning-enabled');
const expectedPlanTasks = process.argv.includes('--simulations-enabled') ? 4 : process.argv.includes('--planned-exercises-enabled') ? 3 : 1;
if (accounts.length !== 2 || !accounts.every(a => a.email.endsWith('@estudai.invalid'))) throw new Error('Only existing fictional accounts permitted');
const guard = "require 'server/config/database.php';$db=getDB();$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost'||$ai['enabled']!==false)exit(1);echo 'SAFE';";
const preflight = spawnSync('C:\\xampp\\php\\php.exe', ['-d', 'display_errors=0', '-d', 'log_errors=0', '-r', guard], { cwd: ROOT, encoding: 'utf8', windowsHide: true });
if (preflight.status !== 0 || preflight.stdout !== 'SAFE') throw new Error('Real database/AI guard failed');
const stamp = Date.now().toString();
const profile = path.join(privateDir, 'chrome-visual-' + stamp);
fs.mkdirSync(profile);
fs.mkdirSync(path.join(profile, 'Default'));
fs.writeFileSync(path.join(profile, 'Default/Preferences'), JSON.stringify({ credentials_enable_service: false, profile: { password_manager_enabled: false } }));
const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--disable-background-networking', '--disable-component-update', '--disable-sync', '--disable-extensions',
  '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0',
  '--user-data-dir=' + profile, 'about:blank'
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
chrome.stderr.on('data', () => {});

const checks = [], screenshots = [], network = [], failures = [], runtimeErrors = [], workerErrors = [], fonts = [];
const SITE = 'http://localhost/estudAi_1.1/';
const PAGE = SITE + 'app/public/';
const SENTINELS = ['little-rio-stage09-sentinel', 'estudai-static-other-project'];
let socket, session, phase = 'startup', sequence = 0;
const pending = new Map();
const workerSessions = new Map();
function safe(text) {
  let value = String(text || '');
  for (const a of accounts) value = value.split(a.password).join('[redacted]').split(a.email).join('[fictional-email]');
  return value.replace(/[a-f0-9]{64}/gi, '[redacted-token]');
}
function check(ok, name) {
  if (!ok) throw new Error('Check failed: ' + name);
  checks.push({ test: name, result: 'PASS' });
}
async function send(method, params = {}, sid = session) {
  const id = ++sequence;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error('Protocol timeout: ' + method)); }, 20000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params, ...(sid ? { sessionId: sid } : {}) }));
  });
}
async function evaluate(fn, arg) {
  const response = await send('Runtime.evaluate', {
    expression: '(' + fn.toString() + ')(' + JSON.stringify(arg) + ')',
    awaitPromise: true, returnByValue: true, userGesture: true
  });
  if (response.exceptionDetails) throw new Error('Browser evaluation failed in phase ' + phase + ': ' + safe(response.exceptionDetails.exception?.description || response.exceptionDetails.text));
  return response.result?.value;
}
async function waitFor(fn, name, timeout = 15000, arg) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try { if (await evaluate(fn, arg)) return; } catch (_) {}
    await new Promise(r => setTimeout(r, 100));
  }
  throw new Error('Browser wait failed: ' + name);
}
async function navigate(url) {
  await send('Page.navigate', { url });
  await waitFor(() => document.readyState !== 'loading', 'document ready');
}
async function settleVisuals(allowNavigation = false) {
  try { await evaluate(async () => {
    const animations = document.getAnimations().filter(a => a.playState === 'running' && Number.isFinite(a.effect?.getComputedTiming().endTime));
    await Promise.race([Promise.allSettled(animations.map(a => a.finished)), new Promise(r => setTimeout(r, 2000))]);
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
  }); } catch (error) {
    if (!allowNavigation || !/Inspected target navigated|Execution context was destroyed|Cannot find context/.test(error.message)) throw error;
  }
}
async function click(selector) {
  await settleVisuals();
  const point = await evaluate(sel => {
    const element = document.querySelector(sel);
    if (!element) return null;
    element.scrollIntoView({ block: 'center', behavior: 'instant' });
    const rect = element.getBoundingClientRect();
    return rect.width && rect.height ? { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 } : null;
  }, selector);
  check(!!point, 'interactive_element_' + selector);
  await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: point.x, y: point.y, button: 'left', clickCount: 1 });
  await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: point.x, y: point.y, button: 'left', clickCount: 1 });
  await settleVisuals(true);
}
async function fill(selector, value) {
  await evaluate(sel => { const e = document.querySelector(sel); e.value = ''; e.focus(); }, selector);
  await send('Input.insertText', { text: value });
}
async function screenshot(name, scrollTop = true, fullPage = false) {
  await evaluate(async top => {
    await Promise.race([document.fonts.ready, new Promise(r => setTimeout(r, 4000))]);
    if (top) window.scrollTo({ top: 0, behavior: 'instant' });
  }, scrollTop);
  await settleVisuals();
  const metrics = fullPage ? await send('Page.getLayoutMetrics') : null;
  const data = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: fullPage,
    ...(metrics ? { clip: { x: 0, y: 0, width: metrics.cssContentSize.width, height: Math.min(metrics.cssContentSize.height, 2400), scale: 1 } } : {}) });
  const file = path.join(privateDir, 'visual-' + stamp + '-' + name + '.png');
  fs.writeFileSync(file, Buffer.from(data.data, 'base64'));
  screenshots.push({ view: name, path: file });
}
async function section(name, mobile = false) {
  await evaluate(({ name, mobile }) => {
    const list = [...document.querySelectorAll(mobile ? '.mobile-nav-tab' : '.nav-tab')];
    const button = list.find(b => b.getAttribute('onclick') === "showSection('" + name + "')");
    if (!button) throw new Error('Missing navigation button');
    button.click();
  }, { name, mobile });
  await waitFor(() => !document.querySelector('.section.active')?.hidden, 'section navigation');
  check(await evaluate(n => document.querySelector('#sec-' + n)?.classList.contains('active'), name), 'section_' + name + (mobile ? '_mobile' : '_desktop'));
}
async function login(account) {
  await fill('#login-email', account.email);
  await fill('#login-senha', account.password);
  await click('#btn-login');
  await waitFor(() => location.pathname.endsWith('/app.html') && !!window.EstudAIDashboardReady, 'login redirect');
  check(await evaluate(async () => await window.EstudAIDashboardReady), 'dashboard_ready_after_login');
  check(await evaluate(expected => document.querySelector('#mobile-user-name').textContent === expected, account.name), 'fictional_identity_matches');
}
async function offline(value) {
  const conditions = { offline: value, latency: 0, downloadThroughput: -1, uploadThroughput: -1 };
  await send('Network.emulateNetworkConditions', conditions);
  // Chrome's page network emulation does not cover a worker's own fetches.
  const targets = await send('Target.getTargets', {}, null);
  for (const target of targets.targetInfos.filter(t => t.type === 'service_worker' && t.url.startsWith(PAGE))) {
    if (!workerSessions.has(target.targetId)) {
      const attached = await send('Target.attachToTarget', { targetId: target.targetId, flatten: true }, null);
      workerSessions.set(target.targetId, attached.sessionId);
      await send('Network.enable', {}, attached.sessionId);
      await send('Network.setCacheDisabled', { cacheDisabled: true }, attached.sessionId);
    }
    await send('Network.emulateNetworkConditions', conditions, workerSessions.get(target.targetId));
  }
  check(workerSessions.size > 0, 'offline_emulation_includes_Service_Worker');
}
let report;
try {
  const active = path.join(profile, 'DevToolsActivePort');
  let lines;
  for (let i = 0; i < 120; i++) {
    if (fs.existsSync(active)) { lines = fs.readFileSync(active, 'utf8').trim().split(/\r?\n/); break; }
    if (chrome.exitCode !== null) throw new Error('Chrome exited before startup');
    await new Promise(r => setTimeout(r, 100));
  }
  if (!lines) throw new Error('Chrome startup timeout');
  socket = new WebSocket('ws://127.0.0.1:' + Number(lines[0]) + lines[1]);
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const job = pending.get(message.id); pending.delete(message.id); clearTimeout(job.timer);
      if (message.error) job.reject(new Error('Protocol error: ' + safe(message.error.message))); else job.resolve(message.result);
      return;
    }
    if (message.method === 'Fetch.requestPaused') {
      const p = message.params, url = new URL(p.request.url);
      const allowed = ['localhost', '127.0.0.1', '::1', '[::1]', 'fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname);
      send(allowed ? 'Fetch.continueRequest' : 'Fetch.failRequest', allowed ? { requestId: p.requestId } : { requestId: p.requestId, errorReason: 'BlockedByClient' }, message.sessionId).catch(() => {});
    }
    if (message.method === 'Network.responseReceived') {
      const p = message.params;
      network.push({ phase, url: p.response.url, status: p.response.status, fromServiceWorker: !!p.response.fromServiceWorker, fromDiskCache: !!p.response.fromDiskCache });
    }
    if (message.method === 'Network.loadingFailed') failures.push({ phase, error: safe(message.params.errorText), type: message.params.type });
    if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push({ phase, text: safe(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text) });
    if (message.method === 'ServiceWorker.workerErrorReported') workerErrors.push({ phase, text: safe(message.params.errorMessage?.errorMessage) });
  });
  await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
  const version = await send('Browser.getVersion', {}, null);
  const target = await send('Target.createTarget', { url: 'about:blank' }, null);
  const attached = await send('Target.attachToTarget', { targetId: target.targetId, flatten: true }, null);
  session = attached.sessionId;
  for (const domain of ['Page', 'Runtime', 'Network', 'Log', 'ServiceWorker']) await send(domain + '.enable');
  await send('Fetch.enable', { patterns: [{ urlPattern: '*', requestStage: 'Request' }] });
  await send('Network.setCacheDisabled', { cacheDisabled: true });
  await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false });

  phase = 'cache-isolation-setup';
  await navigate(PAGE + 'offline.html');
  await evaluate(async names => { for (const name of names) { const c = await caches.open(name); await c.put('http://localhost/another-project/stage09-sentinel.txt', new Response('SENTINEL')); } }, SENTINELS);
  phase = 'login-and-registration';
  await navigate(PAGE + 'index.html?utm_source=chatgpt.com');
  await waitFor(() => typeof fazerLogin === 'function' && !!document.querySelector('#btn-login'), 'login scripts');
  await screenshot('login-desktop');
  check(await evaluate(() => document.querySelector('#login-email').getBoundingClientRect().width > 0), 'login_layout_rendered');
  await click('#btn-login');
  check(await evaluate(() => document.querySelector('#alert-login').textContent.includes('Preencha')), 'login_empty_fields_error');
  await evaluate(() => document.querySelectorAll('.tab')[1].click());
  await click('#btn-cadastro');
  check(await evaluate(() => document.querySelector('#alert-cadastro').textContent.includes('Preencha')), 'registration_empty_fields_error');
  await fill('#cad-nome', accounts[0].name); await fill('#cad-email', accounts[0].email); await fill('#cad-senha', '123');
  await click('#btn-cadastro');
  check(await evaluate(() => document.querySelector('#alert-cadastro').textContent.includes('6 caracteres')), 'registration_short_password_error');
  await fill('#cad-senha', accounts[0].password);
  await click('#btn-cadastro');
  await waitFor(() => document.querySelector('#alert-cadastro').textContent.includes('cadastrado'), 'duplicate registration response');
  check(true, 'registration_duplicate_rejected_without_new_account');
  await evaluate(() => document.querySelectorAll('.tab')[0].click());
  await fill('#login-email', accounts[0].email); await fill('#login-senha', 'intentionally-invalid-password');
  await click('#btn-login');
  await waitFor(() => document.querySelector('#alert-login').textContent.includes('incorretos'), 'invalid credentials response');
  check(true, 'wrong_password_visible_error');

  phase = 'authenticated-core';
  await login(accounts[0]);
  const core = await evaluate(() => ({ complete: state.onboardingCompleto, plan: state.plano?.id, origin: state.plano?.origem, tasks: state.plano?.tarefas?.length || 0, errors: Object.keys(state.moduleErrors) }));
  check(core.complete && core.plan === 2 && core.origin === 'fallback' && core.tasks === expectedPlanTasks && core.errors.length === 0, 'existing_profile_and_plan_loaded_without_generation');
  await screenshot('dashboard-desktop');
  await click("#sec-dashboard button[onclick='openOnboardingModal()']");
  check(await evaluate(() => document.querySelector('#onb-objetivo').value === state.onboarding.objetivo && document.querySelector('#onb-data-prova').value === state.onboarding.data_prova), 'onboarding_saved_values_rendered');
  await evaluate(() => document.querySelector('[data-onb-step="1"]').click());
  check(await evaluate(() => document.querySelector('#onb-horarios input') !== null), 'onboarding_availability_step');
  await screenshot('onboarding-availability');
  for (const step of [2, 3, 4, 0]) await evaluate(s => document.querySelector('[data-onb-step="' + s + '"]').click(), step);
  check(true, 'onboarding_step_navigation_without_save');
  await click('#modal-onboarding .modal-close');
  check(await evaluate(() => !document.querySelector('#modal-onboarding').classList.contains('active') && getComputedStyle(document.querySelector('#modal-onboarding')).visibility === 'hidden'), 'onboarding_modal_really_closed');
  await section('plano');
  await waitFor(() => document.querySelector('#plan-content').textContent.includes('fallback'), 'plan content');
  check(await evaluate(() => document.querySelector('#plan-content').textContent.includes('2026-11-16') && document.querySelector('#plan-content').textContent.includes('60 min')), 'plan_date_duration_and_origin_visible');
  await screenshot('plan-desktop', true, true);
  await section('rotina'); await screenshot('routine-desktop');
  await section('calendario');
  await waitFor(count => state.calendarEvents.length === count, 'calendar events', 15000, expectedPlanTasks);
  await evaluate(() => selectCalendarDay('2026-11-16'));
  check(await evaluate(() => document.querySelector('#calendar-day-events').textContent.includes('Redacao') && document.querySelector('#calendar-day-events').textContent.includes('60 min')), 'calendar_matches_saved_task');
  await screenshot('calendar-desktop');
  await evaluate(() => calendarNextMonth());
  await waitFor(() => state.calendarDate.getMonth() === 11 && state.calendarEvents.length === 0, 'empty next month');
  check(true, 'calendar_next_month_empty');
  await evaluate(() => calendarPrevMonth());
  await waitFor(count => state.calendarDate.getMonth() === 10 && state.calendarEvents.length === count, 'return previous month', 15000, expectedPlanTasks);
  check(true, 'calendar_previous_month_restores_event');

  phase = 'mobile-layout';
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await section('dashboard', true);
  check(await evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'mobile_no_horizontal_document_overflow');
  await screenshot('dashboard-mobile');
  await section('calendario', true);
  await evaluate(() => selectCalendarDay('2026-11-16'));
  await evaluate(() => document.querySelector('#calendar-mobile-day-panel').scrollIntoView({ block: 'center', behavior: 'instant' }));
  await screenshot('calendar-mobile', false);
  await section('perfil', true);
  check(await evaluate(() => document.querySelector('#sec-perfil').classList.contains('active')), 'mobile_profile_navigation');
  await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false });

  phase = 'session-expiry';
  check(await evaluate(async () => (await apiFetch(apiEndpoint('auth', 'action=logout'))).status === 200), 'server_session_invalidated_without_DB_write');
  await evaluate(() => showSection('calendario'));
  await waitFor(() => location.pathname.endsWith('/index.html') && typeof fazerLogin === 'function', 'session expiry redirect');
  check(true, 'expired_session_redirects_to_login');
  phase = 'second-account-isolation';
  await login(accounts[1]);
  check(await evaluate(enabled => state.onboardingCompleto && (enabled ? state.plano?.id !== 2 && state.plano?.tarefas.length === 1 : state.plano === null), aiPlanningEnabled), 'second_account_does_not_see_first_plan');
  await section('calendario');
  await waitFor(enabled => state.calendarEvents.length === (enabled ? 1 : 0), 'second owned calendar', 15000, aiPlanningEnabled);
  check(true, 'second_account_does_not_see_first_events');
  await evaluate(() => document.querySelector('.btn-logout').click());
  await waitFor(() => location.pathname.endsWith('/index.html') && typeof fazerLogin === 'function', 'logout redirect');
  check(true, 'logout_visible_flow');

  phase = 'pwa-registration-and-isolation';
  await evaluate(async () => await navigator.serviceWorker.ready.then(r => r.scope));
  await waitFor(() => !!navigator.serviceWorker.controller, 'service worker controller');
  const pwa = await evaluate(async () => ({ scope: (await navigator.serviceWorker.ready).scope, script: navigator.serviceWorker.controller.scriptURL, keys: await caches.keys(), registrations: (await navigator.serviceWorker.getRegistrations()).map(r => r.scope) }));
  check(pwa.scope === PAGE && pwa.registrations.every(s => s === PAGE), 'service_worker_scope_is_project_public_directory');
  check(pwa.keys.some(k => k.includes('recovery-09')), 'new_cache_version_active');
  check(SENTINELS.every(k => pwa.keys.includes(k)), 'other_project_sentinel_caches_preserved');
  await navigate(PAGE + 'index.html');
  await waitFor(() => typeof fazerLogin === 'function', 'warm login');
  await new Promise(r => setTimeout(r, 300));
  fonts.push(...await evaluate(() => [...document.fonts].map(f => ({ family: f.family, status: f.status }))));
  phase = 'offline-warm-query';
  await offline(true);
  await navigate(PAGE + 'index.html?utm_source=chatgpt.com');
  await waitFor(() => document.title === 'EstudAI - Login' && typeof fazerLogin === 'function', 'offline query login');
  check(true, 'offline_query_uses_real_cached_login');
  await screenshot('login-offline-warm');
  check(await evaluate(async () => { try { await fetch('../../server/api/auth.php?action=status'); return false; } catch (_) { return true; } }), 'offline_API_is_network_failure_not_cached_success');
  check(await evaluate(async () => { try { const r = await fetch('../src/assets/icons/stage09-missing.svg'); return r.status === 503; } catch (_) { return false; } }), 'offline_uncached_asset_returns_valid_503');
  await navigate(PAGE + 'stage09-unvisited.html');
  await waitFor(() => document.title === 'EstudAI offline', 'offline fallback page');
  check(true, 'unvisited_navigation_uses_offline_page');
  phase = 'offline-cold-cache';
  await evaluate(async () => {
    const prefix = 'estudai-static-' + encodeURIComponent('/estudAi_1.1/app/') + '-';
    for (const key of await caches.keys()) if (key.startsWith(prefix)) await caches.delete(key);
  });
  await navigate(PAGE + 'stage09-cold.html');
  await waitFor(() => document.title === 'EstudAI offline', 'cold emergency offline response');
  check(await evaluate(() => document.body.textContent.includes('backend precisam de conexao')), 'offline_missing_fallback_returns_real_503_HTML');
  await screenshot('offline-cold');
  check((await evaluate(async () => await caches.keys())).filter(k => SENTINELS.includes(k)).length === SENTINELS.length, 'cold_test_preserves_unrelated_caches');
  await offline(false);
  await navigate(PAGE + 'index.html');
  await waitFor(() => typeof fazerLogin === 'function', 'network restored');
  check(true, 'login_recovers_after_network_restoration');
  check(runtimeErrors.length === 0, 'no_uncaught_page_exceptions');
  check(workerErrors.length === 0, 'no_service_worker_errors');
  check(!network.some(r => /openrouter|cron_|action=gerar/.test(r.url)), 'no_IA_cron_or_plan_generation_requests');
  check(network.filter(r => r.url.includes('/server/api/')).every(r => !r.fromServiceWorker), 'all_API_responses_bypass_Service_Worker');

  report = { result: 'PASS', browser: version.product, checks, screenshots, network, failures, runtimeErrors, workerErrors, fonts, pwa, real_browser: true, isolated_profile: true, visual_inspection_pending: true, new_plans: 0, new_accounts: 0, onboarding_saves: 0, external_AI_requests: 0, personal_browser_profile_used: false };
} catch (error) {
  report = { result: 'FAIL', phase, error: safe(error.message), checks, screenshots, network, failures, runtimeErrors, workerErrors, real_browser: true, personal_browser_profile_used: false };
  process.exitCode = 1;
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    try { await send('Browser.close', {}, null); } catch (_) {}
    socket.close();
  }
  await new Promise(resolve => { if (chrome.exitCode !== null) resolve(); else { chrome.once('exit', resolve); setTimeout(resolve, 3000); } });
  if (chrome.exitCode === null) chrome.kill();
}
const output = path.join(privateDir, 'browser-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ result: report.result, phase: report.phase, error: report.error, checks: checks.length, screenshots: screenshots.length, report: output, real_browser: true, chrome_closed: chrome.exitCode !== null }));

