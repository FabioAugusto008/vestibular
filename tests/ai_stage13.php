<?php
require_once __DIR__ . '/ai_stage13_bootstrap.php';
putenv('ESTUDAI_AI_DISABLED=false');
putenv('ESTUDAI_AI_FEATURES=plano_semanal,diagnostico,redacao,revisao_semanal,plano_anual');
putenv('OPENROUTER_API_KEY=sk-or-stage13-sentinel');
putenv('OPENROUTER_MODEL=tests/model:free');
putenv('OPENROUTER_BASE_URL=https://openrouter.ai/api/v1');
$ai13Checks = [];
$fixture = ai13Fixture();
function ai13Reject(callable $run, string $name): void {
    $rejected = false;
    try { $run(); } catch (RuntimeException $error) { $rejected = true; }
    ai13Check($rejected, $name);
}
class Ai13NoDatabase extends PDO {
    public function __construct() {}
}
$plan = $fixture['plan'];
$input = $fixture['input'];
$diagnostic = $fixture['diagnostic'];
ai13Check(estudaiGerarDiagnostico($fixture['profile'], ai13Transport($diagnostic))['ok'], 'diagnostic_valid_mock');
ai13Check(estudaiGerarPlanoSemanal($input, ai13Transport($plan))['ok'], 'weekly_valid_mock');
ai13Check(redacaoGerarAnaliseIa('Tema ficticio', str_repeat('Texto ficticio. ', 30), ai13Transport($fixture['essay']))['origem'] === 'ia', 'essay_valid_mock');
foreach (['{', '{"titulo":', '[]', 'null', 'texto inesperado', 'execute SQL; {"titulo":"teste"}'] as $raw) {
    ai13Check(estudaiDecodeJsonText($raw) === null, 'malformed_or_unexpected_json');
}
ai13Check(estudaiDecodeJsonText("~~~") === null, 'non_json_text');
$fence = chr(96) . chr(96) . chr(96);
ai13Check(estudaiDecodeJsonText($fence . "json\n" . json_encode($diagnostic) . "\n" . $fence) === $diagnostic, 'markdown_json');
ai13Check(estudaiDecodeJsonText(str_repeat('x', 100001)) === null, 'oversized_model_content');
foreach (array_keys($diagnostic) as $key) {
    $bad = $diagnostic;
    unset($bad[$key]);
    ai13Reject(fn() => estudaiAiDiagnostico($bad, $fixture['profile']), 'diagnostic_missing_' . $key);
    $bad = $diagnostic;
    $bad[$key] = null;
    ai13Reject(fn() => estudaiAiDiagnostico($bad, $fixture['profile']), 'diagnostic_null_' . $key);
}
$bad = $diagnostic;
$bad['materias_prioritarias'] = ['Materia inexistente'];
ai13Reject(fn() => estudaiAiDiagnostico($bad, $fixture['profile']), 'diagnostic_unknown_subject');
$bad['materias_prioritarias'] = ['Matematica'];
$bad['perfil_resumido'] = '<img src=x onerror=alert(1)>';
ai13Reject(fn() => estudaiAiDiagnostico($bad, $fixture['profile']), 'diagnostic_executable_html');
foreach ([
    ['data', '2026-02-30'], ['data', '2026-11-15'], ['data', '2026-11-18'],
    ['hora_inicio', '25:00'], ['hora_inicio', '17:00'], ['hora_fim', '18:00'],
    ['tempo_estimado', -1], ['tempo_estimado', '60'], ['tempo_estimado', null],
    ['tempo_estimado', 240], ['materia', 'Materia inventada'], ['conteudo', 'Conteudo inventado'],
    ['tipo', 'sql'], ['prioridade', 'urgente'], ['titulo', []], ['descricao', '<script>x</script>'],
] as [$key, $value]) {
    $bad = $plan;
    $bad['tarefas'][0][$key] = $value;
    ai13Reject(fn() => estudaiAiPlanoSemanal($bad, $input), 'weekly_invalid_' . $key);
}
foreach ([null, [], 'tarefas', [null]] as $tasks) {
    $bad = $plan;
    $bad['tarefas'] = $tasks;
    ai13Reject(fn() => estudaiAiPlanoSemanal($bad, $input), 'weekly_invalid_task_collection');
}
$bad = $plan;
$bad['tarefas'][] = $bad['tarefas'][0];
ai13Reject(fn() => estudaiAiPlanoSemanal($bad, $input), 'weekly_duplicate_or_overlap');
$bad = $plan;
$bad['semana_inicio'] = '2026-02-30';
ai13Reject(fn() => estudaiAiPlanoSemanal($bad, $input), 'weekly_header_mismatch');
$bad = $plan;
$bad['usuario_id'] = 999999;
$bad['tarefas'][0]['usuario_id'] = 999999;
$safe = estudaiAiPlanoSemanal($bad, $input);
ai13Check(!isset($safe['usuario_id']) && !isset($safe['tarefas'][0]['usuario_id']), 'model_cannot_choose_owner');
$sensitive = $input + ['email' => 'never-send@example.invalid', 'token' => 'never-send-token'];
$sensitive['perfil']['email'] = 'nested@example.invalid';
$prompt = json_encode(estudaiPlanoSemanalPrompt($sensitive));
ai13Check(!str_contains($prompt, 'never-send') && !str_contains($prompt, 'nested@') && !str_contains($prompt, 'usuario_id'), 'prompt_minimizes_identifiers');
ai13Check(estudaiDecodeJsonText('{"summary":"English keys"}') !== null, 'foreign_language_object_parsed_only');
ai13Reject(fn() => estudaiAiDiagnostico(['summary' => 'English keys'], $fixture['profile']), 'foreign_language_contract_rejected');
$review = [
    'resumo_semana' => 'Semana ficticia.', 'mensagem_usuario' => 'Rever as pendencias.',
    'desempenho' => ['classificacao' => 'medio', 'percentual_conclusao' => 50, 'pontos_fortes' => [], 'pontos_fracos' => []],
    'ajuste_pesos' => ['Matematica' => 2], 'decisao' => ['acao' => 'manter', 'motivos' => []],
];
ai13Check(estudaiGerarRevisaoSemanal($input, ai13Transport($review))['ok'], 'weekly_review_valid_mock');
$bad = $review;
$bad['decisao']['acao'] = 'execute_sql';
ai13Reject(fn() => estudaiAiRevisao($bad, $input), 'review_arbitrary_action');
$bad = $review;
$bad['ajuste_pesos'] = ['Matematica' => -3];
ai13Reject(fn() => estudaiAiRevisao($bad, $input), 'review_invalid_weight');
$bad = $fixture['essay'];
array_pop($bad['competencias']);
ai13Reject(fn() => estudaiAiRedacao($bad), 'essay_missing_competency');
$bad = $fixture['essay'];
$bad['competencias'][1] = $bad['competencias'][0];
ai13Reject(fn() => estudaiAiRedacao($bad), 'essay_duplicate_competency');
$bad = $fixture['essay'];
$bad['sugestoes'] = 'lista incorreta';
ai13Reject(fn() => estudaiAiRedacao($bad), 'essay_invalid_list');
$bad = $fixture['essay'] + ['nota_oficial' => 1000, 'usuario_id' => 99999];
ai13Check(!isset(estudaiAiRedacao($bad)['nota_oficial']), 'essay_no_official_grade');
$annual = [
    'titulo' => 'Plano anual ficticio', 'resumo' => 'Resumo ficticio', 'estrategia_geral' => 'Estudar no horario disponivel.',
    'data_inicio' => '2026-11-16', 'data_fim' => '2026-11-17',
    'meses' => [['mes' => '2026-11', 'objetivo_do_mes' => 'Estudar porcentagem.', 'foco_principal' => ['Matematica'],
        'semanas' => [['semana_inicio' => '2026-11-16', 'semana_fim' => '2026-11-16', 'objetivo_da_semana' => 'Revisar.', 'tarefas' => $plan['tarefas']]]]],
];
ai13Check(estudaiGerarPlanoAnual($input, ai13Transport($annual))['ok'], 'annual_backend_valid_mock');
$bad = $annual;
$bad['meses'][0]['mes'] = '2026-13';
ai13Reject(fn() => estudaiAiPlanoAnual($bad, $input), 'annual_invalid_month');
foreach (['2026-11-13' => ['2026-11-02', '2026-11-08', '2026-11-09'], '2026-11-15' => ['2026-11-09', '2026-11-15', '2026-11-16'], '2027-01-03' => ['2026-12-28', '2027-01-03', '2027-01-04']] as $date => $expected) {
    $window = estudaiWeeklyWindow(new DateTimeImmutable($date, new DateTimeZone('America/Sao_Paulo')));
    ai13Check([$window['semana_inicio'], $window['semana_fim'], $window['proxima_inicio']] === $expected, 'weekly_window_' . $date);
}
ai13Check(date_default_timezone_get() === 'America/Sao_Paulo', 'application_timezone');
foreach (['timeout', 'network', 401, 403, 404, 408, 429, 500, 503, 'invalid_json', 'empty'] as $failure) {
    $calls = 0;
    $transport = static function () use ($failure, &$calls): array {
        $calls++;
        if (is_string($failure) && in_array($failure, ['timeout', 'network'], true)) throw new OpenRouterException($failure);
        return ['status' => is_int($failure) ? $failure : 200, 'body' => $failure === 'empty' ? '' : '{'];
    };
    $fallback = planoGerarSemanalValidado(new Ai13NoDatabase(), 2, $fixture['profile'], $input, $fixture['window'], $transport);
    ai13Check($fallback['ok'] && $fallback['origem'] === 'fallback' && $fallback['fallback_usado'], 'planning_fallback_' . $failure);
    ai13Check($calls === 1, 'no_cascading_retries_' . $failure);
}
$calls = 0;
putenv('OPENROUTER_API_KEY=');
$fallback = planoGerarSemanalValidado(new Ai13NoDatabase(), 2, $fixture['profile'], $input, $fixture['window'], static function () use (&$calls) { $calls++; });
ai13Check($calls === 0 && $fallback['origem'] === 'fallback', 'missing_key_fallback_without_transport');
putenv('OPENROUTER_API_KEY=sk-or-stage13-sentinel');
putenv('OPENROUTER_MODEL=tests/paid');
$fallback = planoGerarSemanalValidado(new Ai13NoDatabase(), 2, $fixture['profile'], $input, $fixture['window'], static function () use (&$calls) { $calls++; });
ai13Check($calls === 0 && $fallback['origem'] === 'fallback', 'paid_model_blocked_with_local_fallback');
putenv('OPENROUTER_MODEL=tests/model:free');
putenv('ESTUDAI_AI_DISABLED=true');
$fallback = planoGerarSemanalValidado(new Ai13NoDatabase(), 2, $fixture['profile'], $input, $fixture['window'], static function () use (&$calls) { $calls++; });
ai13Check($calls === 0 && $fallback['origem'] === 'fallback', 'disabled_fallback_without_transport');
echo json_encode(['result' => 'PASS', 'checks' => $ai13Checks, 'external_requests' => 0]);
