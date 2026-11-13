// Real frontend functions in a simulated DOM; no HTTP or database writes.
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {scenario}=require('./dashboard_initialization.test.js');
const results=[];
async function setup(failure=null,target='answer') {
 const r=await scenario('stage10_fixture');
 r.context.FormData=FormData;r.context.confirm=()=>true;
 const calls=[],toasts=[];
 r.context.showToast=(...args)=>toasts.push(args);
 const secondary=r.context.fetch;
 r.context.fetch=async(url,options={})=>{
  if((options.method||'GET')==='GET')return secondary(url,options);
  calls.push({url,csrf:options.headers.has('X-CSRF-Token')});
  if(failure==='network')throw new TypeError('Failed to fetch');
  if(failure==='json')return new Response('invalid JSON');
  if(failure==='empty')return new Response('');
  if(typeof failure==='number')return new Response(JSON.stringify({erro:'Controlled failure'}),{status:failure});
  return new Response(JSON.stringify(target==='finish'?{ok:true,total:1,acertos:1,erros:0,tempo_seg:33,gabarito:{42:{correta:'B',explicacao:'Feedback'}}}:{ok:true,acertou:true,correta:'B',explicacao:'Feedback'}));
 };
 vm.runInContext("state.questoes=[{id:42,materia:'Portugues',enunciado:'Questao',alternativa_a:'A',alternativa_b:'B',alternativa_c:'C',alternativa_d:'D',alternativa_e:'E'}];state.respostas={};state.gabarito=null;state.finalizado=false;state.revisaoQuestoes=state.questoes;state.revisaoRespostas={};",r.context);
 return {...r,posts:calls,toasts};
}
async function test(name,fn){await fn();results.push({test:name,result:'PASS'});}
(async()=>{
 await test('explicit_preparation_double_click_has_CSRF_and_no_boot_write',async()=>{
  const r=await setup();
  await Promise.all([vm.runInContext('prepararQuestoes()',r.context),vm.runInContext('prepararQuestoes()',r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);assert.match(r.posts[0].url,/action=preparar/);
  assert.equal(vm.runInContext('state.preparingQuestions',r.context),false);
 });
 for(const failure of [419,500,'json','network','empty'])await test('preparation_failure_'+failure+'_is_visible',async()=>{
  const r=await setup(failure);await vm.runInContext('prepararQuestoes()',r.context);
  assert.equal(vm.runInContext('state.preparingQuestions',r.context),false);assert.equal(r.toasts.length,1);
 });
 await test('answer_double_click_sends_one_CSRF_request_and_immediate_feedback',async()=>{
  const r=await setup();
  await Promise.all([vm.runInContext("responder(42,'B')",r.context),vm.runInContext("responder(42,'B')",r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);assert.match(r.posts[0].url,/action=responder/);
  assert.equal(vm.runInContext('state.currentIdx',r.context),0);
  assert.equal(vm.runInContext('state.respostas[42].acertou',r.context),true);
  assert.ok(r.nodes.get('q-explanation').classList.contains('visible'));
  assert.match(r.nodes.get('q-explanation').innerHTML,/Feedback/);
 });
 for(const failure of [401,403,419,409,500,'json','network','empty']) {
  await test('answer_failure_'+failure+'_does_not_create_false_result',async()=>{
   const r=await setup(failure);await vm.runInContext("responder(42,'B')",r.context);
   assert.equal(vm.runInContext('Object.keys(state.respostas).length',r.context),0);
   assert.equal(vm.runInContext('state.answerPending',r.context),false);
   assert.equal(r.toasts.length,1);
   if(failure===401)assert.equal(r.context.location.href,'index.html');
  });
 }
 await test('finish_double_click_preserves_server_time_and_final_state',async()=>{
  const r=await setup(null,'finish');
  vm.runInContext("state.respostas={42:{marcada:'B',acertou:true}}",r.context);
  await Promise.all([vm.runInContext('finalizarDia()',r.context),vm.runInContext('finalizarDia()',r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);
  assert.equal(vm.runInContext('state.finalizado',r.context),true);
  assert.equal(r.nodes.get('res-tempo').textContent,'00:33');
 });
 for(const failure of [419,500,'json','network','empty']) {
  await test('finish_failure_'+failure+'_does_not_claim_completion',async()=>{
   const r=await setup(failure,'finish');await vm.runInContext('finalizarDia()',r.context);
   assert.equal(vm.runInContext('state.finalizado',r.context),false);
   assert.equal(vm.runInContext('state.finishingQuestions',r.context),false);
   assert.equal(r.toasts.length,1);
  });
 }
 await test('review_response_double_click_is_guarded',async()=>{
  const r=await setup();await Promise.all([vm.runInContext("responderRevisao(42,'B')",r.context),vm.runInContext("responderRevisao(42,'B')",r.context)]);
  assert.equal(r.posts.length,1);assert.ok(r.posts[0].csrf);
  assert.equal(vm.runInContext('state.revisaoRespostas[42].acertou',r.context),true);
  assert.equal(vm.runInContext('Object.keys(state.respostas).length',r.context),0);
 });
 for(const failure of [401,419,409,500,'json','network','empty']) {
  await test('review_failure_'+failure+'_keeps_daily_state',async()=>{
   const r=await setup(failure);await vm.runInContext("responderRevisao(42,'B')",r.context);
   assert.equal(vm.runInContext('Object.keys(state.revisaoRespostas).length',r.context),0);
   assert.equal(vm.runInContext('state.reviewAnswerPending',r.context),false);
   assert.equal(r.toasts.length,1);
  });
 }
 console.log(JSON.stringify({result:'PASS',scenarios:results.length,results,simulated_DOM:true,real_network_requests:0}));
})().catch(e=>{console.error(e.message);process.exitCode=1;});
