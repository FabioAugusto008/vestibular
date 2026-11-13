// Stage 14: real presentation screenshots, existing fictional accounts and UI.
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

const outputDir = path.join(ROOT, 'docs/apresentacao-2026/screenshots');
fs.mkdirSync(outputDir, { recursive: true });
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
  await waitFor(() => ![...document.querySelectorAll('.toast')].some(e => e.getBoundingClientRect().height && getComputedStyle(e).opacity !== '0'), 'temporary notifications finished', 12000);
  const metrics = fullPage ? await send('Page.getLayoutMetrics') : null;
  const data = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: fullPage,
    ...(metrics ? { clip: { x: 0, y: 0, width: metrics.cssContentSize.width, height: Math.min(metrics.cssContentSize.height, 2400), scale: 1 } } : {}) });
  const file = path.join(outputDir, name + '.png');
  fs.writeFileSync(file, Buffer.from(data.data, 'base64'));
  screenshots.push({ view: name, path: file });
  console.log(JSON.stringify({ captured: name + '.png' }));
}
async function screenshotRegion(name, selector) {
  await settleVisuals();
  const rect = await evaluate(sel => {
    const r = document.querySelector(sel).getBoundingClientRect();
    return {x:r.x + window.scrollX,y:r.y + window.scrollY,width:r.width,height:r.height,scale:2};
  }, selector);
  const data = await send('Page.captureScreenshot', {format:'png',captureBeyondViewport:true,clip:rect});
  const file = path.join(outputDir,name + '.png');
  fs.writeFileSync(file,Buffer.from(data.data,'base64'));
  screenshots.push({view:name,path:file,region:selector});
  console.log(JSON.stringify({captured:name + '.png'}));
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
  await send('Emulation.setDeviceMetricsOverride', { width: 1920, height: 1080, deviceScaleFactor: 1, mobile: false });



  phase = 'capture-stage14';
  await navigate(PAGE + 'index.html');
  await waitFor(() => typeof fazerLogin === 'function', 'login');
  await login(accounts[0]);
  if (process.argv[4] === '--polish-result') {
    const f=JSON.parse(fs.readFileSync(path.join(recoveryRoot,'20261111_204714_stage12_e851887b/simulation_fixture.private.json'),'utf8'));
    await section('simulados');
    await waitFor(() => state.simulados.length === 2,'catalog');
    await click('#simulados-list button[onclick="iniciarSimulado(' + f.classic_id + ')"]');
    await waitFor(() => state.simFinalizado && !state.simStarting,'saved result');
    await screenshotRegion('07-resultado-simulado','#simulado-result');
  } else {
  await screenshot('01-dashboard');
  await section('plano');
  await waitFor(() => state.plano?.id === 2 && state.plano.tarefas.length === 4, 'existing November plan');
  await screenshot('02-planejamento', true, true);
  await section('calendario');
  await waitFor(() => state.calendarEvents.length === 4, 'November calendar');
  await evaluate(() => selectCalendarDay('2026-11-16'));
  await screenshot('03-calendario', true, true);
  await section('questoes');
  await waitFor(() => Array.isArray(state.questoes), 'daily questions loaded');
  if (await evaluate(() => state.questoes.length === 0)) {
    await click('#q-options button');
    await waitFor(() => !state.preparingQuestions && state.questoes.length > 0, 'normal daily preparation');
  }
  check(await evaluate(() => !state.questoes.some(q => q.respondida)), 'daily ungraded question for screenshot');
  await screenshot('04-questoes');
  await section('simulados');
  await waitFor(() => state.simulados.length === 2, 'existing simulation catalog');
  await screenshot('06-simulados');
  await section('estatisticas');
  await waitFor(() => !document.querySelector('#sec-estatisticas').textContent.includes('Carregando'), 'statistics ready');
  await click('#sec-estatisticas details summary');
  await waitFor(() => document.querySelector('#est-pct-geral').textContent === '50%', 'statistics values');
  await screenshot('08-estatisticas', true, true);
  console.log(JSON.stringify({ priority_screenshots_ready: 6, folder: outputDir }));

  const fixture = JSON.parse(fs.readFileSync(path.join(recoveryRoot, '20261111_204714_stage12_e851887b/simulation_fixture.private.json'), 'utf8'));
  await section('simulados');
  await click('#simulados-list button[onclick="iniciarSimulado(' + fixture.classic_id + ')"]');
  await waitFor(() => state.simFinalizado && !state.simStarting, 'existing finalized simulation');
  await evaluate(() => document.querySelector('#simulado-result').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('07-resultado-simulado', false);
  await section('plano');
  const practice = await evaluate(() => state.plano.tarefas.find(t => t.tipo === 'questoes' && t.status === 'concluida')?.id);
  check(!!practice, 'existing completed practice');
  await click('#plan-content button[onclick="abrirExerciciosTarefa(' + practice + ')"]');
  await waitFor(() => !!state.currentExerciseLote?.exercicios?.length, 'existing corrected exercise');
  await evaluate(() => window.scrollTo({top:document.querySelector('#exercises-content .exercise-card').getBoundingClientRect().top + window.scrollY - 145,behavior:'instant'}));
  await screenshot('05-correcao', false);
  await section('conquistas');
  await waitFor(() => !document.querySelector('#sec-conquistas').textContent.includes('Carregando'), 'achievements ready');
  await screenshot('09-conquistas', true, true);
  await section('rotina');
  await evaluate(() => document.querySelector('#sec-rotina .card:last-child')?.scrollIntoView({block:'end',behavior:'instant'}));
  await screenshot('10-rotina', true, true);
  await section('dashboard');
  await evaluate(() => document.querySelector('#diagnostic-summary').closest('.card, .panel, article, .grid-2')?.scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('12-perfil-diagnostico', false);
  await evaluate(() => window.scrollTo({top:0,behavior:'instant'}));
  await click('header .btn-logout');
  await waitFor(() => location.pathname.endsWith('/index.html'), 'logout');
  await login(accounts[1]);
  await section('redacao');
  await waitFor(() => state.redacoes?.length === 1, 'existing local essay');
  const essayId = await evaluate(() => state.redacoes[0].id);
  await click('#redacao-historico button[onclick="abrirRedacao(' + essayId + ')"]');
  await screenshot('11-redacao', true, true);
  check(!network.some(r => /openrouter|cron_|action=gerar_anual|action=replanejar|diagnostico.php.action=gerar/.test(r.url)), 'no_remote_AI_or_blocked_actions');
  check(runtimeErrors.length === 0, 'no_unhandled_errors');
  }
  report = { result: 'PASS', browser: version.product, checks, screenshots, network, failures, runtimeErrors, workerErrors, real_browser: true, external_AI_requests: 0, new_plans: 0, new_accounts: 0 };
} catch (error) {
  report = { result:'PARTIAL', phase, error:safe(error.message), checks, screenshots, network, failures, runtimeErrors, workerErrors };
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    try { await send('Browser.close', {}, null); } catch (_) {}
    socket.close();
  }
  await new Promise(resolve => { if (chrome.exitCode !== null) resolve(); else { chrome.once('exit', resolve); setTimeout(resolve, 3000); } });
  if (chrome.exitCode === null) chrome.kill();
}
const output = path.join(privateDir, 'capture-stage14-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({result:report.result,error:report.error,screenshots:screenshots.length,report:output,chrome_closed:chrome.exitCode !== null}));
