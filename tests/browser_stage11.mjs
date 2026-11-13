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

  phase='planned-practice';
  const fixture=JSON.parse(fs.readFileSync(path.join(privateDir,'planned_fixture.private.json'),'utf8'));
  const mainId=fixture.tasks.main.task_id, partialId=fixture.tasks.partial.task_id;
  await navigate(PAGE+'index.html');
  await waitFor(()=>typeof fazerLogin==='function','login');
  await login(accounts[0]);
  await section('plano');
  await waitFor(count=>document.querySelectorAll('#plan-content .task-card').length===count,'real plan tasks',15000,process.argv.includes('--simulations-enabled')?4:3);
  check(await evaluate(()=>state.plano.id===2),'original_plan_reused_without_generation');
  await screenshot('plan-before',true,true);
  await click('#plan-content button[onclick="abrirExerciciosTarefa('+mainId+')"]');
  await waitFor(()=>state.currentExerciseLote?.quantidade===5,'existing five-question lot');
  const lotId=await evaluate(()=>state.currentExerciseLote.id);
  const pendingQuestions=await evaluate(()=>state.currentExerciseLote.exercicios.filter(q=>!state.currentExerciseLote.respostas[q.id]));
  check(pendingQuestions.every(q=>!('resposta_correta' in q)&&!('explicacao' in q)),'no_answer_key_before_pending_planned_attempt');
  await click('#sec-exercicios button[onclick="regenerarExerciciosTarefa()"]');
  await waitFor(()=>!state.preparingExercise&&state.currentExerciseLote?.quantidade===5,'explicit reopen');
  check(await evaluate(id=>state.currentExerciseLote.id===id,lotId),'explicit_reopen_keeps_same_lot');
  await evaluate(()=>document.querySelector('#exercises-content .exercise-card:last-child').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('planned-pending-last',false);
  if(pendingQuestions.length){
    check(pendingQuestions.length===1,'only_one_remaining_response');
    const dbSource="require 'server/services/plannedExercises.php';$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)exit(1);$db=getDB();if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')exit(2);$s=$db->prepare('SELECT exercicios_json FROM exercicios_planejados WHERE id=?');$s->execute(["+lotId+"]);echo $s->fetchColumn();";
    const local=spawnSync('C:\\xampp\\php\\php.exe',['-d','display_errors=0','-d','log_errors=0','-r',dbSource],{cwd:ROOT,encoding:'utf8',windowsHide:true});
    if(local.status!==0)throw new Error('Private fixture guard failed');
    const item=JSON.parse(local.stdout).exercicios.find(q=>q.id===pendingQuestions[0].id);
    await click('input[name="exercise-'+item.id+'"][value="'+item.resposta_correta+'"]');
    await click('#exercises-content .exercise-card:last-child button');
    await waitFor(()=>!state.exerciseAnswerPending&&state.currentExerciseLote?.status==='concluido','atomic planned completion');
  }
  check(await evaluate(()=>state.currentExerciseLote.resumo.respondidas===5&&state.currentExerciseLote.resumo.acertos===4&&state.currentExerciseLote.resumo.erros===1&&state.currentExerciseLote.resumo.tarefa_concluida),'planned_result_and_task_complete');
  check(await evaluate(()=>[...document.querySelectorAll('#exercises-content input[type=radio]')].every(e=>e.disabled)),'answered_alternatives_locked');
  await evaluate(()=>document.querySelector('#exercises-content .exercise-card:last-child .explanation').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('planned-feedback',false);
  await section('plano');
  await waitFor(text=>document.querySelector('#plan-content').textContent.includes(text),'plan progress',15000,process.argv.includes('--simulations-enabled')?'50% concluido':'33% concluido');
  check(await evaluate(id=>state.plano.tarefas.find(t=>t.id===id).status==='concluida',mainId),'plan_task_reflects_completion');
  await screenshot('plan-completed',true,true);
  await click('#plan-content button[onclick="abrirExerciciosTarefa('+partialId+')"]');
  await waitFor(()=>state.currentExerciseLote?.quantidade===1,'reduced lot loaded');
  check(await evaluate(()=>state.currentExerciseLote.insuficiente&&!state.currentExerciseLote.resumo.tarefa_concluida&&document.querySelector('#exercises-status').textContent.includes('1 de 5')),'insufficiency_visible_without_false_completion');
  await screenshot('partial-warning',true,true);
  const integrity=await evaluate(async()=>{
    const daily=await(await apiFetch(apiEndpoint('estatisticas','action=geral'))).json();
    const planned=await(await apiFetch(apiEndpoint('estatisticas','action=estudai_geral'))).json();
    const reviews=await(await apiFetch(apiEndpoint('revisao','action=estatisticas'))).json();
    return{daily:daily.geral,planned:planned.exercicios,reviews:reviews.total};
  });
  check(integrity.daily.total_questoes===2&&integrity.daily.total_acertos===1&&integrity.reviews===1,'daily_and_review_results_preserved');
  check(integrity.planned.respondidos===6&&integrity.planned.acertos===5,'planned_statistics_separate');
  await evaluate(()=>document.querySelector('.btn-logout').click());
  await waitFor(()=>location.pathname.endsWith('/index.html')&&typeof fazerLogin==='function','logout A');
  await login(accounts[1]);
  await evaluate(id=>abrirExerciciosTarefa(id),mainId);
  await waitFor(()=>document.querySelector('#exercises-content').textContent.includes('indisponivel'),'foreign task error');
  check(await evaluate(()=>state.currentExerciseLote===null),'foreign_task_cannot_display_previous_lot');
  await screenshot('foreign-task-denied');
  await evaluate(()=>document.querySelector('.btn-logout').click());
  await waitFor(()=>location.pathname.endsWith('/index.html')&&typeof fazerLogin==='function','logout B');
  await login(accounts[0]);
  await section('plano');
  await click('#plan-content button[onclick="abrirExerciciosTarefa('+mainId+')"]');
  await waitFor(()=>state.currentExerciseLote?.resumo.respondidas===5,'new session reloaded all answers');
  check(await evaluate(()=>state.currentExerciseLote.status==='concluido'&&state.currentExerciseLote.resumo.tarefa_concluida),'result_recovers_after_new_authenticated_session');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile_no_horizontal_overflow');
  await evaluate(()=>document.querySelector('#exercises-content .exercise-card:last-child').scrollIntoView({block:'center',behavior:'instant'}));
  await screenshot('planned-mobile',false);
  check(runtimeErrors.length===0,'no_uncaught_exceptions');
  check(workerErrors.length===0,'no_worker_errors');
  check(!network.some(r=>/openrouter|cron_|diagnostico|simulados|action=gerar_semana/.test(r.url)),'no_external_IA_cron_simulation_or_plan_generation');
  check(network.filter(r=>r.url.includes('/server/api/')).every(r=>!r.fromServiceWorker),'backend_calls_bypass_Service_Worker');
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
const output = path.join(privateDir, 'planned-browser-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ result: report.result, phase: report.phase, error: report.error, checks: checks.length, screenshots: screenshots.length, report: output, real_browser: true, chrome_closed: chrome.exitCode !== null }));
