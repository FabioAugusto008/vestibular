// Simulation UI regressions; real frontend in VM, no network.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const {scenario}=require('./dashboard_initialization.test.js');
const data=JSON.parse(fs.readFileSync(process.argv[3],'utf8')),results=[];
async function setup(failure=null){
 const r=await scenario('simulations_fixture');
 const calls=[],messages=[],buttons=[];
 r.context.FormData=FormData;r.context.confirm=()=>true;r.context.showToast=(...a)=>messages.push(a);
 r.nodes.get('planned-sim-active').querySelectorAll=()=>buttons;
 const query=r.context.document.querySelector.bind(r.context.document);
 r.context.document.querySelector=s=>s.startsWith('input[name="planned-sim-')?{value:'A'}:query(s);
 const oldfetch=r.context.fetch;
 r.context.fetch=async(url,o={})=>{
  if(!url.includes('simulados'))return oldfetch(url,o);
  calls.push({url,method:o.method||'GET',csrf:o.headers?.has('X-CSRF-Token')});
  if(failure==='network')throw new TypeError('Network unavailable');
  if(failure==='abort')throw new DOMException('Interrupted','AbortError');
  if(failure==='json')return new Response('{bad');
  if(failure==='empty')return new Response('');
  if(typeof failure==='number')return new Response(JSON.stringify({erro:'Controlled failure'}),{status:failure});
  let body;
  if(url.includes('simulados-planejados')){
   body=url.includes('action=listar')?{ok:true,simulados:[data.planned.simulado]}:url.includes('action=finalizar')?{ok:true,simulado:data.planned.simulado,resultado:data.planned.simulado.resultado}:url.includes('action=responder')?{ok:true}:data.planned;
  }else body=url.includes('action=finalizar')?{ok:true,...data.classic.resultado,tempo_seg:25}:url.includes('action=responder')?{ok:true}:data.classic;
  return new Response(JSON.stringify(body));
 };
 r.context.examFixture=data;
 vm.runInContext("state.simAtivo={tentativa_id:examFixture.classic.tentativa_id};state.simQuestoes=examFixture.classic.questoes;state.simRespostas={};state.simFinalizado=false;state.plannedSimAtivo=JSON.parse(JSON.stringify(examFixture.planned.simulado));state.plannedSimAtivo.status='iniciado';state.plannedSimAtivo.resultado=null;state.plannedSimAtivo.respostas={};",r.context);
 return{...r,calls,messages,buttons};
}
async function test(name,fn){await fn();results.push({test:name,result:'PASS'});}
(async()=>{
 await test('string_question_key_uses_DOM_listener_not_inline_JS',async()=>{
  const r=await setup();let bound,received;
  const key='q1" onclick="globalThis.injected=true';
  r.buttons.push({dataset:{simQuestion:key},addEventListener:(name,fn)=>{assert.equal(name,'click');bound=fn;}});
  vm.runInContext('renderPlannedSimActive()',r.context);
  assert.ok(!r.nodes.get('planned-sim-active').innerHTML.includes('onclick="responderSimuladoPlanejado'));
  r.context.responderSimuladoPlanejado=(id,value)=>{received=value;};bound();
  assert.equal(received,key);assert.equal(r.context.injected,undefined);
 });
 await test('saved_answers_do_not_render_correction_before_finalization',async()=>{
  const r=await setup();vm.runInContext('state.plannedSimAtivo.respostas=examFixture.planned.simulado.respostas;renderPlannedSimActive()',r.context);
  assert.ok(!r.nodes.get('planned-sim-active').innerHTML.includes('explanation'));
 });
 for(const kind of ['classic-answer','classic-finish','planned-answer','planned-finish']){
  for(const failure of [401,403,419,409,500,'json','network','abort','empty'])await test(kind+'_failure_'+failure,async()=>{
   const r=await setup(failure);
   const expression=kind==='classic-answer'?"responderSimulado(state.simQuestoes[0].id,'A')":kind==='classic-finish'?'finalizarSimulado()':kind==='planned-answer'?"responderSimuladoPlanejado(state.plannedSimAtivo.id,state.plannedSimAtivo.questoes[0].id)":'finalizarSimuladoPlanejado(state.plannedSimAtivo.id)';
   await vm.runInContext(expression,r.context);
   assert.equal(r.messages.length,1);assert.equal(r.messages[0][0],'error');
   assert.equal(vm.runInContext('state.simFinalizado',r.context),false);
   assert.equal(vm.runInContext('state.plannedSimAtivo.status',r.context),'iniciado');
  });
 }
 await test('classic_double_click_has_one_CSRF_POST',async()=>{
  const r=await setup();await Promise.all([vm.runInContext("responderSimulado(state.simQuestoes[0].id,'A')",r.context),vm.runInContext("responderSimulado(state.simQuestoes[0].id,'A')",r.context)]);
  assert.equal(r.calls.filter(x=>x.method==='POST').length,1);assert.ok(r.calls[0].csrf);
 });
 await test('planned_double_click_has_one_CSRF_POST',async()=>{
  const r=await setup();const expr="responderSimuladoPlanejado(state.plannedSimAtivo.id,state.plannedSimAtivo.questoes[0].id)";
  await Promise.all([vm.runInContext(expr,r.context),vm.runInContext(expr,r.context)]);
  assert.equal(r.calls.filter(x=>x.method==='POST').length,1);assert.ok(r.calls[0].csrf);
 });
 await test('closed_planned_renders_disabled_buttons_and_feedback',async()=>{
  const r=await setup();vm.runInContext('state.plannedSimAtivo=examFixture.planned.simulado;renderPlannedSimActive()',r.context);
  assert.ok(r.nodes.get('planned-sim-active').innerHTML.includes('disabled'));
  assert.ok(r.nodes.get('planned-sim-active').innerHTML.includes('explanation visible'));
  await vm.runInContext("responderSimuladoPlanejado(state.plannedSimAtivo.id,state.plannedSimAtivo.questoes[0].id)",r.context);
  assert.equal(r.calls.length,0);
 });
 await test('known_finalized_planned_record_overrides_future_task_prediction',async()=>{
  const r=await setup();
  const markup=vm.runInContext("state.plannedSimulados=[examFixture.planned.simulado];taskCardHtml({id:examFixture.planned.simulado.tarefa_id,tipo:'simulado',data_prevista:'2099-01-01',status:'concluida',titulo:'Task'},true)",r.context);
  assert.match(markup,/onclick=\"abrirSimuladoDaTarefa/);
  assert.match(markup,/Ver resultado/);
 });
 await test('reopened_closed_conventional_stops_old_timer',async()=>{
  const r=await setup();vm.runInContext('state.simTimer=123',r.context);
  await vm.runInContext('iniciarSimulado(1)',r.context);
  assert.equal(vm.runInContext('state.simTimer',r.context),null);
 });
 await test('planned_card_displays_in_progress_state',async()=>{
  const r=await setup();assert.match(vm.runInContext('plannedSimCardHtml(state.plannedSimAtivo)',r.context),/Em andamento/);
 });
 for(const failure of [500,'json','network','empty'])await test('catalog_failure_'+failure+'_is_visible',async()=>{
  const r=await setup(failure);vm.runInContext('state.simAtivo=null',r.context);await vm.runInContext('loadSimulados()',r.context);
  assert.ok(r.nodes.get('simulados-list').innerHTML.includes('empty-state'));
 });
 await test('closed_task_can_reopen_result_before_list_loaded',async()=>{
  const r=await setup();const markup=vm.runInContext("state.plannedSimulados=[];taskCardHtml({id:99,tipo:'simulado',data_prevista:'2099-01-01',status:'concluida',titulo:'Task'},true)",r.context);
  assert.ok(markup.includes('abrirSimuladoDaTarefa(99)'));assert.match(markup,/Ver resultado/);
 });
 console.log(JSON.stringify({result:'PASS',scenarios:results.length,results,real_network_requests:0,simulated_DOM:true}));
})().catch(e=>{console.error(e.message);process.exitCode=1});
