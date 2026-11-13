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

  phase='simulations-stage12';
  const fixture=JSON.parse(fs.readFileSync(path.join(privateDir,'simulation_fixture.private.json'),'utf8'));
  const attemptIds=JSON.parse(fs.readFileSync(path.join(privateDir,'simulation_attempts.private.json'),'utf8'));
  const php=(code)=>{const p=spawnSync('C:\\xampp\\php\\php.exe',['-d','display_errors=0','-d','log_errors=0','-r',code],{cwd:ROOT,encoding:'utf8',windowsHide:true});if(p.status!==0)throw new Error('Private inspection failed');return JSON.parse(p.stdout);};
  const guard="require 'server/services/simulationRecovery.php';$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)exit(1);$db=getDB();if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')exit(2);";
  const rollback=(mode)=>{const r=spawnSync('C:\\xampp\\php\\php.exe',['tests/simulations_stage12.php',mode,privateDir],{cwd:ROOT,encoding:'utf8',windowsHide:true});check(r.status===0,'real_rollback_'+mode);};
  await navigate(PAGE+'index.html');await waitFor(()=>typeof fazerLogin==='function','login');await login(accounts[0]);
  await section('simulados');
  await waitFor(()=>state.simulados.length===2&&state.plannedSimulados.length===1,'two conventional catalogs and one planned exam');
  await screenshot('simulation-list');
  await click('#simulados-list button[onclick="iniciarSimulado('+fixture.classic_id+')"]');
  await waitFor(()=>!!state.simAtivo&&state.simQuestoes.length===2,'conventional resumed');
  check(await evaluate(()=>Object.keys(state.simRespostas).length>=1),'conventional_saved_progress_restored');
  if(!await evaluate(()=>state.simFinalizado)){
    const publicData=await evaluate(async id=>(await(await apiFetch(apiEndpoint('simulados',{action:'carregar',tentativa_id:id}))).json()),attemptIds.classic);
    check(publicData.gabarito===null&&publicData.questoes.every(q=>!('resposta_correta' in q)&&!('explicacao' in q)&&q.alternativas.every(a=>!('correta' in a)))&&Object.values(publicData.respostas).every(a=>!('acertou' in a)),'conventional_no_grading_before_finalization');
    await evaluate(()=>{state.simIdx=1;renderSimuladoDots();renderSimuladoQuestion();});
    await screenshot('conventional-pending',true,true);
    const questions=php(guard+'echo json_encode(examClassicCatalog($db,'+fixture.classic_id+')[1]);');
    const chosen=questions[1];
    if(!await evaluate(id=>!!state.simRespostas[id],chosen.id)){
      await click('#sim-q-options .option:nth-child('+('ABCDE'.indexOf(chosen.resposta_correta)+1)+')');
      await waitFor(()=>!state.simAnswerPending&&Object.keys(state.simRespostas).length===2,'conventional last saved');
    }
    check(await evaluate(()=>document.querySelector('#sim-q-explanation').classList.contains('hidden')),'conventional_explanation_still_hidden_after_answer');
    rollback('classic-finish');
    await evaluate(()=>{window.confirm=()=>true;});await click('#sim-btn-finish');
    await waitFor(()=>state.simFinalizado&&!state.simFinishing,'conventional final');
  }
  check(await evaluate(()=>document.querySelector('#sim-res-score').textContent==='1/2'&&document.querySelector('#sim-btn-finish').disabled),'conventional_correct_immutable_result');
  await evaluate(()=>document.querySelector('#simulado-result').scrollIntoView({block:'center',behavior:'instant'}));await screenshot('conventional-result',false);
  await evaluate(()=>document.querySelector('#sim-q-explanation').scrollIntoView({block:'center',behavior:'instant'}));await screenshot('conventional-answer-key',false);
  await section('plano');
  await waitFor(()=>document.querySelectorAll('#plan-content .task-card').length===4,'four plan tasks');
  await click('#plan-content button[onclick="abrirSimuladoDaTarefa('+fixture.task_id+')"]');
  await waitFor(()=>state.plannedSimAtivo?.questoes?.length===10,'planned resumed through plan task');
  const current=await evaluate(()=>state.plannedSimAtivo);
  if(current.status!=='finalizado'){
    check(current.questoes.every(q=>!('resposta_correta' in q)&&!('explicacao' in q))&&Object.values(current.respostas).every(a=>!('acertou' in a)&&!('avaliacao' in a)),'planned_no_grading_before_final');
    const internal=php(guard+"$s=$db->prepare('SELECT questoes_json FROM simulados_planejados WHERE id=?');$s->execute(["+attemptIds.planned+"]);echo $s->fetchColumn();").questoes;
    const last=internal[internal.length-1];
    await evaluate(()=>document.querySelector('#planned-sim-active .exercise-card:last-child').scrollIntoView({block:'center',behavior:'instant'}));
    await screenshot('planned-pending',false);
    if(!current.respostas[last.id]){
      await click('input[name="planned-sim-'+last.id+'"][value="'+last.resposta_correta+'"]');
      await click('#planned-sim-active button[data-sim-question="'+last.id+'"]');
      await waitFor(()=>!state.plannedAnswerPending&&Object.keys(state.plannedSimAtivo.respostas).length===10,'real planned string-key button saved');
    }
    check(await evaluate(()=>!document.querySelector('#planned-sim-active .explanation')),'planned_explanations_hidden_until_final');
    rollback('planned-finish');
    await evaluate(()=>{window.confirm=()=>true;});
    await click('#planned-sim-active button[onclick^="finalizarSimuladoPlanejado"]');
    await waitFor(()=>state.plannedSimAtivo?.status==='finalizado'&&!state.plannedFinishing,'planned final');
  }
  check(await evaluate(()=>state.plannedSimAtivo.resultado.acertos===9&&state.plannedSimAtivo.resultado.percentual===90),'planned_result_9_of_10');
  check(await evaluate(()=>[...document.querySelectorAll('#planned-sim-active [data-sim-question]')].every(b=>b.disabled)),'planned_answer_buttons_disabled_after_finish');
  await evaluate(()=>document.querySelector('#planned-sim-active .exercise-card:last-child .explanation').scrollIntoView({block:'center',behavior:'instant'}));await screenshot('planned-result-key',false);
  await section('plano');
  await waitFor(()=>document.querySelector('#plan-content').textContent.includes('50% concluido'),'two of four tasks completed');
  check(await evaluate(id=>state.plano.tarefas.find(t=>t.id===id).status==='concluida',fixture.task_id),'planned_task_complete');
  await screenshot('simulation-plan-result',true,true);
  await evaluate(()=>document.querySelector('.btn-logout').click());await waitFor(()=>location.pathname.endsWith('/index.html')&&typeof fazerLogin==='function','logout');
  await login(accounts[0]);await section('simulados');await waitFor(()=>state.simulados.length===2,'list reloaded');
  check(await evaluate(id=>state.simulados.find(s=>Number(s.id)===id).tentativas==1,fixture.classic_id),'conventional_history_indicator_persisted');
  await click('#simulados-list button[onclick="iniciarSimulado('+fixture.classic_id+')"]');
  await waitFor(()=>state.simFinalizado,'closed conventional reopened without new attempt');
  await click('#planned-sims-list button[onclick="iniciarSimuladoPlanejado('+attemptIds.planned+')"]');
  await waitFor(()=>state.plannedSimAtivo?.status==='finalizado','closed planned recovered after new session');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile_simulations_no_horizontal_overflow');
  await evaluate(()=>document.querySelector('#planned-sim-active .exercise-card:last-child').scrollIntoView({block:'center',behavior:'instant'}));await screenshot('simulation-mobile',false);
  check(runtimeErrors.length===0,'no_uncaught_page_exceptions');check(workerErrors.length===0,'no_worker_errors');
  check(!network.some(r=>/openrouter|cron_|diagnostico|action=gerar_semana|action=gerar_para_semana/.test(r.url)),'no_external_IA_cron_or_plan_generation');
  check(network.filter(r=>r.url.includes('/server/api/')).every(r=>!r.fromServiceWorker),'APIs_never_cached_by_worker');
  report = { result: 'PASS', browser: version.product, checks, screenshots, network, failures, runtimeErrors, workerErrors, real_browser: true, isolated_profile: true, visual_inspection_pending: true, new_plans: 0, new_accounts: 0, onboarding_saves: 0, external_AI_requests: 0, personal_browser_profile_used: false };
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
const output = path.join(privateDir, 'simulations-browser-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ result: report.result, phase: report.phase, error: report.error, checks: checks.length, screenshots: screenshots.length, report: output, real_browser: true, chrome_closed: chrome.exitCode !== null }));
