<?php
// api/cron_revisao_semanal_ia.php - Execucao dominical da revisao semanal IA

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/weeklyReview.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();

function cronRevisaoLocalRequest(): bool {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);
}

function cronRevisaoAuthorized(): bool {
    $configured = getenv('ESTUDAI_CRON_TOKEN') ?: ($_ENV['ESTUDAI_CRON_TOKEN'] ?? '');
    $provided = $_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
    if ($configured !== '') {
        return hash_equals((string)$configured, (string)$provided);
    }
    return cronRevisaoLocalRequest();
}

try {
    if (!cronRevisaoAuthorized()) {
        jsonResponse(['erro' => 'Cron nao autorizado.'], 403);
    }
    if (!dbTableExists($db, 'revisoes_semanais_ia') || !dbTableExists($db, 'plano_versoes') || !dbTableExists($db, 'planejamento_semanal_controle')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    $today = new DateTimeImmutable('today');
    $force = cronRevisaoLocalRequest() && (($_GET['force'] ?? '') === '1');
    if ($today->format('N') !== '7' && !$force) {
        jsonResponse([
            'ok' => true,
            'executado' => false,
            'motivo' => 'A revisao semanal automatica roda apenas aos domingos.',
            'data' => $today->format('Y-m-d'),
        ]);
    }

    $plans = estudaiWeeklyActivePlans($db, null);
    $resultados = [];
    foreach ($plans as $plano) {
        $uid = (int)$plano['usuario_id'];
        $resultados[] = [
            'usuario_id' => $uid,
            'plano_id' => (int)$plano['id'],
            'resultado' => estudaiWeeklyRunForUser($db, $uid, 'cron'),
        ];
    }

    jsonResponse([
        'ok' => true,
        'executado' => true,
        'usuarios_processados' => count($resultados),
        'resultados' => $resultados,
    ]);
} catch (Throwable $e) {
    jsonResponse(['erro' => 'Nao foi possivel executar a revisao semanal automatica.'], 500);
}
