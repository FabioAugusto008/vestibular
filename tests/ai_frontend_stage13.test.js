const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { scenario } = require('./dashboard_initialization.test.js');
const actual = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
const planning = JSON.parse(fs.readFileSync(process.argv[4], 'utf8'));
const diagnostic = {
  perfil_resumido: 'Perfil ficticio com uma hora na segunda-feira.',
  principais_dificuldades: ['Porcentagem'], materias_prioritarias: ['Matematica'],
  estrategia_recomendada: 'Revisar conceitos.', rotina_sugerida: 'Estudar no horario informado.',
  estrategia_revisao: 'Rever erros.', proximos_passos: ['Revisar porcentagem']
};
const results = [];
async function setup(name, options = {}) {
  const result = await scenario(name, {
    failures: options.failures,
    changeFixture(data) {
      Object.assign(data, actual);
      data['diagnostico.php?action=gerar'] = { ok: true, origem: 'ia', diagnostico: diagnostic, persistido: true };
      data['diagnostico.php?action=carregar_ultimo'] = { ok: true, resultado: { origem: 'ia', diagnostico: diagnostic } };
      data['redacao.php?action=salvar'] = { ok: true, persistido: true, redacao_id: 1 };
      data['plano-estudos.php?action=gerar_semana'] = planning['plano-estudos.php?action=gerar_semana'];
      if (options.changeFixture) options.changeFixture(data);
    }
  });
  result.nodes.get('redacao-tema').value = 'Tema ficticio';
  result.nodes.get('redacao-texto').value = 'Texto ficticio sobre educacao. '.repeat(30);
  return result;
}
function posts(result) { return result.calls.filter(call => call.method === 'POST'); }
function pass(name) { results.push({ test: name, result: 'PASS' }); }
async function main() {
  const normal = await setup('diagnostic_success');
  assert.match(normal.nodes.get('diagnostic-content').innerHTML, /Perfil ficticio/);
  assert.equal(posts(normal).length, 0);
  pass('saved_diagnostic_loaded_during_read_only_initialization');
  await vm.runInContext('gerarDiagnostico()', normal.context);
  assert.match(normal.nodes.get('diagnostic-content').innerHTML, /Perfil ficticio/);
  assert.equal(posts(normal).length, 1);
  assert.equal(posts(normal)[0].hasCSRF, true);
  assert.equal(normal.nodes.get('btn-gerar-diagnostico').disabled, false);
  pass('diagnostic_success_and_csrf');
  const duplicate = await setup('diagnostic_double_click');
  await vm.runInContext('Promise.all([gerarDiagnostico(), gerarDiagnostico()])', duplicate.context);
  assert.equal(posts(duplicate).length, 1);
  pass('diagnostic_single_pending_request');
  const restored = await setup('diagnostic_restored');
  await vm.runInContext('loadDiagnosticoUltimo()', restored.context);
  assert.match(restored.nodes.get('diagnostic-content').innerHTML, /Perfil ficticio/);
  assert.equal(posts(restored).length, 0);
  pass('diagnostic_restored_without_generation');
  for (const bad of [{}, { diagnostico: { perfil_resumido: 'Incompleto' } }]) {
    const invalid = await setup('invalid_saved_diagnostic', { changeFixture(data) {
      data['diagnostico.php?action=carregar_ultimo'] = { ok: true, resultado: bad };
    } });
    await vm.runInContext('loadDiagnosticoUltimo()', invalid.context);
    assert.equal(vm.runInContext('state.diagnostico', invalid.context), null);
    assert.match(invalid.nodes.get('diagnostic-summary').textContent, /formato esperado/);
    pass('invalid_saved_diagnostic_is_not_AI_success');
  }
  const local = await setup('essay_local_fallback');
  await vm.runInContext('analisarRedacao()', local.context);
  assert.match(local.nodes.get('redacao-status').textContent, /local.*IA indisponivel/);
  assert.match(local.nodes.get('redacao-analise').innerHTML, /Orientacao local/);
  assert.equal(vm.runInContext('state.redacaoPending', local.context), false);
  assert.ok(posts(local).every(call => call.hasCSRF));
  assert.ok(local.calls.every(call => !/conquistas.*verificar|openrouter|cron/.test(call.key)));
  pass('essay_fallback_honest_origin_and_no_extra_writes');
  const essayDuplicate = await setup('essay_double_click');
  await vm.runInContext('Promise.all([analisarRedacao(), analisarRedacao()])', essayDuplicate.context);
  assert.equal(posts(essayDuplicate).length, 1);
  pass('essay_single_pending_request');
  for (const [fn, path, control] of [
    ['gerarDiagnostico()', 'diagnostico.php?action=gerar', 'btn-gerar-diagnostico'],
    ['analisarRedacao()', 'redacao.php?action=analisar', 'redacao-status']
  ]) {
    for (const failure of [401, 403, 419, 429, 500, 503, 'json', 'network']) {
      const test = await setup(fn + failure, { failures: { [path]: failure } });
      await vm.runInContext(fn, test.context);
      assert.equal(posts(test).length, 1);
      assert.equal(vm.runInContext('!!state.redacaoPending', test.context), false);
      if (control === 'btn-gerar-diagnostico') assert.equal(test.nodes.get(control).disabled, false);
      if (failure === 401) assert.equal(test.context.location.href, 'index.html');
      pass(fn + '_failure_' + failure);
    }
    for (const bad of [null, {}, [], { ok: true }]) {
      const test = await setup(fn + '_malformed', { changeFixture(data) { data[path] = bad; } });
      const previous = JSON.stringify(vm.runInContext('state.diagnostico', test.context));
      await vm.runInContext(fn, test.context);
      assert.equal(JSON.stringify(vm.runInContext('state.diagnostico', test.context)), previous);
      assert.equal(vm.runInContext('state.redacaoAtualId', test.context), null);
      pass(fn + '_invalid_contract');
    }
  }
  const unsafe = await setup('unsafe_text_is_escaped');
  vm.runInContext('renderRedacaoAnalise({origem:"fallback",resumo:"<script>alert(1)</script>",competencias:[],proximos_passos:[]})', unsafe.context);
  assert.ok(!unsafe.nodes.get('redacao-analise').innerHTML.includes('<script>'));
  assert.match(unsafe.nodes.get('redacao-analise').innerHTML, /&lt;script&gt;/);
  pass('essay_escapes_untrusted_text');
  const helper = await setup('legacy_helper_approved_action');
  vm.runInContext(fs.readFileSync('app/src/services/ia.js', 'utf8'), helper.context);
  await vm.runInContext('EstudAIApi.planoEstudos({usuario_id:999})', helper.context);
  assert.equal(posts(helper).at(-1).key, 'plano-estudos.php?action=gerar_semana');
  pass('legacy_service_uses_approved_weekly_action');
  console.log(JSON.stringify({ result: 'PASS', scenarios: results.length, results, external_requests: 0, simulated_DOM: true }));
}
main().catch(error => { console.error(error.stack); process.exitCode = 1; });
