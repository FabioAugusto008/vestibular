<?php
// api/revisoes-ia.php - Revisoes por conteudo alinhadas ao plano

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'carregar_semana';

function revisoesIaJson($raw): array {
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function revisoesIaWeekStart(?string $date = null): string {
    $date = $date ?: date('Y-m-d');
    return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
}

function revisoesIaMap(array $row): array {
    return [
        'id' => (int)$row['id'],
        'usuario_id' => (int)$row['usuario_id'],
        'tarefa_id' => $row['tarefa_id'] ? (int)$row['tarefa_id'] : null,
        'plano_id' => $row['plano_id'] ? (int)$row['plano_id'] : null,
        'materia' => $row['materia'],
        'conteudo' => $row['conteudo'],
        'revisao' => revisoesIaJson($row['revisao_json']),
        'origem' => $row['origem'],
        'criado_em' => $row['criado_em'],
    ];
}

function revisoesIaLoadTarefa(PDO $db, int $uid, int $tarefaId): ?array {
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $stmt = $db->prepare("
        SELECT id, plano_id, titulo, descricao, materia, {$conteudoExpr}, tipo, data_prevista, status
        FROM tarefas_estudo
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tarefaId, $uid]);
    return $stmt->fetch() ?: null;
}

function revisoesIaErrosDaTarefa(PDO $db, int $uid, int $tarefaId): array {
    if (!dbTableExists($db, 'exercicios_planejados') || !dbTableExists($db, 'respostas_exercicios_planejados')) {
        return [];
    }
    $motivoErroExpr = dbColumnExists($db, 'respostas_exercicios_planejados', 'motivo_erro')
        ? 'rep.motivo_erro'
        : 'NULL AS motivo_erro';
    $stmt = $db->prepare("
        SELECT ep.exercicios_json, rep.exercise_key, rep.resposta_marcada, rep.resposta_usuario, {$motivoErroExpr}
        FROM respostas_exercicios_planejados rep
        INNER JOIN exercicios_planejados ep ON ep.id = rep.exercicio_planejado_id
        WHERE rep.usuario_id = ?
          AND ep.tarefa_id = ?
          AND rep.acertou = 0
        ORDER BY rep.respondido_em DESC
        LIMIT 8
    ");
    $stmt->execute([$uid, $tarefaId]);
    $erros = [];
    foreach ($stmt->fetchAll() as $row) {
        $items = revisoesIaJson($row['exercicios_json'])['exercicios'] ?? [];
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $row['exercise_key']) {
                $erros[] = [
                    'materia' => $item['materia'] ?? '',
                    'conteudo' => $item['conteudo'] ?? '',
                    'pergunta' => $item['pergunta'] ?? '',
                    'resposta_correta' => $item['resposta_correta'] ?? '',
                    'resposta_marcada' => $row['resposta_marcada'],
                    'motivo_erro' => $row['motivo_erro'] ?? null,
                    'explicacao' => $item['explicacao'] ?? '',
                ];
                break;
            }
        }
    }
    return $erros;
}

function revisoesIaFindByTask(PDO $db, int $uid, int $tarefaId): ?array {
    $stmt = $db->prepare("
        SELECT *
        FROM revisoes_conteudo_ia
        WHERE usuario_id = ? AND tarefa_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid, $tarefaId]);
    $row = $stmt->fetch();
    return $row ? revisoesIaMap($row) : null;
}

function revisoesIaCreateForTask(PDO $db, int $uid, int $tarefaId): array {
    $tarefa = revisoesIaLoadTarefa($db, $uid, $tarefaId);
    if (!$tarefa) {
        jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
    }
    $materia = sanitizeTextValue($tarefa['materia'] ?: 'Geral', 80);
    $conteudo = sanitizeTextValue($tarefa['conteudo'] ?: $tarefa['titulo'], 180);
    $erros = revisoesIaErrosDaTarefa($db, $uid, $tarefaId);
    if (!$erros) {
        jsonResponse(['erro' => 'Ainda nao ha erros registrados nessa tarefa para montar uma revisao pela base de questoes.'], 404);
    }

    $revisao = [
        'materia' => $materia,
        'conteudo' => $conteudo,
        'resumo_revisao' => 'Revisao montada a partir das questoes respondidas incorretamente na base aprovada.',
        'pontos_importantes' => [],
        'erros_comuns' => [],
        'exemplo_resolvido' => '',
        'questoes_revisao' => array_map(static function ($erro) {
            return [
                'materia' => $erro['materia'] ?? '',
                'conteudo' => $erro['conteudo'] ?? '',
                'pergunta' => $erro['pergunta'] ?? '',
                'resposta' => 'Gabarito: ' . ($erro['resposta_correta'] ?? ''),
                'resposta_marcada' => $erro['resposta_marcada'] ?? '',
                'motivo_erro' => $erro['motivo_erro'] ?? null,
                'explicacao' => $erro['explicacao'] ?? '',
            ];
        }, $erros),
    ];
    $stmt = $db->prepare('
        INSERT INTO revisoes_conteudo_ia
            (usuario_id, tarefa_id, plano_id, materia, conteudo, revisao_json, origem)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uid,
        (int)$tarefa['id'],
        $tarefa['plano_id'] ?: null,
        $materia,
        $conteudo,
        jsonEncodeSafe($revisao),
        dbEnumAllows($db, 'revisoes_conteudo_ia', 'origem', 'banco') ? 'banco' : 'ia',
    ]);
    return revisoesIaFindByTask($db, $uid, $tarefaId) ?: [];
}

try {
    if (!dbTableExists($db, 'revisoes_conteudo_ia')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'carregar_por_tarefa':
            $tarefaId = (int)($_GET['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            jsonResponse(['ok' => true, 'revisao' => revisoesIaFindByTask($db, $uid, $tarefaId)]);
            break;

        case 'gerar_por_tarefa':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            jsonResponse(['ok' => true, 'revisao' => revisoesIaCreateForTask($db, $uid, $tarefaId)]);
            break;

        case 'carregar_semana':
            $week = revisoesIaWeekStart($_GET['semana_inicio'] ?? date('Y-m-d'));
            $end = (new DateTimeImmutable($week))->modify('+6 days')->format('Y-m-d');
            $stmt = $db->prepare("
                SELECT r.*
                FROM revisoes_conteudo_ia r
                LEFT JOIN tarefas_estudo t ON t.id = r.tarefa_id
                WHERE r.usuario_id = ?
                  AND (DATE(r.criado_em) BETWEEN ? AND ? OR t.data_prevista BETWEEN ? AND ?)
                ORDER BY r.criado_em DESC
                LIMIT 40
            ");
            $stmt->execute([$uid, $week, $end, $week, $end]);
            jsonResponse(['ok' => true, 'semana_inicio' => $week, 'semana_fim' => $end, 'revisoes' => array_map('revisoesIaMap', $stmt->fetchAll())]);
            break;

        case 'gerar_semana':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $week = revisoesIaWeekStart($payload['semana_inicio'] ?? date('Y-m-d'));
            $end = (new DateTimeImmutable($week))->modify('+6 days')->format('Y-m-d');
            $stmt = $db->prepare("
                SELECT id
                FROM tarefas_estudo
                WHERE usuario_id = ?
                  AND data_prevista BETWEEN ? AND ?
                  AND status = 'concluida'
                  AND COALESCE(NULLIF(conteudo, ''), titulo) <> ''
                ORDER BY concluida_em DESC, id DESC
                LIMIT 8
            ");
            $stmt->execute([$uid, $week, $end]);
            $revisoes = [];
            foreach ($stmt->fetchAll() as $row) {
                $existente = revisoesIaFindByTask($db, $uid, (int)$row['id']);
                $revisoes[] = $existente ?: revisoesIaCreateForTask($db, $uid, (int)$row['id']);
            }
            jsonResponse(['ok' => true, 'semana_inicio' => $week, 'semana_fim' => $end, 'revisoes' => $revisoes]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['erro' => 'Nao foi possivel processar revisoes agora.'], 500);
}
