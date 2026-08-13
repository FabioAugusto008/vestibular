<?php
// api/revisao-semanal-ia.php - Revisao semanal manual/controlada

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/weeklyReview.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

function revisaoSemanalMigrationOk(PDO $db): bool {
    return dbTableExists($db, 'revisoes_semanais_ia')
        && dbTableExists($db, 'plano_versoes')
        && dbTableExists($db, 'tarefas_estudo')
        && dbTableExists($db, 'planejamento_semanal_controle');
}

try {
    if (!revisaoSemanalMigrationOk($db)) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'status':
            $today = new DateTimeImmutable('today');
            jsonResponse([
                'ok' => true,
                'domingo' => $today->format('N') === '7',
                'ultima' => estudaiWeeklyLatest($db, $uid),
                'tem_plano_ativo' => (bool)estudaiWeeklyActivePlans($db, $uid),
            ]);
            break;

        case 'ultima':
            jsonResponse(['ok' => true, 'ultima' => estudaiWeeklyLatest($db, $uid)]);
            break;

        case 'executar_manual':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('revisao_semanal_manual', 6, 3600);
            $resultado = estudaiWeeklyRunForUser($db, $uid, 'manual');
            if (!$resultado['ok']) {
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao foi possivel executar a revisao.'], 400);
            }
            jsonResponse($resultado);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['erro' => 'Nao foi possivel processar a revisao semanal agora.'], 500);
}
