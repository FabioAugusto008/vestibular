// Existing frontend, simulated DOM and real sanitized contracts. No network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const {scenario}=require('./dashboard_initialization.test.js');
const fixture=JSON.parse(fs.readFileSync(process.argv[3],'utf8'));
const results=[];
async function setup(failure=null) {
 const r=await scenario('planned_fixture');
 const posts=[],requests=[],toasts=[];r.context.showToast=(...a)=>toasts.push(a);
 const original=r.context.fetch,query=r.context.document.querySelector.bind(r.context.document);
 r.context.document.querySelector=selector=>selector.startsWith('input[name="exercise-')?{value:'A'}:query(selector);
 r.context.fetch=async(url,options={})=>{
  if(!url.includes('exercicios-ia.php'))return original(url,options);
  requests.push({url,method:options.method||'GET'});
  if(options.method==='POST')posts.push({url,csrf:options.headers.has('X-CSRF-Token')});
  if(failure==='network')throw new TypeError('Network interrupted');
  if(failure==='abort')throw new DOMException('Interrupted','AbortError');
  if(failure==='json')return new Response('invalid JSON');
  if(failure==='empty')return new Response('');
  if(typeof failure==='number')return new Response(JSON.stringify({erro:'Controlled failure'}),{status:failure});
  return new Response(JSON.stringify(url.includes('action=responder')?{ok:true,acertou:true,avaliacao:{explicacao:'Feedback'}}:fixture.main));
 };
 r.context.lotFixture=fixture;
 vm.runInContext('state.currentExerciseTaskId=lotFixture.main.lote.tarefa_id;state.currentExerciseLote=null;',r.context);
 return {...r,posts,requests,toasts};
}
async function test(name,fn){await fn();results.push({test:name,result:'PASS'});}
(async()=>{
 await test('GET_recovers_existing_lot_without_POST',async()=>{
  const r=await setup();await vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,false)',r.context);
  assert.equal(r.posts.length,0);assert.match(r.nodes.get('exercises-content').innerHTML,/Resposta registrada/);
  assert.match(r.nodes.get('exercises-content').innerHTML,/disabled/);
  assert.match(r.nodes.get('exercises-status').textContent,/Tarefa concluida/);
 });
 await test('empty_GET_offers_explicit_preparation_only',async()=>{
  const r=await setup();r.context.fetch=async()=>new Response(JSON.stringify({ok:true,lote:null}));
  await vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,false)',r.context);
  assert.match(r.nodes.get('exercises-content').innerHTML,/Preparar exercicios/);assert.equal(r.posts.length,0);
 });
 await test('explicit_preparation_double_click_is_CSRF_guarded',async()=>{
  const r=await setup();await Promise.all([vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,true)',r.context),vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,true)',r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);assert.match(r.posts[0].url,/gerar_por_tarefa/);
 });
 for(const failure of [401,403,500,'json','network','abort','empty'])await test('load_failure_'+failure+'_is_visible_without_false_success',async()=>{
  const r=await setup(failure);await vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,false)',r.context);
  assert.equal(vm.runInContext('state.currentExerciseLote',r.context),null);
  assert.ok(r.nodes.get('exercises-content').innerHTML.length>0);
 });
 await test('planned_answer_double_click_sends_one_POST_with_CSRF',async()=>{
  const r=await setup();await Promise.all([vm.runInContext("responderExercicio(2,'q1','multipla_escolha')",r.context),vm.runInContext("responderExercicio(2,'q1','multipla_escolha')",r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);assert.equal(vm.runInContext('state.exerciseAnswerPending',r.context),false);
 });
 for(const failure of [401,419,409,500,'json','network','abort','empty'])await test('answer_failure_'+failure+'_does_not_claim_saved',async()=>{
  const r=await setup(failure);await vm.runInContext("responderExercicio(2,'q1','multipla_escolha')",r.context);
  assert.equal(vm.runInContext('state.exerciseAnswerPending',r.context),false);
  assert.equal(r.toasts.length,1);assert.equal(r.toasts[0][0],'error');
 });
 await test('partial_lot_does_not_claim_task_complete',async()=>{
  const r=await setup();vm.runInContext('state.currentExerciseLote=lotFixture.partial.lote;renderExercises()',r.context);
  assert.match(r.nodes.get('exercises-status').textContent,/Tarefa em andamento/);
  assert.match(r.nodes.get('exercises-status').textContent,/1 de 5/);
 });
 await test('late_response_cannot_replace_other_selected_task',async()=>{
  const r=await setup();let release;
  r.context.fetch=async()=>{await new Promise(resolve=>{release=resolve});return new Response(JSON.stringify(fixture.main));};
  const first=vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,false)',r.context);
  await new Promise(resolve=>setImmediate(resolve));
  vm.runInContext('state.currentExerciseTaskId=lotFixture.partial.lote.tarefa_id',r.context);
  release();await first;
  assert.equal(vm.runInContext('state.currentExerciseLote',r.context),null);
 });
 await test('late_failure_cannot_hide_other_selected_task',async()=>{
  const r=await setup();let release;
  r.context.fetch=async()=>{await new Promise(resolve=>{release=resolve});return new Response(JSON.stringify({erro:'Old task failed'}),{status:500});};
  const first=vm.runInContext('loadExercisesForTask(state.currentExerciseTaskId,false)',r.context);
  await new Promise(resolve=>setImmediate(resolve));
  vm.runInContext('state.currentExerciseTaskId=lotFixture.partial.lote.tarefa_id;state.currentExerciseLote=lotFixture.partial.lote;renderExercises()',r.context);
  const html=r.nodes.get('exercises-content').innerHTML;
  release();await first;
  assert.equal(r.nodes.get('exercises-content').innerHTML,html);
 });
 console.log(JSON.stringify({result:'PASS',scenarios:results.length,results,real_network_requests:0,simulated_DOM:true}));
})().catch(e=>{console.error(e.message);process.exitCode=1});
