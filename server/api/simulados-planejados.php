<?php
// api/simulados-planejados.php - Simulados do plano usando a base aprovada de questoes

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

function simPlanJson($raw): array {
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function simPlanStatus(array $row): string {
    $status = $row['status'] ?? 'bloqueado';
    if ($status === 'bloqueado' && !empty($row['data_liberacao']) && $row['data_liberacao'] <= date('Y-m-d')) {
        return 'liberado';
    }
    return $status;
}

function simPlanQuestionItem(array $questao): array {
    $alternativas = [];
    foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
        $alternativas[$letra] = (string)($questao['alternativa_' . strtolower($letra)] ?? '');
    }
    return [
        'id' => 'q' . (int)$questao['id'],
        'questao_id' => (int)$questao['id'],
        'materia' => $questao['materia'] ?? '',
        'conteudo' => $questao['conteudo'] ?? '',
        'tipo' => 'multipla_escolha',
        'dificuldade' => $questao['dificuldade'] ?? 'medio',
        'fonte' => $questao['fonte'] ?? 'Base propria EstudAI',
        'pergunta' => $questao['enunciado'] ?? '',
        'alternativas' => $alternativas,
        'resposta_correta' => $questao['resposta_correta'] ?? '',
        'explicacao' => $questao['explicacao'] ?? '',
    ];
}

function simPlanMap(PDO $db, array $row, bool $withAnswers = false): array {
    $questoes = simPlanJson($row['questoes_json'] ?? '');
    $respostas = [];
    if ($withAnswers) {
        $stmt = $db->prepare('
            SELECT question_key, resposta_usuario, resposta_marcada, acertou, avaliacao_json, respondido_em
            FROM simulados_planejados_respostas
            WHERE usuario_id = ? AND simulado_planejado_id = ?
        ');
        $stmt->execute([(int)$row['usuario_id'], (int)$row['id']]);
        foreach ($stmt->fetchAll() as $resp) {
            $respostas[$resp['question_key']] = [
                'resposta_usuario' => $resp['resposta_usuario'],
                'resposta_marcada' => $resp['resposta_marcada'],
                'acertou' => $resp['acertou'] === null ? null : (bool)$resp['acertou'],
                'avaliacao' => simPlanJson($resp['avaliacao_json'] ?? ''),
                'respondido_em' => $resp['respondido_em'],
            ];
        }
    }

    return [
        'id' => (int)$row['id'],
        'plano_id' => $row['plano_id'] ? (int)$row['plano_id'] : null,
        'tarefa_id' => $row['tarefa_id'] ? (int)$row['tarefa_id'] : null,
        'titulo' => $row['titulo'],
        'descricao' => $row['descricao'],
        'materia' => $row['materia'],
        'conteudos' => simPlanJson($row['conteudos_json'] ?? ''),
        'questoes' => $questoes['questoes'] ?? $questoes,
        'aviso' => $questoes['aviso'] ?? null,
        'data_liberacao' => $row['data_liberacao'],
        'status' => simPlanStatus($row),
        'origem' => $row['origem'],
        'criado_em' => $row['criado_em'],
        'atualizado_em' => $row['atualizado_em'],
        'respostas' => $respostas,
    ];
}

function simPlanReleaseDue(PDO $db, int $uid): void {
    $db->prepare("
        UPDATE simulados_planejados
        SET status = 'liberado', atualizado_em = CURRENT_TIMESTAMP
        WHERE usuario_id = ?
          AND status = 'bloqueado'
          AND data_liberacao <= CURDATE()
    ")->execute([$uid]);
}

function simPlanLoad(PDO $db, int $uid, int $id): ?array {
    simPlanReleaseDue($db, $uid);
    $stmt = $db->prepare('SELECT * FROM simulados_planejados WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$id, $uid]);
    return $stmt->fetch() ?: null;
}

function simPlanLoadTarefa(PDO $db, int $uid, int $tarefaId): ?array {
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $stmt = $db->prepare("
        SELECT id, plano_id, titulo, descricao, materia, {$conteudoExpr}, tipo, data_prevista
        FROM tarefas_estudo
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tarefaId, $uid]);
    return $stmt->fetch() ?: null;
}

function simPlanFindByTask(PDO $db, int $uid, int $tarefaId): ?array {
    simPlanReleaseDue($db, $uid);
    $stmt = $db->prepare("
        SELECT *
        FROM simulados_planejados
        WHERE usuario_id = ? AND tarefa_id = ? AND status <> 'arquivado'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid, $tarefaId]);
    $row = $stmt->fetch();
    return $row ? simPlanMap($db, $row, true) : null;
}

function simPlanActiveWeeklyPlan(PDO $db, int $uid): ?array {
    $tipoFilter = dbColumnExists($db, 'planos_estudo', 'tipo_plano') ? "AND tipo_plano = 'semanal'" : '';
    $stmt = $db->prepare("
        SELECT *
        FROM planos_estudo
        WHERE usuario_id = ? AND status = 'ativo' {$tipoFilter}
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: null;
}

function simPlanFindWeek(PDO $db, int $uid, int $planoId, string $inicio, string $fim): ?array {
    $stmt = $db->prepare("
        SELECT *
        FROM simulados_planejados
        WHERE usuario_id = ?
          AND plano_id = ?
          AND tarefa_id IS NULL
          AND data_liberacao BETWEEN ? AND ?
          AND status <> 'arquivado'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid, $planoId, $inicio, $fim]);
    $row = $stmt->fetch();
    return $row ? simPlanMap($db, $row, true) : null;
}

function simPlanQuestionsFromBase(PDO $db, array $conteudosBase, int $quantidade): array {
    $selected = [];
    $seen = [];
    $avisos = [];
    $perContent = max(1, (int)ceil($quantidade / max(1, count($conteudosBase))));

    foreach ($conteudosBase as $item) {
        $resultado = questoesBuscarAprovadas($db, $item['materia'] ?? null, $item['conteudo'] ?? null, $perContent);
        if (!empty($resultado['aviso'])) {
            $avisos[] = $resultado['aviso'];
        }
        foreach ($resultado['questoes'] as $questao) {
            $id = (int)$questao['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $selected[] = $questao;
            }
            if (count($selected) >= $quantidade) {
                break 2;
            }
        }
    }

    if (count($selected) < $quantidade) {
        $resultado = questoesBuscarAprovadas($db, null, null, $quantidade - count($selected));
        if (!empty($resultado['aviso'])) {
            $avisos[] = $resultado['aviso'];
        }
        foreach ($resultado['questoes'] as $questao) {
            $id = (int)$questao['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $selected[] = $questao;
            }
            if (count($selected) >= $quantidade) {
                break;
            }
        }
    }

    return [
        'questoes' => array_slice($selected, 0, $quantidade),
        'aviso' => $avisos ? $avisos[0] : null,
    ];
}

function simPlanConteudosSemana(PDO $db, int $uid, int $planoId, string $inicio, string $fim): array {
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : 'titulo';
    $stmt = $db->prepare("
        SELECT DISTINCT COALESCE(NULLIF(materia, ''), 'Geral') AS materia,
               COALESCE(NULLIF({$conteudoExpr}, ''), titulo) AS conteudo
        FROM tarefas_estudo
        WHERE usuario_id = ?
          AND plano_id = ?
          AND data_prevista BETWEEN ? AND ?
          AND tipo IN ('teoria','questoes','revisao','resumo','misto','simulado')
        ORDER BY data_prevista ASC, id ASC
        LIMIT 20
    ");
    $stmt->execute([$uid, $planoId, $inicio, $fim]);
    return array_values(array_filter(array_map(static function ($row) {
        $conteudo = trim((string)($row['conteudo'] ?? ''));
        return $conteudo === '' ? null : ['materia' => $row['materia'] ?: 'Geral', 'conteudo' => $conteudo];
    }, $stmt->fetchAll())));
}

function simPlanInsert(PDO $db, int $uid, ?int $planoId, ?int $tarefaId, string $titulo, string $descricao, string $materia, array $conteudosBase, array $questoes, string $dataLiberacao, ?string $aviso): array {
    if (count($questoes) < 5) {
        jsonResponse(['erro' => 'Não encontramos questões suficientes para este conteúdo ainda.'], 404);
    }

    $payload = [
        'origem' => 'banco',
        'aviso' => $aviso,
        'questoes' => array_map('simPlanQuestionItem', $questoes),
    ];

    $stmt = $db->prepare('
        INSERT INTO simulados_planejados
            (usuario_id, plano_id, tarefa_id, titulo, descricao, materia, conteudos_json, questoes_json,
             data_liberacao, status, origem)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uid,
        $planoId,
        $tarefaId,
        $titulo,
        $descricao,
        $materia,
        jsonEncodeSafe($conteudosBase),
        jsonEncodeSafe($payload),
        $dataLiberacao,
        $dataLiberacao <= date('Y-m-d') ? 'liberado' : 'bloqueado',
        dbOriginValue($db, 'simulados_planejados'),
    ]);

    $created = simPlanLoad($db, $uid, (int)$db->lastInsertId());
    if (!$created) {
        jsonResponse(['erro' => 'Simulado criado, mas nao foi possivel carregar o registro.'], 500);
    }
    return simPlanMap($db, $created, true);
}

function simPlanCreateForWeek(PDO $db, int $uid): array {
    $plano = simPlanActiveWeeklyPlan($db, $uid);
    if (!$plano) {
        jsonResponse(['erro' => 'Gere um plano semanal antes do simulado.'], 400);
    }
    $inicio = $plano['semana_inicio'] ?? $plano['data_inicio'] ?? date('Y-m-d');
    $fim = $plano['semana_fim'] ?? $plano['data_fim'] ?? (new DateTimeImmutable($inicio))->modify('+6 days')->format('Y-m-d');
    $existing = simPlanFindWeek($db, $uid, (int)$plano['id'], $inicio, $fim);
    if ($existing) {
        return $existing + ['existente' => true];
    }

    $conteudosBase = simPlanConteudosSemana($db, $uid, (int)$plano['id'], $inicio, $fim);
    if (count($conteudosBase) < 2) {
        jsonResponse(['erro' => 'Ainda nao ha conteudo suficiente da semana para liberar um simulado.'], 400);
    }

    $resultado = simPlanQuestionsFromBase($db, $conteudosBase, min(12, max(6, count($conteudosBase) * 2)));
    return simPlanInsert(
        $db,
        $uid,
        (int)$plano['id'],
        null,
        'Simulado da Semana',
        'Simulado montado com questões aprovadas da base EstudAI.',
        'Geral',
        $conteudosBase,
        $resultado['questoes'],
        $fim,
        $resultado['aviso']
    );
}

function simPlanCreateForTask(PDO $db, int $uid, int $tarefaId): array {
    $existing = simPlanFindByTask($db, $uid, $tarefaId);
    if ($existing) {
        return $existing + ['existente' => true];
    }

    $tarefa = simPlanLoadTarefa($db, $uid, $tarefaId);
    if (!$tarefa) {
        jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
    }

    $conteudoBase = $tarefa['conteudo'] ?: $tarefa['titulo'];
    $conteudosBase = [[
        'materia' => $tarefa['materia'] ?: 'Geral',
        'conteudo' => $conteudoBase,
    ]];
    $resultado = simPlanQuestionsFromBase($db, $conteudosBase, 10);
    return simPlanInsert(
        $db,
        $uid,
        $tarefa['plano_id'] ? (int)$tarefa['plano_id'] : null,
        (int)$tarefa['id'],
        'Simulado planejado',
        'Simulado montado com questões aprovadas da base EstudAI.',
        sanitizeTextValue($tarefa['materia'] ?: 'Geral', 80),
        $conteudosBase,
        $resultado['questoes'],
        $tarefa['data_prevista'] ?: date('Y-m-d'),
        $resultado['aviso']
    );
}

try {
    if (!dbTableExists($db, 'simulados_planejados') || !dbTableExists($db, 'simulados_planejados_respostas')) {
        jsonResponse(['erro' => 'Banco incompleto: tabelas de simulados planejados ausentes. Aplique database/schema.sql.'], 503);
    }

    simPlanReleaseDue($db, $uid);

    switch ($action) {
        case 'listar':
            $status = sanitizeTextValue($_GET['status'] ?? '', 20);
            $where = '';
            $params = [$uid];
            if ($status !== '' && in_array($status, ['bloqueado', 'liberado', 'iniciado', 'finalizado', 'arquivado'], true)) {
                $where = 'AND status = ?';
                $params[] = $status;
            }
            $stmt = $db->prepare("
                SELECT *
                FROM simulados_planejados
                WHERE usuario_id = ? {$where}
                ORDER BY data_liberacao ASC, id ASC
                LIMIT 80
            ");
            $stmt->execute($params);
            jsonResponse(['ok' => true, 'simulados' => array_map(fn($row) => simPlanMap($db, $row), $stmt->fetchAll())]);
            break;

        case 'carregar':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(['erro' => 'Simulado nao informado.'], 400);
            }
            $row = simPlanLoad($db, $uid, $id);
            if (!$row) {
                jsonResponse(['erro' => 'Simulado nao encontrado.'], 404);
            }
            jsonResponse(['ok' => true, 'simulado' => simPlanMap($db, $row, true)]);
            break;

        case 'gerar_para_tarefa':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            jsonResponse(['ok' => true, 'simulado' => simPlanCreateForTask($db, $uid, $tarefaId)]);
            break;

        case 'gerar_para_semana':
            requirePost();
            validateCsrfToken();
            jsonResponse(['ok' => true, 'simulado' => simPlanCreateForWeek($db, $uid)]);
            break;

        case 'iniciar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $id = (int)($payload['simulado_id'] ?? 0);
            $row = $id > 0 ? simPlanLoad($db, $uid, $id) : null;
            if (!$row) {
                jsonResponse(['erro' => 'Simulado nao encontrado.'], 404);
            }
            $status = simPlanStatus($row);
            if ($status === 'bloqueado') {
                jsonResponse(['erro' => 'Simulado liberado em ' . date('d/m/Y', strtotime($row['data_liberacao'])) . '.'], 403);
            }
            if ($status === 'liberado') {
                $db->prepare("UPDATE simulados_planejados SET status = 'iniciado', atualizado_em = CURRENT_TIMESTAMP WHERE id = ? AND usuario_id = ?")
                    ->execute([$id, $uid]);
            }
            $updated = simPlanLoad($db, $uid, $id);
            jsonResponse(['ok' => true, 'simulado' => simPlanMap($db, $updated, true)]);
            break;

        case 'responder':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(12000);
            $id = (int)($payload['simulado_planejado_id'] ?? 0);
            $key = sanitizeTextValue($payload['question_key'] ?? '', 80);
            $row = $id > 0 ? simPlanLoad($db, $uid, $id) : null;
            if (!$row || $key === '') {
                jsonResponse(['erro' => 'Questao de simulado invalida.'], 400);
            }
            if (simPlanStatus($row) === 'bloqueado') {
                jsonResponse(['erro' => 'Simulado ainda bloqueado.'], 403);
            }
            $questoes = simPlanMap($db, $row, false)['questoes'];
            $questao = null;
            foreach ($questoes as $item) {
                if (($item['id'] ?? '') === $key) {
                    $questao = $item;
                    break;
                }
            }
            if (!$questao) {
                jsonResponse(['erro' => 'Questao nao encontrada.'], 404);
            }
            $marcada = strtoupper(sanitizeTextValue($payload['resposta_marcada'] ?? '', 20));
            if (!in_array($marcada, ['A', 'B', 'C', 'D', 'E'], true)) {
                jsonResponse(['erro' => 'Resposta invalida.'], 400);
            }
            $acertou = strtoupper((string)($questao['resposta_correta'] ?? '')) === $marcada;
            $avaliacao = ['explicacao' => $questao['explicacao'] ?? '', 'avaliado_por' => 'base_questoes'];
            $db->prepare('DELETE FROM simulados_planejados_respostas WHERE usuario_id = ? AND simulado_planejado_id = ? AND question_key = ?')
                ->execute([$uid, $id, $key]);
            $db->prepare('
                INSERT INTO simulados_planejados_respostas
                    (usuario_id, simulado_planejado_id, question_key, resposta_usuario, resposta_marcada, acertou, avaliacao_json)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ')->execute([$uid, $id, $key, sanitizeTextValue($payload['resposta_usuario'] ?? '', 2000), $marcada, $acertou ? 1 : 0, jsonEncodeSafe($avaliacao)]);
            jsonResponse(['ok' => true, 'acertou' => $acertou, 'avaliacao' => $avaliacao]);
            break;

        case 'finalizar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $id = (int)($payload['simulado_id'] ?? 0);
            $row = $id > 0 ? simPlanLoad($db, $uid, $id) : null;
            if (!$row) {
                jsonResponse(['erro' => 'Simulado nao encontrado.'], 404);
            }
            $mapped = simPlanMap($db, $row, true);
            $total = count($mapped['questoes']);
            $respondidas = count($mapped['respostas']);
            $acertos = 0;
            foreach ($mapped['respostas'] as $resp) {
                if ($resp['acertou']) {
                    $acertos++;
                }
            }
            $db->prepare("UPDATE simulados_planejados SET status = 'finalizado', atualizado_em = CURRENT_TIMESTAMP WHERE id = ? AND usuario_id = ?")
                ->execute([$id, $uid]);
            jsonResponse([
                'ok' => true,
                'resultado' => [
                    'total' => $total,
                    'respondidas' => $respondidas,
                    'acertos' => $acertos,
                    'erros' => max(0, $respondidas - $acertos),
                    'percentual' => $respondidas > 0 ? round(($acertos / $respondidas) * 100, 1) : 0,
                ],
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('simulados_planejados', $e);
    jsonResponse(['erro' => 'Nao foi possivel processar simulados planejados agora.'], 500);
}
