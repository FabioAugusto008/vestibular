// Real app.js flow with sanitized HTTP fixtures and a simulated DOM.
process.env.TZ = 'America/Sao_Paulo';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { scenario } = require('./dashboard_initialization.test.js');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const generated = fixture['plano-estudos.php?action=gerar_semana'];
assert.ok(generated?.ok && generated.plano.tarefas.length, 'Use the stage08 sanitized planning fixture');
const selectors = { '#calendar-filters input:checked': ['teoria', 'exercicio', 'revisao', 'simulado', 'resumo', 'concluido', 'atrasado'].map(value => ({ value, checked: true })) };
const results = [];

async function setup(name, options = {}) {
  return scenario(name, { selectors, ...options, changeFixture(data) {
    data['plano-estudos.php?action=carregar_ativo'].plano = null;
    if (options.changeFixture) options.changeFixture(data);
  } });
}
function postCalls(r) { return r.calls.filter(c => c.method === 'POST'); }
function forbiddenCalls(r) { return r.calls.filter(c => c.key !== 'diagnostico.php?action=carregar_ultimo' && /simulados|cron|diagnostico|ia\.php/.test(c.key)); }
async function generate(r) { await vm.runInContext('gerarPlanoEstudos()', r.context); }

async function main() {
  const normal = await setup('planning_success');
  await generate(normal);
  assert.equal(postCalls(normal).length, 1);
  assert.equal(postCalls(normal)[0].hasCSRF, true);
  assert.equal(forbiddenCalls(normal).length, 0);
  assert.equal(normal.nodes.get('btn-gerar-plano').disabled, false);
  assert.match(normal.nodes.get('plan-status-text').textContent, /localmente/);
  assert.match(normal.nodes.get('plan-content').innerHTML, /fallback/);
  const day = generated.plano.tarefas[0].data_prevista;
  assert.equal(vm.runInContext('state.selectedCalendarDay', normal.context), day);
  assert.match(normal.nodes.get('calendar-day-events').innerHTML, /task-card/);
  assert.ok(normal.nodes.get('calendar-grid').innerHTML.includes('has-events'));
  results.push({ test: 'one_explicit_POST_with_CSRF_plan_tasks_and_calendar_contracts', result: 'PASS' });

  const double = await setup('planning_double_click');
  await Promise.all([vm.runInContext('gerarPlanoEstudos()', double.context), vm.runInContext('gerarPlanoEstudos()', double.context)]);
  assert.equal(postCalls(double).length, 1);
  results.push({ test: 'double_click_does_not_send_duplicate_POST', result: 'PASS' });

  for (const failure of [401, 403, 500, 'json', 'network']) {
    const r = await setup('planning_failure_' + failure, { failures: { 'plano-estudos.php?action=gerar_semana': failure } });
    await generate(r);
    assert.equal(vm.runInContext('state.plano', r.context), null);
    assert.equal(r.nodes.get('btn-gerar-plano').disabled, false);
    assert.equal(r.calls.filter(c => c.key.startsWith('calendario-estudai')).length, 0);
    if (failure === 401) assert.equal(r.context.location.href, 'index.html');
    results.push({ test: 'failed_generation_' + failure + '_has_no_false_plan', result: 'PASS' });
  }

  const reused = await setup('planning_reused', { changeFixture(data) { data['plano-estudos.php?action=gerar_semana'].reutilizado = true; } });
  await generate(reused);
  assert.match(reused.nodes.get('plan-status-text').textContent, /recuperado, sem duplicar/);
  results.push({ test: 'reused_plan_is_identified', result: 'PASS' });

  const monthKey = 'calendario-estudai.php?action=mes&mes=' + day.slice(0, 7);
  const partial = await setup('calendar_secondary_failure', { failures: { [monthKey]: 500 } });
  await generate(partial);
  assert.equal(vm.runInContext('state.plano.id', partial.context), generated.plano.id);
  assert.ok(partial.nodes.get('calendar-status').textContent);
  assert.equal(partial.nodes.get('btn-gerar-plano').disabled, false);
  results.push({ test: 'calendar_failure_preserves_saved_plan', result: 'PASS' });

  for (const date of ['2026-11-11T23:30:00-03:00', '2026-12-31T23:50:00-03:00']) {
    assert.equal(vm.runInContext('isoDate(new Date(' + JSON.stringify(date) + '))', normal.context), date.slice(0, 10));
    results.push({ test: 'local_date_at_midnight_boundary_' + date.slice(0, 10), result: 'PASS' });
  }
  const empty = await setup('calendar_empty', { changeFixture(data) {
    data[monthKey].eventos = [];
    data['calendario-estudai.php?action=ano&ano=' + day.slice(0, 4)].eventos = [];
  } });
  vm.runInContext('state.calendarDate=new Date(' + JSON.stringify(day + 'T12:00:00') + ');state.selectedCalendarDay=' + JSON.stringify(day), empty.context);
  await vm.runInContext('loadCalendarMonth()', empty.context);
  assert.equal(empty.nodes.get('calendar-status').textContent, '0 eventos neste mes');
  assert.match(empty.nodes.get('calendar-day-events').innerHTML, /Nenhuma atividade/);
  results.push({ test: 'empty_calendar_remains_read_only_and_truthful', result: 'PASS' });
  console.log(JSON.stringify({ result: 'PASS', scenarios: results.length, results, simulated_DOM: true, visual_validation: false, real_network_requests: 0 }));
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
