<?php
// api/tarefas-estudo.php - Tarefas acionaveis da rotina diaria

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'hoje';

function tarefasTempoExpr(PDO $db): string {
    return dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
        ? 'COALESCE(tempo_estimado, tempo_estimado_min)'
        : 'tempo_estimado';
}

function tarefasPlanoAtivoFilter(PDO $db, string $alias = 'tarefas_estudo'): string {
    if (
        !dbTableExists($db, 'planos_estudo')
        || !dbColumnExists($db, 'planos_estudo', 'status')
        || !dbColumnExists($db, 'tarefas_estudo', 'plano_id')
    ) {
        return '';
    }

    return "
        AND (
            {$alias}.plano_id IS NULL
            OR NOT EXISTS (
                SELECT 1
                FROM planos_estudo p
                WHERE p.id = {$alias}.plano_id
                  AND p.usuario_id = {$alias}.usuario_id
                  AND p.status IN ('substituido', 'arquivado', 'cancelado')
            )
        )
    ";
}

function tarefasMapRow(array $row): array {
    $dataPrevista = $row['data_prevista'] ?? null;
    $status = $row['status'] ?? 'pendente';
    $statusCalculado = $status;
    if (!in_array($status, ['concluida', 'cancelada'], true) && $dataPrevista && $dataPrevista < date('Y-m-d')) {
        $statusCalculado = 'atrasada';
    }

    $metadata = [];
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string)$row['metadata_json'], true);
        $metadata = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => (int)$row['id'],
        'plano_id' => isset($row['plano_id']) ? (int)$row['plano_id'] : null,
        'item_id' => isset($row['item_id']) ? (int)$row['item_id'] : null,
        'titulo' => $row['titulo'] ?? '',
        'descricao' => $row['descricao'] ?? '',
        'materia' => $row['materia'] ?? '',
        'tipo' => $row['tipo'] ?? 'custom',
        'conteudo' => $row['conteudo'] ?? null,
        'data_prevista' => $dataPrevista,
        'hora_inicio' => !empty($row['hora_inicio']) ? substr((string)$row['hora_inicio'], 0, 5) : null,
        'hora_fim' => !empty($row['hora_fim']) ? substr((string)$row['hora_fim'], 0, 5) : null,
        'tempo_estimado' => isset($row['tempo_estimado']) ? (int)$row['tempo_estimado'] : null,
        'prioridade' => $row['prioridade'] ?? 'media',
        'status' => $status,
        'status_calculado' => $statusCalculado,
        'tempo_real_min' => isset($row['tempo_real_min']) ? (int)$row['tempo_real_min'] : 0,
        'sessao_ativa_id' => isset($row['sessao_ativa_id']) ? ((int)$row['sessao_ativa_id'] ?: null) : null,
        'metadata' => $metadata,
        'origem' => $row['origem'] ?? 'manual',
        'concluida_em' => $row['concluida_em'] ?? null,
        'criada_em' => $row['criada_em'] ?? null,
        'atualizada_em' => $row['atualizada_em'] ?? null,
    ];
}

function tarefasSelect(PDO $db, int $uid, string $where, array $params = []): array {
    $tempoExpr = tarefasTempoExpr($db);
    $planoFilter = tarefasPlanoAtivoFilter($db);
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $horaInicioExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_inicio') ? 'hora_inicio' : "NULL AS hora_inicio";
    $horaFimExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_fim') ? 'hora_fim' : "NULL AS hora_fim";
    $metadataExpr = dbColumnExists($db, 'tarefas_estudo', 'metadata_json') ? 'metadata_json' : "NULL AS metadata_json";
    $tempoRealExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_real_min')
        ? 'tempo_real_min'
        : (dbTableExists($db, 'sessoes_estudo')
            ? "(SELECT COALESCE(SUM(s.duracao_min), 0) FROM sessoes_estudo s WHERE s.tarefa_id = tarefas_estudo.id AND s.usuario_id = tarefas_estudo.usuario_id)"
            : '0');
    $sessaoExpr = dbTableExists($db, 'sessoes_estudo')
        ? "(SELECT s.id FROM sessoes_estudo s WHERE s.tarefa_id = tarefas_estudo.id AND s.usuario_id = tarefas_estudo.usuario_id AND s.fim IS NULL ORDER BY s.id DESC LIMIT 1)"
        : 'NULL';
    $sql = "
        SELECT id, usuario_id, plano_id, item_id, titulo, descricao, materia, {$conteudoExpr}, tipo, data_prevista,
               {$horaInicioExpr}, {$horaFimExpr},
               {$tempoExpr} AS tempo_estimado,
               prioridade, status, origem, {$metadataExpr}, {$tempoRealExpr} AS tempo_real_min,
               {$sessaoExpr} AS sessao_ativa_id,
               concluida_em, criada_em, atualizada_em
        FROM tarefas_estudo
        WHERE usuario_id = ? {$where} {$planoFilter}
        ORDER BY data_prevista ASC, hora_inicio ASC, FIELD(prioridade, 'alta', 'media', 'baixa'), id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$uid], $params));
    return array_map('tarefasMapRow', $stmt->fetchAll());
}

function tarefasLoadById(PDO $db, int $uid, int $id): ?array {
    $tarefas = tarefasSelect($db, $uid, 'AND id = ?', [$id]);
    return $tarefas[0] ?? null;
}

function tarefasResumo(PDO $db, int $uid): array {
    $today = new DateTimeImmutable('today');
    $weekStart = $today->modify('monday this week')->format('Y-m-d');
    $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
    $tempoExpr = tarefasTempoExpr($db);
    $tempoRealExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_real_min') ? 'tempo_real_min' : '0';
    $planoFilter = tarefasPlanoAtivoFilter($db);

    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN status <> 'cancelada' THEN 1 ELSE 0 END) AS total_semana,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas_semana,
            SUM(CASE WHEN status <> 'concluida' AND status <> 'cancelada' THEN 1 ELSE 0 END) AS pendentes_semana,
            SUM(CASE WHEN status = 'concluida' THEN {$tempoExpr} ELSE 0 END) AS minutos_concluidos_semana,
            SUM(CASE WHEN status <> 'cancelada' THEN {$tempoExpr} ELSE 0 END) AS minutos_planejados_semana,
            SUM(CASE WHEN status <> 'cancelada' THEN {$tempoRealExpr} ELSE 0 END) AS minutos_realizados_semana
        FROM tarefas_estudo
        WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ? {$planoFilter}
    ");
    $stmt->execute([$uid, $weekStart, $weekEnd]);
    $row = $stmt->fetch() ?: [];

    $atrasadas = $db->prepare("
        SELECT COUNT(*)
        FROM tarefas_estudo
        WHERE usuario_id = ?
          AND data_prevista < ?
          AND status IN ('pendente','em_andamento','adiada','remarcada')
          {$planoFilter}
    ");
    $atrasadas->execute([$uid, $today->format('Y-m-d')]);

    $total = (int)($row['total_semana'] ?? 0);
    $concluidas = (int)($row['concluidas_semana'] ?? 0);

    return [
        'tarefas' => [
            'total_semana' => $total,
            'concluidas_semana' => $concluidas,
            'pendentes_semana' => (int)($row['pendentes_semana'] ?? 0),
            'atrasadas' => (int)$atrasadas->fetchColumn(),
            'percentual_conclusao' => $total > 0 ? (int)round(($concluidas / $total) * 100) : 0,
        ],
        'tempo' => [
            'minutos_planejados_semana' => (int)($row['minutos_planejados_semana'] ?? 0),
            'minutos_concluidos_semana' => (int)($row['minutos_concluidos_semana'] ?? 0),
            'minutos_realizados_semana' => (int)($row['minutos_realizados_semana'] ?? 0),
        ],
    ];
}

function tarefasValidDate($value): string {
    $value = sanitizeTextValue($value, 10);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        jsonResponse(['erro' => 'Data invalida. Use YYYY-MM-DD.'], 400);
    }
    return $value;
}

function tarefasValidHour($value, bool $nullable = true): ?string {
    $value = sanitizeTextValue($value ?? '', 5);
    if ($value === '' && $nullable) {
        return null;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        jsonResponse(['erro' => 'Horario invalido. Use HH:MM.'], 400);
    }
    [$hour, $minute] = array_map('intval', explode(':', $value));
    if ($hour > 23 || $minute > 59) {
        jsonResponse(['erro' => 'Horario invalido. Use HH:MM.'], 400);
    }
    return $value;
}

function tarefasValidDuration($value): int {
    $duration = (int)$value;
    if ($duration <= 0 || $duration > 720) {
        jsonResponse(['erro' => 'A duracao deve ser positiva e menor que 12 horas.'], 400);
    }
    return $duration;
}

function tarefasValidateTimeWindow(?string $start, ?string $end): void {
    if ($start && $end && $end <= $start) {
        jsonResponse(['erro' => 'O horario final deve ser maior que o horario inicial.'], 400);
    }
}

function tarefasEventStatus(string $status, ?string $dataPrevista = null, ?string $tipo = null): string {
    if ($status === 'concluida') return 'concluido';
    if ($status === 'cancelada') return 'cancelado';
    if ($tipo === 'simulado' && $dataPrevista && $dataPrevista > date('Y-m-d')) return 'bloqueado';
    return 'pendente';
}

function tarefasItemStatus(string $status): string {
    if (in_array($status, ['concluida', 'cancelada', 'em_andamento'], true)) {
        return $status;
    }
    return 'pendente';
}

function tarefasSyncLinked(PDO $db, int $uid, array $tarefa, array $changes): void {
    if (!empty($tarefa['item_id']) && dbTableExists($db, 'plano_estudo_itens')) {
        $itemFields = [];
        $itemParams = [];
        $map = [
            'titulo' => 'titulo',
            'descricao' => 'descricao',
            'data_prevista' => 'data_prevista',
            'hora_inicio' => 'hora_inicio',
            'hora_fim' => 'hora_fim',
            'tempo_estimado' => 'tempo_estimado',
            'prioridade' => null,
            'status' => 'status',
        ];
        foreach ($changes as $field => $value) {
            $column = $map[$field] ?? null;
            if (!$column || !dbColumnExists($db, 'plano_estudo_itens', $column)) {
                continue;
            }
            $itemFields[] = "`{$column}` = ?";
            $itemParams[] = $field === 'status' ? tarefasItemStatus((string)$value) : $value;
        }
        if ($itemFields) {
            if (dbColumnExists($db, 'plano_estudo_itens', 'atualizado_em')) {
                $itemFields[] = 'atualizado_em = CURRENT_TIMESTAMP';
            }
            $itemParams[] = (int)$tarefa['item_id'];
            $itemParams[] = $uid;
            $db->prepare('UPDATE plano_estudo_itens SET ' . implode(', ', $itemFields) . ' WHERE id = ? AND usuario_id = ?')
                ->execute($itemParams);
        }
    }

    if (dbTableExists($db, 'eventos_calendario_estudai')) {
        $eventFields = [];
        $eventParams = [];
        $map = [
            'titulo' => 'titulo',
            'descricao' => 'descricao',
            'data_prevista' => 'data_evento',
            'hora_inicio' => 'hora_inicio',
            'hora_fim' => 'hora_fim',
            'status' => 'status',
        ];
        foreach ($changes as $field => $value) {
            $column = $map[$field] ?? null;
            if (!$column || !dbColumnExists($db, 'eventos_calendario_estudai', $column)) {
                continue;
            }
            $eventFields[] = "`{$column}` = ?";
            $eventParams[] = $field === 'status'
                ? tarefasEventStatus((string)$value, $changes['data_prevista'] ?? ($tarefa['data_prevista'] ?? null), $tarefa['tipo'] ?? null)
                : $value;
        }
        if (array_key_exists('tempo_estimado', $changes) || array_key_exists('prioridade', $changes)) {
            $loaded = tarefasLoadById($db, $uid, (int)$tarefa['id']) ?: $tarefa;
            $metadata = $loaded['metadata'] ?? [];
            if (array_key_exists('tempo_estimado', $changes)) $metadata['tempo_estimado'] = (int)$changes['tempo_estimado'];
            if (array_key_exists('prioridade', $changes)) $metadata['prioridade'] = $changes['prioridade'];
            if (dbColumnExists($db, 'eventos_calendario_estudai', 'metadata_json')) {
                $eventFields[] = 'metadata_json = ?';
                $eventParams[] = jsonEncodeSafe($metadata);
            }
        }
        if ($eventFields) {
            if (dbColumnExists($db, 'eventos_calendario_estudai', 'atualizado_em')) {
                $eventFields[] = 'atualizado_em = CURRENT_TIMESTAMP';
            }
            $eventParams[] = (int)$tarefa['id'];
            $eventParams[] = $uid;
            $db->prepare('UPDATE eventos_calendario_estudai SET ' . implode(', ', $eventFields) . ' WHERE tarefa_id = ? AND usuario_id = ?')
                ->execute($eventParams);
        }
    }
}

function tarefasApplyUpdate(PDO $db, int $uid, int $tarefaId, array $changes): array {
    $tarefa = tarefasLoadById($db, $uid, $tarefaId);
    if (!$tarefa) {
        jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
    }

    $fields = [];
    $params = [];
    foreach ($changes as $field => $value) {
        if ($field === 'tempo_estimado' && dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')) {
            $fields[] = 'tempo_estimado_min = ?';
            $params[] = $value;
        }
        if (!dbColumnExists($db, 'tarefas_estudo', $field)) {
            continue;
        }
        $fields[] = "`{$field}` = ?";
        $params[] = $value;
    }
    if (!$fields) {
        return $tarefa;
    }
    if (dbColumnExists($db, 'tarefas_estudo', 'atualizada_em')) {
        $fields[] = 'atualizada_em = CURRENT_TIMESTAMP';
    }
    $params[] = $tarefaId;
    $params[] = $uid;

    $db->beginTransaction();
    $db->prepare('UPDATE tarefas_estudo SET ' . implode(', ', $fields) . ' WHERE id = ? AND usuario_id = ?')
        ->execute($params);
    tarefasSyncLinked($db, $uid, $tarefa, $changes);
    $db->commit();

    return tarefasLoadById($db, $uid, $tarefaId) ?: $tarefa;
}

try {
    if (!dbTableExists($db, 'tarefas_estudo') || !dbColumnExists($db, 'tarefas_estudo', 'item_id')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    $today = new DateTimeImmutable('today');
    $todayStr = $today->format('Y-m-d');
    $weekStart = $today->modify('monday this week')->format('Y-m-d');
    $weekEnd = $today->modify('sunday this week')->format('Y-m-d');

    switch ($action) {
        case 'hoje':
            jsonResponse([
                'ok' => true,
                'data' => $todayStr,
                'tarefas' => tarefasSelect($db, $uid, "AND data_prevista = ? AND status <> 'cancelada'", [$todayStr]),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'semana':
            jsonResponse([
                'ok' => true,
                'data_inicio' => $weekStart,
                'data_fim' => $weekEnd,
                'tarefas' => tarefasSelect($db, $uid, "AND data_prevista BETWEEN ? AND ? AND status <> 'cancelada'", [$weekStart, $weekEnd]),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'atrasadas':
            jsonResponse([
                'ok' => true,
                'tarefas' => tarefasSelect($db, $uid, "AND data_prevista < ? AND status IN ('pendente','em_andamento','adiada','remarcada')", [$todayStr]),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'listar_por_data':
            $data = tarefasValidDate($_GET['data'] ?? $_POST['data'] ?? '');
            jsonResponse([
                'ok' => true,
                'data' => $data,
                'tarefas' => tarefasSelect($db, $uid, "AND data_prevista = ? AND status <> 'cancelada'", [$data]),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'recentes':
            $limite = min(20, max(4, (int)($_GET['limite'] ?? 8)));
            jsonResponse([
                'ok' => true,
                'tarefas' => array_slice(tarefasSelect($db, $uid, "AND status = 'concluida'", []), -$limite),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'concluir':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }

            $tarefa = tarefasLoadById($db, $uid, $tarefaId);
            if (!$tarefa) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }

            $stmt = $db->prepare("
                UPDATE tarefas_estudo
                SET status = 'concluida', concluida_em = CURRENT_TIMESTAMP, atualizada_em = CURRENT_TIMESTAMP
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$tarefaId, $uid]);

            if (!empty($tarefa['item_id']) && dbTableExists($db, 'plano_estudo_itens')) {
                $item = $db->prepare("
                    UPDATE plano_estudo_itens
                    SET status = 'concluida', atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = ? AND usuario_id = ?
                ");
                $item->execute([$tarefa['item_id'], $uid]);
            }
            if (dbTableExists($db, 'eventos_calendario_estudai')) {
                $db->prepare("
                    UPDATE eventos_calendario_estudai
                    SET status = 'concluido', atualizado_em = CURRENT_TIMESTAMP
                    WHERE tarefa_id = ? AND usuario_id = ?
                ")->execute([$tarefaId, $uid]);
            }

            jsonResponse([
                'ok' => true,
                'tarefa' => tarefasLoadById($db, $uid, $tarefaId),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'reabrir':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }

            $tarefa = tarefasLoadById($db, $uid, $tarefaId);
            if (!$tarefa) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }

            $stmt = $db->prepare("
                UPDATE tarefas_estudo
                SET status = 'pendente', concluida_em = NULL, atualizada_em = CURRENT_TIMESTAMP
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$tarefaId, $uid]);

            if (!empty($tarefa['item_id']) && dbTableExists($db, 'plano_estudo_itens')) {
                $item = $db->prepare("
                    UPDATE plano_estudo_itens
                    SET status = 'pendente', atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = ? AND usuario_id = ?
                ");
                $item->execute([$tarefa['item_id'], $uid]);
            }
            if (dbTableExists($db, 'eventos_calendario_estudai')) {
                $db->prepare("
                    UPDATE eventos_calendario_estudai
                    SET status = 'pendente', atualizado_em = CURRENT_TIMESTAMP
                    WHERE tarefa_id = ? AND usuario_id = ?
                ")->execute([$tarefaId, $uid]);
            }

            jsonResponse([
                'ok' => true,
                'tarefa' => tarefasLoadById($db, $uid, $tarefaId),
                'resumo' => tarefasResumo($db, $uid),
            ]);
            break;

        case 'em_andamento':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, ['status' => 'em_andamento']);
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'resumo' => tarefasResumo($db, $uid)]);
            break;

        case 'cancelar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, ['status' => 'cancelada', 'concluida_em' => null]);
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'resumo' => tarefasResumo($db, $uid)]);
            break;

        case 'adiar':
        case 'remarcar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(12000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $atual = tarefasLoadById($db, $uid, $tarefaId);
            if (!$atual) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }
            $data = $payload['data_prevista'] ?? null;
            if (!$data && $action === 'adiar') {
                $dias = min(30, max(1, (int)($payload['dias'] ?? 1)));
                $base = $atual['data_prevista'] ?: $todayStr;
                $data = (new DateTimeImmutable($base))->modify("+{$dias} days")->format('Y-m-d');
            }
            $data = tarefasValidDate($data ?? '');
            $horaInicio = array_key_exists('hora_inicio', $payload) ? tarefasValidHour($payload['hora_inicio']) : ($atual['hora_inicio'] ?? null);
            $horaFim = array_key_exists('hora_fim', $payload) ? tarefasValidHour($payload['hora_fim']) : ($atual['hora_fim'] ?? null);
            tarefasValidateTimeWindow($horaInicio, $horaFim);
            $changes = [
                'data_prevista' => $data,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'status' => $action === 'adiar' ? 'adiada' : 'remarcada',
            ];
            if (isset($payload['tempo_estimado'])) {
                $changes['tempo_estimado'] = tarefasValidDuration($payload['tempo_estimado']);
            }
            if (isset($payload['prioridade']) && in_array($payload['prioridade'], ['baixa', 'media', 'alta'], true)) {
                $changes['prioridade'] = $payload['prioridade'];
            }
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, $changes);
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'resumo' => tarefasResumo($db, $uid)]);
            break;

        case 'editar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(16000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $atual = tarefasLoadById($db, $uid, $tarefaId);
            if (!$atual) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }
            $changes = [];
            if (array_key_exists('titulo', $payload)) {
                $titulo = sanitizeTextValue($payload['titulo'], 160);
                if ($titulo === '') {
                    jsonResponse(['erro' => 'Informe um titulo para a tarefa.'], 400);
                }
                $changes['titulo'] = $titulo;
            }
            if (array_key_exists('descricao', $payload)) {
                $changes['descricao'] = sanitizeTextValue($payload['descricao'], 3000);
            }
            if (array_key_exists('data_prevista', $payload)) {
                $changes['data_prevista'] = tarefasValidDate($payload['data_prevista']);
            }
            if (array_key_exists('hora_inicio', $payload)) {
                $changes['hora_inicio'] = tarefasValidHour($payload['hora_inicio']);
            }
            if (array_key_exists('hora_fim', $payload)) {
                $changes['hora_fim'] = tarefasValidHour($payload['hora_fim']);
            }
            tarefasValidateTimeWindow($changes['hora_inicio'] ?? ($atual['hora_inicio'] ?? null), $changes['hora_fim'] ?? ($atual['hora_fim'] ?? null));
            if (array_key_exists('tempo_estimado', $payload)) {
                $changes['tempo_estimado'] = tarefasValidDuration($payload['tempo_estimado']);
            }
            if (array_key_exists('prioridade', $payload)) {
                $prioridade = sanitizeTextValue($payload['prioridade'], 20);
                if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
                    jsonResponse(['erro' => 'Prioridade invalida.'], 400);
                }
                $changes['prioridade'] = $prioridade;
            }
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, $changes);
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'resumo' => tarefasResumo($db, $uid)]);
            break;

        case 'iniciar_tempo':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            $tarefa = $tarefaId > 0 ? tarefasLoadById($db, $uid, $tarefaId) : null;
            if (!$tarefa) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }
            if (!dbTableExists($db, 'sessoes_estudo')) {
                $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, ['status' => 'em_andamento']);
                jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'aviso' => 'Controle detalhado de tempo ainda nao esta aplicado no banco.']);
            }
            $ativa = $db->prepare('SELECT id FROM sessoes_estudo WHERE usuario_id = ? AND tarefa_id = ? AND fim IS NULL ORDER BY id DESC LIMIT 1');
            $ativa->execute([$uid, $tarefaId]);
            $sessaoId = (int)($ativa->fetchColumn() ?: 0);
            if (!$sessaoId) {
                $db->prepare('INSERT INTO sessoes_estudo (usuario_id, tarefa_id, inicio) VALUES (?, ?, CURRENT_TIMESTAMP)')
                    ->execute([$uid, $tarefaId]);
                $sessaoId = (int)$db->lastInsertId();
            }
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, ['status' => 'em_andamento']);
            $tarefa['sessao_ativa_id'] = $sessaoId;
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'sessao_id' => $sessaoId]);
            break;

        case 'pausar_tempo':
        case 'finalizar_tempo':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            $tarefa = $tarefaId > 0 ? tarefasLoadById($db, $uid, $tarefaId) : null;
            if (!$tarefa) {
                jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
            }
            $minutos = 0;
            if (dbTableExists($db, 'sessoes_estudo')) {
                $stmt = $db->prepare('SELECT id, inicio FROM sessoes_estudo WHERE usuario_id = ? AND tarefa_id = ? AND fim IS NULL ORDER BY id DESC LIMIT 1');
                $stmt->execute([$uid, $tarefaId]);
                $sessao = $stmt->fetch();
                if ($sessao) {
                    $inicio = new DateTimeImmutable($sessao['inicio']);
                    $agora = new DateTimeImmutable('now');
                    $minutos = max(1, (int)ceil(($agora->getTimestamp() - $inicio->getTimestamp()) / 60));
                    $db->prepare('UPDATE sessoes_estudo SET fim = CURRENT_TIMESTAMP, duracao_min = ? WHERE id = ? AND usuario_id = ?')
                        ->execute([$minutos, (int)$sessao['id'], $uid]);
                }
            }
            if (dbColumnExists($db, 'tarefas_estudo', 'tempo_real_min') && $minutos > 0) {
                $db->prepare('UPDATE tarefas_estudo SET tempo_real_min = COALESCE(tempo_real_min, 0) + ?, atualizada_em = CURRENT_TIMESTAMP WHERE id = ? AND usuario_id = ?')
                    ->execute([$minutos, $tarefaId, $uid]);
            }
            $changes = $action === 'finalizar_tempo'
                ? ['status' => 'concluida', 'concluida_em' => date('Y-m-d H:i:s')]
                : ['status' => 'em_andamento'];
            $tarefa = tarefasApplyUpdate($db, $uid, $tarefaId, $changes);
            jsonResponse(['ok' => true, 'tarefa' => $tarefa, 'minutos_salvos' => $minutos, 'resumo' => tarefasResumo($db, $uid)]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('tarefas_estudo_api_error', $e);
    jsonResponse(['erro' => 'Nao foi possivel carregar as tarefas agora.'], 500);
}
