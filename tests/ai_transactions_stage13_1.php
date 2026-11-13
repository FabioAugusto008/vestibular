<?php
// Mocked upstream; real writer inspected inside a transaction which always rolls back.
require_once __DIR__ . '/ai_stage13_bootstrap.php';
if (DB_NAME !== 'estudai_teste_2026' || DB_USER !== 'estudai_teste_app') throw new RuntimeException('Unsafe configuration');
putenv('ESTUDAI_AI_DISABLED=false');
putenv('ESTUDAI_AI_FEATURES=plano_semanal,diagnostico');
putenv('OPENROUTER_API_KEY=sk-or-stage13-1-sentinel');
putenv('OPENROUTER_MODEL=tests/model:free');
$ai13Checks = [];
class Stage131Rollback extends PDO {
    public function commit(): bool {
        $row = $this->query("SELECT id,origem FROM planos_estudo WHERE usuario_id=2 ORDER BY id DESC LIMIT 1")->fetch();
        ai13Check($row['origem'] === 'fallback', 'invalid_remote_never_saved_as_ia');
        $stmt = $this->prepare('SELECT COUNT(*) FROM tarefas_estudo t JOIN plano_estudo_itens i ON i.id=t.item_id JOIN eventos_calendario_estudai e ON e.tarefa_id=t.id WHERE t.plano_id=? AND t.usuario_id=2 AND i.usuario_id=2 AND e.usuario_id=2');
        $stmt->execute([$row['id']]);
        ai13Check((int)$stmt->fetchColumn() === 1, 'single_fallback_task_item_and_event');
        throw new RuntimeException('stage131 controlled rollback');
    }
}
function stage131Snapshot(PDO $db): array {
    $result = [];
    foreach (['estudo_perfis', 'planos_estudo', 'plano_versoes', 'planejamento_semanal_controle', 'plano_estudo_itens', 'tarefas_estudo', 'eventos_calendario_estudai', 'ia_historico'] as $table) {
        $result[$table] = hash('sha256', json_encode($db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll()));
    }
    return $result;
}
$db = new Stage131Rollback('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
estudaiRequireTestDatabase($db);
ai13Check((bool)$db->query("SELECT id FROM usuarios WHERE id=2 AND email LIKE '%@estudai.invalid'")->fetchColumn(), 'fictional_owner');
$before = stage131Snapshot($db);
$fixture = ai13Fixture();
$profile = planoPerfil($db, 2);
$profile['data_prova'] = null; // In-memory alternate week, never UPDATE the profile.
$window = $fixture['window'];
$window['semana_inicio'] = $window['semana_fim'] = '2026-11-23';
$window['data_limite'] = null;
$window['dias_cobertura_obrigatoria'][0]['data'] = $window['datas_disponiveis'][0]['data'] = '2026-11-23';
ai13Check(planoDaSemana($db, 2, $window) === null, 'alternate_week_empty');
foreach (['missing_field', 'invalid_date', 'unknown_subject', 'invalid_duration', 'truncated_json'] as $case) {
    $plan = $fixture['plan'];
    $plan['semana_inicio'] = $plan['semana_fim'] = $plan['tarefas'][0]['data'] = '2026-11-23';
    if ($case === 'missing_field') unset($plan['tarefas'][0]['titulo']);
    if ($case === 'invalid_date') $plan['tarefas'][0]['data'] = '2026-02-30';
    if ($case === 'unknown_subject') $plan['tarefas'][0]['materia'] = 'Materia inexistente';
    if ($case === 'invalid_duration') $plan['tarefas'][0]['tempo_estimado'] = -1;
    $text = json_encode($plan);
    if ($case === 'truncated_json') $text = substr($text, 0, -20);
    $calls = 0;
    $transport = static function () use ($text, &$calls): array {
        $calls++;
        return ['status' => 200, 'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $text]]]])];
    };
    $failed = false;
    try { planoGerarOuRecuperarSemanal($db, 2, $profile, $window, $transport); }
    catch (RuntimeException $error) { $failed = $error->getMessage() === 'stage131 controlled rollback'; }
    ai13Check($failed && $calls === 1, $case . '_one_remote_attempt_then_fallback');
    ai13Check(!$db->inTransaction() && stage131Snapshot($db) === $before, $case . '_no_partial_rows_or_open_transaction');
}
$bad = $fixture['diagnostic'];
unset($bad['perfil_resumido']);
$result = diagnosticoGerarOuRecuperar($db, 2, ai13Transport($bad));
ai13Check(!$result['ok'] && !$db->inTransaction() && stage131Snapshot($db) === $before, 'invalid_diagnostic_no_persistence');
echo json_encode(['result' => 'PASS', 'checks' => $ai13Checks, 'external_requests' => 0, 'committed_rows' => 0]);
