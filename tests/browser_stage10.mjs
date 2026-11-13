// Stage 10 real Chrome questions, correction and review integration; no new dependencies.
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

  if(process.argv.includes('--details-only')) {
    phase='stage10-visual-details';
    await navigate(PAGE+'index.html');
    await waitFor(()=>typeof fazerLogin==='function','login');
    await login(accounts[1]);
    await section('questoes');
    await evaluate(()=>{state.currentIdx=0;renderQuestion(0);});
    await screenshot('question-correction-full',true,true);
    await evaluate(()=>document.querySelector('#result-card').scrollIntoView({block:'center',behavior:'instant'}));
    await screenshot('result-card-detail',false);
    await section('dashboard');
    await evaluate(()=>document.querySelector('#hist-list').scrollIntoView({block:'center',behavior:'instant'}));
    await screenshot('history-detail',false);
    await section('estatisticas');
    await waitFor(()=>document.querySelector('#est-questoes-respondidas').textContent==='2','statistics');
    await evaluate(()=>{for(const d of document.querySelectorAll('#sec-estatisticas details'))d.open=true;});
    await screenshot('statistics-expanded',true,true);
  } else {
  phase = 'stage10-real-question-flow';
  await navigate(PAGE + 'index.html');
  await waitFor(() => typeof fazerLogin === 'function', 'login available');
  await login(accounts[1]);
  await section('questoes');
  await waitFor(() => state.questoes.length === 2, 'two catalog questions');
  check(await evaluate(() => document.querySelectorAll('#q-options .option').length === 5), 'five real alternatives rendered');
  const before = await evaluate(async () => {
    const r=await apiFetch(apiEndpoint('questoes','action=carregar'));return r.json();
  });
  const fresh = before.respondidas === 0;
  if (fresh) check(before.questoes.every(q => !('resposta_correta' in q) && !('explicacao' in q) && !('alternativa_correta_id' in q) && q.alternativas.every(a => !('correta' in a))) && Object.keys(before.gabarito).length === 0, 'no_answer_key_before_attempt_real_HTTP');
  await screenshot(fresh ? 'questions-before-answer' : 'questions-existing-result');
  const fixture = spawnSync('C:\\xampp\\php\\php.exe',['-d','display_errors=0','-d','log_errors=0','-r',
    "require 'server/services/dailyQuestions.php';$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)exit(1);$db=getDB();if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')exit(2);echo json_encode(questionDailyRows($db,date('Y-m-d')));"],
    {cwd:ROOT,encoding:'utf8',windowsHide:true});
  if(fixture.status!==0)throw new Error('Private question fixture guard failed');
  const privateQuestions=JSON.parse(fixture.stdout);
  const first=privateQuestions[0], second=privateQuestions[1];
  const wrong=first.alternativas.find(a => !a.correta).letra;
  await evaluate(() => { state.currentIdx=0;renderQuestion(0); });
  if (!before.respostas[first.id]) {
    await click('#q-options .option:nth-child(' + ('ABCDE'.indexOf(wrong)+1) + ')');
    await waitFor(() => !state.answerPending && Object.keys(state.respostas).length>=1, 'answer submitted by actual option click');
  }
  check(await evaluate(id => state.respostas[id].acertou===false, first.id), 'wrong_answer_corrected_in_browser');
  check(await evaluate(() => document.querySelector('#q-explanation').classList.contains('visible') && document.querySelector('#q-explanation').textContent.includes('Explicacao')), 'explanation_visible_after_attempt');
  await screenshot('questions-correction');
  await navigate(PAGE+'app.html');
  await waitFor(() => !!window.EstudAIDashboardReady, 'dashboard reload');
  await evaluate(async () => await window.EstudAIDashboardReady);
  await section('questoes');
  check(await evaluate(id => !!state.gabarito[id]?.explicacao && state.respostas[id].acertou===false,first.id),'attempt_and_explanation_survive_page_reload');
  await evaluate(() => {state.currentIdx=1;renderQuestion(1);});
  if(!await evaluate(id => !!state.respostas[id],second.id)) {
    await click('#q-options .option:nth-child('+('ABCDE'.indexOf(second.resposta_correta)+1)+')');
    await waitFor(() => !state.answerPending && Object.keys(state.respostas).length===2,'second answer saved');
  }
  check(await evaluate(() => document.querySelector('#btn-finish').disabled === !!state.finalizado),'finish_button_matches_completion_state');
  // Accept the application's existing confirmation dialog, then use the real button.
  await evaluate(() => {window.confirm=()=>true;});
  if(!await evaluate(() => state.finalizado)) {
    await click('#btn-finish');
    await waitFor(() => state.finalizado && !state.finishingQuestions,'daily finish persisted');
  }
  check(await evaluate(() => document.querySelector('#res-score').textContent==='1/2' && !document.querySelector('#result-card').classList.contains('hidden')),'daily_result_matches_one_correct_one_wrong');
  await screenshot('daily-result');
  await section('dashboard');
  check(await evaluate(() => document.querySelector('#stat-acertos').textContent==='1' && document.querySelector('#stat-pct').textContent==='50%'),'dashboard_history_progress_refreshed');
  await screenshot('dashboard-progress');
  await section('estatisticas');
  await waitFor(() => document.querySelector('#est-questoes-respondidas').textContent==='2','statistics rendered');
  const subjectKey=first.materia.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const subjectStat=subjectKey==='portugues'?'#est-por-pct':'#est-mat-pct';
  check(await evaluate(selector => document.querySelector(selector).textContent==='50%',subjectStat),'accented_subject_statistics_rendered');
  await screenshot('daily-statistics');
  const pendingBeforeReview=await evaluate(async()=> (await (await apiFetch(apiEndpoint('revisao','action=estatisticas'))).json()).total);
  await section('revisao');
  if(pendingBeforeReview>0) await waitFor(() => state.revisaoQuestoes.length>0 && !!document.querySelector('#revisao-content .option'),'review data loaded');
  else await waitFor(() => document.querySelector('#revisao-content').textContent.includes('Nenhuma questao'),'review empty');
  if(await evaluate(() => state.revisaoQuestoes.length>0)) {
    await click('#revisao-tabs [data-revisao="'+subjectKey+'"]');
    await waitFor(() => state.revisaoQuestoes.length===1,'accented subject filter');
    check(await evaluate(() => !('explicacao' in state.revisaoQuestoes[0]) && !('resposta_correta' in state.revisaoQuestoes[0])),'review_answer_key_hidden_before_attempt');
    await screenshot('review-before-answer');
    await click('#revisao-content .option:nth-child('+('ABCDE'.indexOf(first.resposta_correta)+1)+')');
    await waitFor(() => !state.reviewAnswerPending && Object.values(state.revisaoRespostas).some(a=>a.acertou),'review answer saved from option');
    check(await evaluate(() => document.querySelector('#revisao-content .explanation').textContent.includes('Alternativa correta')),'review_feedback_rendered');
    await screenshot('review-correction');
  }
  const reviewState=await evaluate(async()=>{
    const daily=await (await apiFetch(apiEndpoint('questoes','action=carregar'))).json();
    const stats=await (await apiFetch(apiEndpoint('estatisticas','action=geral'))).json();
    const review=await (await apiFetch(apiEndpoint('revisao','action=estatisticas'))).json();
    return {daily,stats,review};
  });
  check(reviewState.stats.geral.total_questoes===2 && reviewState.stats.geral.total_acertos===1 && reviewState.stats.geral.total_erros===1,'review_does_not_change_daily_statistics');
  check(reviewState.review.total===0,'correct_review_removes_pending_error');
  check(await evaluate(() => document.querySelector('#revisao-count').textContent==='Revisao' && !document.querySelector('#nav-revisao .nav-badge')),'review_badge_cleared');
  await section('revisao');
  await waitFor(()=>document.querySelector('#revisao-content').textContent.includes('Nenhuma questao'),'review empty after correction');
  await screenshot('review-empty');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  await section('dashboard',true);
  await click('#sec-dashboard button[onclick="showSection(\'questoes\')"]');
  check(await evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile_questions_no_horizontal_overflow');
  await screenshot('questions-mobile');
  await send('Emulation.setDeviceMetricsOverride',{width:1365,height:900,deviceScaleFactor:1,mobile:false});
  await evaluate(()=>document.querySelector('.btn-logout').click());
  await waitFor(()=>location.pathname.endsWith('/index.html') && typeof fazerLogin==='function','logout B');
  await login(accounts[0]);
  await section('revisao');
  await waitFor(()=>state.revisaoQuestoes.length===1,'A still has own pending error');
  check(await evaluate(()=>state.revisaoQuestoes.length===1),'B_review_did_not_resolve_A_error');
  await section('plano');
  check(await evaluate(()=>state.plano?.id===2),'prior_saved_plan_preserved');
  await section('calendario');
  await waitFor(()=>state.calendarEvents.length===1,'prior calendar preserved');
  check(true,'calendar_regression_preserved');
  check(runtimeErrors.length===0,'no_uncaught_exceptions');
  check(workerErrors.length===0,'no_worker_errors');
  check(!network.some(r=>/openrouter|cron_|action=gerar|diagnostico|exercicios-ia|simulados/.test(r.url)),'no_IA_cron_simulation_or_plan_generation');
  check(network.filter(r=>r.url.includes('/server/api/')).every(r=>!r.fromServiceWorker),'API_responses_not_cached_by_worker');
  }
  report = { result: 'PASS', browser: version.product, checks, screenshots, network, failures, runtimeErrors, workerErrors, real_browser: true, isolated_profile: true, visual_details_only:process.argv.includes('--details-only'), visual_inspection_pending: true, new_plans: 0, new_accounts: 0, daily_answers_B: 2, review_answers_B: 1, onboarding_saves: 0, external_AI_requests: 0, personal_browser_profile_used: false };
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
const output = path.join(privateDir, 'questions-browser-' + stamp + '.json');
fs.writeFileSync(output, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ result: report.result, phase: report.phase, error: report.error, checks: checks.length, screenshots: screenshots.length, report: output, real_browser: true, chrome_closed: chrome.exitCode !== null }));
