<?php
require_once __DIR__ . '/ai_stage13_bootstrap.php';
if (DB_NAME !== 'estudai_teste_2026' || DB_USER !== 'estudai_teste_app') {
    throw new RuntimeException('Unsafe database configuration');
}
putenv('ESTUDAI_AI_DISABLED=false');
putenv('ESTUDAI_AI_FEATURES=plano_semanal,diagnostico,redacao');
putenv('OPENROUTER_API_KEY=sk-or-stage13-sentinel');
putenv('OPENROUTER_MODEL=tests/model:free');
$ai13Checks = [];
class Ai13CommitFailure extends PDO {
    public string $mode;
    public function commit(): bool {
        if ($this->mode === 'plan') {
            $row = $this->query("SELECT p.origem, t.usuario_id, i.usuario_id item_owner, e.usuario_id event_owner FROM planos_estudo p JOIN tarefas_estudo t ON t.plano_id=p.id JOIN plano_estudo_itens i ON i.id=t.item_id JOIN eventos_calendario_estudai e ON e.tarefa_id=t.id WHERE p.usuario_id=2 ORDER BY p.id DESC LIMIT 1")->fetch();
            ai13Check($row['origem'] === 'ia' && (int)$row['usuario_id'] === 2 && (int)$row['item_owner'] === 2 && (int)$row['event_owner'] === 2, 'uncommitted_mock_plan_and_links_owned_and_consistent');
        } elseif ($this->mode === 'diagnostic') {
            $row = $this->query("SELECT usuario_id, resposta_json FROM ia_historico WHERE tipo='diagnostico' ORDER BY id DESC LIMIT 1")->fetch();
            $data = json_decode($row['resposta_json'], true);
            ai13Check((int)$row['usuario_id'] === 2 && $data['diagnostico']['perfil_resumido'] !== '', 'uncommitted_mock_diagnostic_valid_and_owned');
        } else {
            $row = $this->query("SELECT usuario_id, analise_json FROM redacoes_enem ORDER BY id DESC LIMIT 1")->fetch();
            $data = json_decode($row['analise_json'], true);
            ai13Check((int)$row['usuario_id'] === 2 && $data['origem'] === 'ia' && count($data['competencias']) === 5, 'uncommitted_mock_essay_valid_and_owned');
        }
        throw new RuntimeException('Controlled AI persistence failure');
    }
}
function ai13Snapshot(PDO $db): array {
    $out = [];
    foreach (['estudo_perfis', 'planos_estudo', 'plano_versoes', 'planejamento_semanal_controle', 'plano_estudo_itens', 'tarefas_estudo', 'eventos_calendario_estudai', 'ia_historico', 'redacoes_enem', 'respostas_usuario', 'simulado_tentativas', 'simulados_planejados'] as $table) {
        $out[$table] = hash('sha256', json_encode($db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll()));
    }
    return $out;
}
$fixture = ai13Fixture();
foreach (['plan', 'diagnostic', 'essay'] as $mode) {
    $db = new Ai13CommitFailure('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->mode = $mode;
    ai13Check($db->query('SELECT DATABASE()')->fetchColumn() === DB_NAME && $db->query('SELECT CURRENT_USER()')->fetchColumn() === 'estudai_teste_app@localhost', 'actual_database_' . $mode);
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = 2 AND email LIKE '%@estudai.invalid'");
    $stmt->execute();
    ai13Check((bool)$stmt->fetchColumn(), 'fictional_owner_' . $mode);
    $before = ai13Snapshot($db);
    $failed = false;
    try {
        if ($mode === 'plan') {
            $profile = planoPerfil($db, 2);
            // A separate in-memory week exercises rollback even after the real test plan exists.
            $profile['data_prova'] = null;
            $window = $fixture['window'];
            $window['semana_inicio'] = '2026-11-23';
            $window['semana_fim'] = '2026-11-23';
            $window['data_limite'] = null;
            $window['dias_cobertura_obrigatoria'][0]['data'] = '2026-11-23';
            $window['datas_disponiveis'][0]['data'] = '2026-11-23';
            $mockPlan = $fixture['plan'];
            $mockPlan['semana_inicio'] = '2026-11-23';
            $mockPlan['semana_fim'] = '2026-11-23';
            $mockPlan['tarefas'][0]['data'] = '2026-11-23';
            ai13Check(planoDaSemana($db, 2, $window) === null, 'plan_owner_has_no_existing_plan');
            planoGerarOuRecuperarSemanal($db, 2, $profile, $window, ai13Transport($mockPlan));
        } elseif ($mode === 'diagnostic') {
            diagnosticoGerarOuRecuperar($db, 2, ai13Transport($fixture['diagnostic']));
        } else {
            redacaoProcessar($db, 2, ['tema' => 'Texto ficticio de rollback', 'texto' => str_repeat('Educacao e acesso ao conhecimento. ', 20)], true, ai13Transport($fixture['essay']));
        }
    } catch (RuntimeException $error) {
        $failed = $error->getMessage() === 'Controlled AI persistence failure';
    }
    ai13Check($failed, 'failure_after_all_' . $mode . '_writes');
    ai13Check(!$db->inTransaction(), 'transaction_closed_' . $mode);
    ai13Check(ai13Snapshot($db) === $before, 'complete_rollback_' . $mode);
}
echo json_encode(['result' => 'PASS', 'checks' => $ai13Checks, 'external_requests' => 0]);
