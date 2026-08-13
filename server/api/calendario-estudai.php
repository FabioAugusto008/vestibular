<?php
// api/calendario-estudai.php - Calendario anual/mensal do EstudAI

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'mes';

function calendarioValidDate($value): string {
    $value = sanitizeTextValue($value, 10);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        jsonResponse(['erro' => 'Data invalida. Use YYYY-MM-DD.'], 400);
    }
    return $value;
}

function calendarioValidMonth($value): string {
    $value = sanitizeTextValue($value ?: date('Y-m'), 7);
    if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
        jsonResponse(['erro' => 'Mes invalido. Use YYYY-MM.'], 400);
    }
    return $value;
}

function calendarioMapEvento(array $row): array {
    $metadata = [];
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string)$row['metadata_json'], true);
        $metadata = is_array($decoded) ? $decoded : [];
    }
    $status = $row['status'] ?? 'pendente';
    if ($status === 'pendente' && !empty($row['data_evento']) && $row['data_evento'] < date('Y-m-d')) {
        $status = 'atrasado';
    }
    return [
        'id' => (int)$row['id'],
        'plano_id' => isset($row['plano_id']) ? (int)$row['plano_id'] : null,
        'tarefa_id' => isset($row['tarefa_id']) ? (int)$row['tarefa_id'] : null,
        'tipo' => $row['tipo'] ?? 'tarefa',
        'titulo' => $row['titulo'] ?? '',
        'descricao' => $row['descricao'] ?? '',
        'conteudo' => $row['conteudo'] ?? ($metadata['conteudo'] ?? null),
        'data_evento' => $row['data_evento'] ?? null,
        'hora_inicio' => $row['hora_inicio'] ? substr((string)$row['hora_inicio'], 0, 5) : null,
        'hora_fim' => $row['hora_fim'] ? substr((string)$row['hora_fim'], 0, 5) : null,
        'status' => $row['status'] ?? 'pendente',
        'status_calculado' => $status,
        'metadata' => $metadata,
    ];
}

function calendarioTipoFromTarefa(string $tipo): string {
    if ($tipo === 'questoes') return 'exercicio';
    if (in_array($tipo, ['revisao', 'simulado', 'resumo'], true)) return $tipo;
    return 'tarefa';
}

function calendarioSyncTarefas(PDO $db, int $uid): void {
    if (!dbTableExists($db, 'eventos_calendario_estudai') || !dbTableExists($db, 'tarefas_estudo')) {
        return;
    }

    $exists = $db->prepare('SELECT 1 FROM eventos_calendario_estudai WHERE usuario_id = ? AND tarefa_id = ? LIMIT 1');
    $hasConteudo = dbColumnExists($db, 'eventos_calendario_estudai', 'conteudo');
    $hasSemanaInicio = dbColumnExists($db, 'eventos_calendario_estudai', 'semana_inicio');
    $columns = ['usuario_id', 'plano_id', 'tarefa_id', 'tipo', 'titulo', 'descricao'];
    if ($hasConteudo) $columns[] = 'conteudo';
    $columns = array_merge($columns, ['data_evento', 'hora_inicio', 'hora_fim']);
    if ($hasSemanaInicio) {
        $columns[] = 'semana_inicio';
        $columns[] = 'semana_fim';
    }
    $columns = array_merge($columns, ['status', 'metadata_json']);
    $ins = $db->prepare('
        INSERT INTO eventos_calendario_estudai
            (`' . implode('`,`', $columns) . '`)
        VALUES
            (' . implode(',', array_fill(0, count($columns), '?')) . ')
    ');

    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 't.conteudo' : "NULL AS conteudo";
    $horaInicioExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_inicio') ? 't.hora_inicio' : "NULL AS hora_inicio";
    $horaFimExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_fim') ? 't.hora_fim' : "NULL AS hora_fim";
    $tempoExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
        ? 'COALESCE(t.tempo_estimado, t.tempo_estimado_min)'
        : 't.tempo_estimado';
    $filtrarPlanos = dbTableExists($db, 'planos_estudo') && dbColumnExists($db, 'planos_estudo', 'status');
    $planJoin = $filtrarPlanos ? 'LEFT JOIN planos_estudo p ON p.id = t.plano_id AND p.usuario_id = t.usuario_id' : '';
    $planFilter = $filtrarPlanos ? "AND (t.plano_id IS NULL OR p.id IS NULL OR COALESCE(p.status, '') NOT IN ('substituido', 'arquivado', 'cancelado'))" : '';
    $stmt = $db->prepare("
        SELECT t.id, t.plano_id, t.titulo, t.descricao, t.materia, {$conteudoExpr}, t.tipo, t.data_prevista,
               {$horaInicioExpr}, {$horaFimExpr}, {$tempoExpr} AS tempo_estimado, t.prioridade, t.status
        FROM tarefas_estudo t
        {$planJoin}
        WHERE t.usuario_id = ? AND t.data_prevista IS NOT NULL {$planFilter}
        ORDER BY t.data_prevista ASC
        LIMIT 800
    ");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $tarefa) {
        $exists->execute([$uid, $tarefa['id']]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $metadata = [
            'materia' => $tarefa['materia'] ?? '',
            'conteudo' => $tarefa['conteudo'] ?? '',
            'prioridade' => $tarefa['prioridade'] ?? 'media',
            'tempo_estimado' => (int)($tarefa['tempo_estimado'] ?? 0),
            'tipo_tarefa' => $tarefa['tipo'] ?? 'custom',
        ];
        $weekStart = (new DateTimeImmutable($tarefa['data_prevista']))->modify('monday this week')->format('Y-m-d');
        $weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
        $params = [
            $uid,
            $tarefa['plano_id'] ?: null,
            (int)$tarefa['id'],
            calendarioTipoFromTarefa((string)$tarefa['tipo']),
            $tarefa['titulo'],
            $tarefa['descricao'],
        ];
        if ($hasConteudo) $params[] = $tarefa['conteudo'] ?: null;
        $params[] = $tarefa['data_prevista'];
        $params[] = $tarefa['hora_inicio'] ?: null;
        $params[] = $tarefa['hora_fim'] ?: null;
        if ($hasSemanaInicio) {
            $params[] = $weekStart;
            $params[] = $weekEnd;
        }
        $params[] = $tarefa['status'] === 'concluida' ? 'concluido' : 'pendente';
        $params[] = jsonEncodeSafe($metadata);
        $ins->execute($params);
    }
}

function calendarioFetch(PDO $db, int $uid, string $where, array $params): array {
    calendarioSyncTarefas($db, $uid);
    $planFilter = '';
    if (
        dbTableExists($db, 'planos_estudo')
        && dbColumnExists($db, 'planos_estudo', 'status')
        && dbColumnExists($db, 'eventos_calendario_estudai', 'plano_id')
    ) {
        $planFilter = "
            AND (
                plano_id IS NULL
                OR NOT EXISTS (
                    SELECT 1
                    FROM planos_estudo p
                    WHERE p.id = eventos_calendario_estudai.plano_id
                      AND p.usuario_id = eventos_calendario_estudai.usuario_id
                      AND p.status IN ('substituido', 'arquivado', 'cancelado')
                )
            )
        ";
    }
    $stmt = $db->prepare("
        SELECT *
        FROM eventos_calendario_estudai
        WHERE usuario_id = ? {$where} {$planFilter}
        ORDER BY data_evento ASC, hora_inicio ASC, id ASC
    ");
    $stmt->execute(array_merge([$uid], $params));
    return array_map('calendarioMapEvento', $stmt->fetchAll());
}

try {
    if (!dbTableExists($db, 'eventos_calendario_estudai')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'mes':
            $mes = calendarioValidMonth($_GET['mes'] ?? date('Y-m'));
            $inicio = $mes . '-01';
            $fim = (new DateTimeImmutable($inicio))->modify('last day of this month')->format('Y-m-d');
            jsonResponse([
                'ok' => true,
                'mes' => $mes,
                'eventos' => calendarioFetch($db, $uid, 'AND data_evento BETWEEN ? AND ?', [$inicio, $fim]),
            ]);
            break;

        case 'ano':
            $ano = (int)($_GET['ano'] ?? date('Y'));
            $ano = max(2020, min(2100, $ano));
            jsonResponse([
                'ok' => true,
                'ano' => $ano,
                'eventos' => calendarioFetch($db, $uid, 'AND data_evento BETWEEN ? AND ?', [$ano . '-01-01', $ano . '-12-31']),
            ]);
            break;

        case 'dia':
            $data = calendarioValidDate($_GET['data'] ?? date('Y-m-d'));
            jsonResponse([
                'ok' => true,
                'data' => $data,
                'eventos' => calendarioFetch($db, $uid, 'AND data_evento = ?', [$data]),
            ]);
            break;

        case 'eventos':
            $inicio = calendarioValidDate($_GET['inicio'] ?? date('Y-m-01'));
            $fim = calendarioValidDate($_GET['fim'] ?? date('Y-m-d'));
            jsonResponse([
                'ok' => true,
                'eventos' => calendarioFetch($db, $uid, 'AND data_evento BETWEEN ? AND ?', [$inicio, $fim]),
            ]);
            break;

        case 'atualizar_status':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $eventoId = (int)($payload['evento_id'] ?? 0);
            $status = sanitizeTextValue($payload['status'] ?? '', 20);
            if ($eventoId <= 0 || !in_array($status, ['pendente', 'concluido', 'atrasado', 'bloqueado', 'cancelado'], true)) {
                jsonResponse(['erro' => 'Dados de evento invalidos.'], 400);
            }
            $stmt = $db->prepare('
                UPDATE eventos_calendario_estudai
                SET status = ?, atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ? AND usuario_id = ?
            ');
            $stmt->execute([$status, $eventoId, $uid]);
            jsonResponse(['ok' => true]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('calendario_estudai_api_error', $e);
    jsonResponse(['erro' => 'Nao foi possivel carregar o calendario agora.'], 500);
}
