// Stage 11 real Chrome planned practice; isolated profile and existing fixtures.
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
if (accounts.length !== 2 || !accounts.every(a => a.email.endsWith('@estudai.invalid'))) throw new Error('Only existing fictional accounts permitted');
const guard = "require 'server/config/database.php';$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)exit(1);$db=getDB();if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost'||$ai['enabled']!==false)exit(1);echo 'SAFE';";
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

const checks = [], screenshots = [], network = [], failures = [], runtimeErrors = [], workerErrors = [];
const SITE = 'http://localhost/estudAi_1.1/';
const PAGE = SITE + 'app/public/';
let socket, session, phase = 'startup', sequence = 0;
const pending = new Map();
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
async function waitFor(fn, name, timeout = 15000) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try { if (await evaluate(fn)) return; } catch (_) {}
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
  await evaluate(sel => document.querySelector(sel)?.scrollIntoView({ block: 'center', behavior: 'instant' }), selector);
  let point = null;
  const deadline = Date.now() + 7000;
  while (Date.now() < deadline) {
    await settleVisuals();
    point = await evaluate(sel => {
      const element = document.querySelector(sel);
      if (!element) return null;
      const rect = element.getBoundingClientRect();
      const x = rect.x + rect.width / 2, y = rect.y + rect.height / 2;
      const hit = document.elementFromPoint(x, y);
      return rect.width && rect.height && hit && (element === hit || element.contains(hit)) ? { x, y } : null;
    }, selector);
    if (point) break;
    await new Promise(resolve => setTimeout(resolve, 100));
  }
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


  phase = 'stage13-existing-results';
  const saved = JSON.parse(fs.readFileSync(path.join(privateDir, 'real_plan_result.json'), 'utf8'));
  const ledgerPath = path.join(privateDir, 'openrouter_budget.json');
  const ledgerBefore = fs.readFileSync(ledgerPath, 'utf8');
  const metadataBefore = fs.readFileSync(ledgerPath + '.results.jsonl', 'utf8');
  await navigate(PAGE + 'index.html');
  await waitFor(() => typeof fazerLogin === 'function', 'login');
  await screenshot('ai-login');
  await login(accounts[1]);
  await section('plano');
  check(await evaluate(id => state.plano.id === id && state.plano.origem === 'fallback' && state.plano.tarefas.length === 1, saved.plano.id), 'own_fallback_plan_restored');
  await click('#btn-gerar-plano');
  await waitFor(() => !document.querySelector('#btn-gerar-plano').disabled && document.querySelector('#plan-status-text').textContent.includes('recuperado'), 'plan replay complete');
  check(await evaluate(id => state.plano.id === id, saved.plano.id), 'plan_generation_button_reuses_existing_plan');
  await screenshot('ai-plan-fallback', true, true);
  await section('calendario');
  await waitFor(() => state.calendarEvents.length === 1, 'one owned calendar event');
  await evaluate(() => selectCalendarDay('2026-11-16'));
  await screenshot('ai-calendar');
  await section('redacao');
  await waitFor(() => state.redacoes.length === 1, 'saved fictional essay');
  await click('#redacao-historico button');
  await waitFor(() => !!state.redacaoAtualId, 'essay open click');
  check(await evaluate(() => document.querySelector('#redacao-analise').textContent.includes('Orientacao local')), 'saved_essay_origin_is_local');
  await click('button[onclick="analisarRedacao()"]');
  await waitFor(() => !state.redacaoPending && document.querySelector('#redacao-status').textContent.includes('Orientacao local pronta'), 'cached essay result');
  await screenshot('ai-essay-local', true, true);
  await send('Page.reload');
  await waitFor(() => !!window.EstudAIDashboardReady, 'reload');
  check(await evaluate(async () => await window.EstudAIDashboardReady), 'dashboard_ready_after_reload');
  check(await evaluate(id => state.plano.id === id, saved.plano.id), 'plan_persisted_after_reload');
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await click('.mobile-nav-tab[data-section="dashboard"]');
  await waitFor(() => document.querySelector('#diagnostic-summary').textContent.includes('Nenhum diagnostico'), 'no fabricated diagnostic');
  await evaluate(() => document.querySelector('#btn-gerar-diagnostico').scrollIntoView({ block: 'center', behavior: 'instant' }));
  await click('#btn-gerar-diagnostico');
  await waitFor(() => !document.querySelector('#btn-gerar-diagnostico').disabled && !document.querySelector('#diagnostic-summary').textContent.includes('Gerando'), 'diagnostic unavailable handled');
  check(await evaluate(() => !state.diagnostico && !document.querySelector('#diagnostic-content').textContent.includes('Perfil ficticio')), 'failed_diagnostic_is_not_presented_as_AI_success');
  await screenshot('ai-diagnostic-unavailable', false);
  check(await evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'mobile_no_horizontal_overflow');
  await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false });
  await evaluate(() => document.querySelector('.btn-logout').click());
  await waitFor(() => location.pathname.endsWith('/index.html') && typeof fazerLogin === 'function', 'logout');
  await login(accounts[0]);
  await section('plano');
  check(await evaluate(() => state.plano.id === 2 && state.plano.tarefas.length === 4), 'original_account_plan_preserved');
  await screenshot('ai-original-plan', true, true);
  await section('redacao');
  await waitFor(() => document.querySelector('#redacao-historico').textContent.includes('Nenhuma redacao'), 'other account essay hidden');
  check(await evaluate(() => state.redacoes.length === 0), 'essay_isolation');
  check(fs.readFileSync(ledgerPath, 'utf8') === ledgerBefore && fs.readFileSync(ledgerPath + '.results.jsonl', 'utf8') === metadataBefore, 'browser_did_not_spend_external_budget');
  check(runtimeErrors.length === 0 && workerErrors.length === 0, 'no_uncaught_page_or_worker_errors');
  check(!network.some(r => /openrouter|cron_|action=gerar_anual|action=replanejar/.test(r.url)), 'no_external_browser_calls_or_blocked_actions');
  check(network.filter(r => r.url.includes('/server/api/')).every(r => !r.fromServiceWorker), 'APIs_not_served_from_worker_cache');
  report = { result: 'PASS', browser: version.product, checks, screenshots, network, failures, runtimeErrors, workerErrors, real_browser: true, isolated_profile: true, visual_inspection_pending: true, external_AI_requests: 0, new_plans: 0, new_accounts: 0, personal_browser_profile_used: false };

} catch (error) {
  try { await screenshot('ai-failure', false); } catch (_) {}
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
const output = path.join(privateDir, 'ai-browser-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ result: report.result, phase: report.phase, error: report.error, checks: checks.length, screenshots: screenshots.length, report: output, real_browser: true, chrome_closed: chrome.exitCode !== null }));
