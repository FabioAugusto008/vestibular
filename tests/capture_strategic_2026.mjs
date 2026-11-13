// Strategic journey: original UI, existing fictional accounts, no application writes.
// Uses an isolated private profile. Never prints credentials, tokens or cookies.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
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

function rowSnapshot() {
  const code = guard.replace("echo 'SAFE';", '') + "$db->exec('START TRANSACTION READ ONLY');$out=[];foreach(['usuarios','estudo_perfis','onboarding_respostas','planos_estudo','planejamento_semanal_controle','plano_versoes','plano_estudo_itens','tarefas_estudo','eventos_calendario_estudai','questoes','questoes_alternativas','questoes_do_dia','respostas_usuario','desempenho_diario','ia_historico','redacoes_enem','simulado_tentativas','simulado_respostas','simulados_planejados','simulados_planejados_respostas'] as $t){$rows=$db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll();$out[$t]=['rows'=>count($rows),'sha256'=>hash('sha256',json_encode($rows))];}$out['grants']=$db->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);$db->rollBack();echo json_encode($out);";
  const p = spawnSync('C:\\xampp\\php\\php.exe',['-d','display_errors=0','-d','log_errors=0','-r',code],{cwd:ROOT,encoding:'utf8',windowsHide:true});
  if(p.status !== 0) throw new Error('Read-only test database snapshot failed');
  return JSON.parse(p.stdout);
}
const dataBefore=rowSnapshot();
fs.writeFileSync(path.join(privateDir,'rows_before.json'),JSON.stringify(dataBefore,null,2));

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

const outputDir = path.join(ROOT, 'docs/apresentacao-2026/screenshots-estrategicos');
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
      const local = ['localhost', '127.0.0.1', '::1', '[::1]'].includes(url.hostname);
      const body = p.request.postData || '';
      const authAction = url.searchParams.get('action') || new URLSearchParams(body).get('action') || body.match(/name="action"\r?\n\r?\n(login|logout)\r?\n/)?.[1];
      const auth = local && url.pathname.endsWith('/auth.php') && ['login','logout','status'].includes(authAction);
      const read = !url.pathname.includes('/server/api/') || p.request.method === 'GET' || auth;
      const allowed = (local || ['fonts.googleapis.com','fonts.gstatic.com'].includes(url.hostname) || url.protocol === 'file:') && read;
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




  phase = 'journey-B-login-onboarding';
  await navigate(PAGE+'index.html');
  await waitFor(() => typeof fazerLogin === 'function','login ready');
  check(await evaluate(() => !document.querySelector('#login-email').value && !document.querySelector('#login-senha').value),'empty_login_fields');
  await screenshot('01-login-estudai');
  await login(accounts[1]);
  await click("#sec-dashboard button[onclick='openOnboardingModal()']");
  await waitFor(() => document.querySelector('#modal-onboarding').classList.contains('active'),'existing onboarding edit');
  await click('[data-onb-step="0"]');
  await screenshot('02-onboarding-objetivo');
  await click('[data-onb-step="2"]');
  await screenshot('03-onboarding-dificuldades');
  await evaluate(() => document.querySelector('#onb-conteudos-reforco').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('03b-conteudos-reforco',false);
  await click('[data-onb-step="1"]');
  await screenshot('04-onboarding-disponibilidade');
  await click('[data-onb-step="3"]');
  await screenshot('05b-onboarding-preferencias');
  await click('[data-onb-step="4"]');
  await screenshot('05-perfil-educacional');
  const education=await evaluate(() => ({objetivo:state.onboarding.objetivo,data_prova:state.onboarding.data_prova,disponibilidade:state.onboarding.disponibilidade,reforcos:state.onboarding.reforcos,conteudos_reforco:state.onboarding.conteudos_reforco,intensidade:state.onboarding.intensidade}));
  fs.writeFileSync(path.join(privateDir,'fictional_education.json'),JSON.stringify(education,null,2));
  await click('#modal-onboarding .modal-close');
  await section('plano');
  await waitFor(() => state.plano?.origem === 'fallback' && state.plano?.tarefas?.length === 1,'existing one-task B plan');
  await screenshot('07-planejamento-semanal');
  await section('calendario');
  await waitFor(() => state.calendarEvents.length === 1,'B calendar one existing event');
  await evaluate(() => selectCalendarDay('2026-11-16'));
  await screenshot('08-calendario-personalizado');
  console.log(JSON.stringify({priority_core_ready:true,account:'B',onboarding_saved:false}));

  await section('dashboard');
  await evaluate(() => document.querySelector('#diagnostic-summary').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('06a-interface-diagnostico',false);
  await section('questoes');
  await waitFor(() => state.questoes?.length > 0,'existing daily batch');
  check(await evaluate(() => Object.keys(state.respostas||{}).length === 0),'no_new_daily_answers');
  await screenshot('10-questoes-pratica');
  await evaluate(() => window.scrollTo({top:0,behavior:'instant'}));
  await click('header .btn-logout');
  await waitFor(() => location.pathname.endsWith('/index.html'),'logout B');
  await login(accounts[0]);
  phase='additional-A-practice-results';
  await section('rotina');
  await waitFor(() => document.querySelector('#routine-done-list .task-card'),'existing recently completed activities');
  await evaluate(() => document.querySelector('#routine-done-list').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshotRegion('09-rotina-e-tarefas','#sec-rotina .card:has(#routine-done-list)');
  await section('plano');
  const taskId=await evaluate(() => state.plano.tarefas.find(t=>t.tipo==='questoes'&&t.status==='concluida').id);
  await click('#plan-content button[onclick="abrirExerciciosTarefa('+taskId+')"]');
  await waitFor(() => state.currentExerciseLote?.exercicios?.length===5,'existing answered practice');
  await evaluate(() => window.scrollTo({top:document.querySelector('#exercises-content .exercise-card').getBoundingClientRect().top+window.scrollY-145,behavior:'instant'}));
  await screenshot('11-correcao-feedback',false);
  await section('estatisticas');
  await click('#sec-estatisticas details summary');
  await waitFor(() => document.querySelector('#est-pct-geral').textContent==='50%','persisted statistics');
  await screenshot('12-estatisticas-progresso',true,true);
  await section('simulados');
  await waitFor(() => state.simulados.length===2,'existing simulation catalog');
  await screenshot('13-simulados');

  phase='conceptual-diagram';
  await navigate(pathToFileURL(path.join(ROOT,'docs/apresentacao-2026/personalizacao-conceitual.html')).href);
  await waitFor(() => document.querySelector('#conceptual-flow')!==null,'conceptual diagram');
  await screenshot('06-personalizacao-ia');
  const dataAfter=rowSnapshot();
  fs.writeFileSync(path.join(privateDir,'rows_after.json'),JSON.stringify(dataAfter,null,2));
  check(JSON.stringify(dataBefore)===JSON.stringify(dataAfter),'all_checked_rows_and_grants_unchanged');
  check(!network.some(r=>/openrouter|cron_|action=gerar_anual|action=replanejar/.test(r.url)),'no_remote_AI_or_blocked_commands');
  check(runtimeErrors.length===0,'no_unhandled_runtime_errors');
  report={result:'PASS',browser:version.product,checks,screenshots,network,failures,runtimeErrors,workerErrors,new_accounts:0,database_writes:0,external_AI_requests:0,diagram:'06-personalizacao-ia.png',onboarding_steps:5};
} catch(error) {
  report={result:'PARTIAL',phase,error:safe(error.message),checks,screenshots,network,failures,runtimeErrors,workerErrors};
} finally {
  if(socket?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},null);}catch{}socket.close();}
  await new Promise(resolve=>{if(chrome.exitCode!==null)resolve();else{chrome.once('exit',resolve);setTimeout(resolve,3000);}});
  if(chrome.exitCode===null)chrome.kill();
}
const output=path.join(privateDir,'strategic-capture-'+stamp+'.json');
fs.writeFileSync(output,JSON.stringify(report,null,2));
console.log(JSON.stringify({result:report.result,error:report.error,screenshots:screenshots.length,report:output,chrome_closed:chrome.exitCode!==null}));
