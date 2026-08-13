<?php
// api/exercicios-ia.php - Exercicios vindos da base aprovada de questoes

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'carregar_semana';

function exerciciosWeekStart(?string $date = null): string {
    $date = $date ?: date('Y-m-d');
    return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
}

function exerciciosParseJson($raw): array {
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function exerciciosQuestionToItem(array $questao): array {
    $alternativas = [];
    foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
        $alternativas[$letra] = (string)($questao['alternativa_' . strtolower($letra)] ?? '');
    }

    return [
        'id' => 'q' . (int)$questao['id'],
        'questao_id' => (int)$questao['id'],
        'tipo' => 'multipla_escolha',
        'materia' => $questao['materia'] ?? '',
        'conteudo' => $questao['conteudo'] ?? '',
        'dificuldade' => $questao['dificuldade'] ?? 'medio',
        'fonte' => $questao['fonte'] ?? 'Base propria EstudAI',
        'ano' => $questao['ano'] ?? null,
        'prova' => $questao['prova'] ?? null,
        'pergunta' => $questao['enunciado'] ?? '',
        'alternativas' => $alternativas,
        'resposta_correta' => $questao['resposta_correta'] ?? '',
        'explicacao' => $questao['explicacao'] ?? '',
    ];
}

function exerciciosMapLote(PDO $db, array $row): array {
    $motivoErroExpr = dbColumnExists($db, 'respostas_exercicios_planejados', 'motivo_erro')
        ? 'motivo_erro'
        : 'NULL AS motivo_erro';
    $respostasStmt = $db->prepare('
        SELECT exercise_key, resposta_usuario, resposta_marcada, acertou, avaliacao_json, ' . $motivoErroExpr . ', respondido_em
        FROM respostas_exercicios_planejados
        WHERE exercicio_planejado_id = ? AND usuario_id = ?
    ');
    $respostasStmt->execute([(int)$row['id'], (int)$row['usuario_id']]);
    $respostas = [];
    foreach ($respostasStmt->fetchAll() as $resp) {
        $respostas[$resp['exercise_key']] = [
            'resposta_usuario' => $resp['resposta_usuario'],
            'resposta_marcada' => $resp['resposta_marcada'],
            'acertou' => $resp['acertou'] === null ? null : (bool)$resp['acertou'],
            'avaliacao' => exerciciosParseJson($resp['avaliacao_json'] ?? ''),
            'motivo_erro' => $resp['motivo_erro'] ?? null,
            'respondido_em' => $resp['respondido_em'],
        ];
    }
    $payload = exerciciosParseJson($row['exercicios_json']);
    return [
        'id' => (int)$row['id'],
        'plano_id' => $row['plano_id'] ? (int)$row['plano_id'] : null,
        'tarefa_id' => $row['tarefa_id'] ? (int)$row['tarefa_id'] : null,
        'semana_inicio' => $row['semana_inicio'],
        'materia' => $row['materia'],
        'conteudo' => $row['conteudo'],
        'nivel' => $row['nivel'],
        'quantidade' => (int)$row['quantidade'],
        'origem' => $row['origem'],
        'status' => $row['status'],
        'aviso' => $payload['aviso'] ?? null,
        'exercicios' => $payload['exercicios'] ?? [],
        'respostas' => $respostas,
    ];
}

function exerciciosLoadTarefa(PDO $db, int $uid, int $tarefaId): ?array {
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $stmt = $db->prepare("
        SELECT id, plano_id, titulo, descricao, materia, {$conteudoExpr}, tipo, data_prevista, tempo_estimado
        FROM tarefas_estudo
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tarefaId, $uid]);
    return $stmt->fetch() ?: null;
}

function exerciciosQuantidadeFromPerfil(PDO $db, int $uid): int {
    $stmt = $db->prepare('SELECT perfil_json FROM estudo_perfis WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $perfil = exerciciosParseJson($stmt->fetchColumn() ?: '');
    $choice = $perfil['exercicios_dia'] ?? $perfil['perfil_avancado']['exercicios_dia'] ?? '6_10';
    return match ($choice) {
        '3_5' => 5,
        '11_20' => 12,
        'mais_20' => 15,
        default => 8,
    };
}

function exerciciosFindLote(PDO $db, int $uid, ?int $tarefaId, ?string $semanaInicio): ?array {
    if ($tarefaId) {
        $stmt = $db->prepare("
            SELECT *
            FROM exercicios_planejados
            WHERE usuario_id = ? AND tarefa_id = ? AND status = 'ativo'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$uid, $tarefaId]);
    } else {
        $stmt = $db->prepare("
            SELECT *
            FROM exercicios_planejados
            WHERE usuario_id = ? AND semana_inicio = ? AND status = 'ativo'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$uid, $semanaInicio]);
    }
    $row = $stmt->fetch();
    return $row ? exerciciosMapLote($db, $row) : null;
}

function exerciciosCreateForTarefa(PDO $db, int $uid, int $tarefaId): array {
    $tarefa = exerciciosLoadTarefa($db, $uid, $tarefaId);
    if (!$tarefa) {
        jsonResponse(['erro' => 'Tarefa nao encontrada.'], 404);
    }

    $materia = sanitizeTextValue($tarefa['materia'] ?: 'Geral', 80);
    $conteudo = sanitizeTextValue($tarefa['conteudo'] ?: $tarefa['titulo'], 180);
    $quantidade = exerciciosQuantidadeFromPerfil($db, $uid);
    $resultado = questoesBuscarAprovadas($db, $materia, $conteudo, $quantidade);
    $questoes = $resultado['questoes'];

    if (!$questoes) {
        jsonResponse(['erro' => 'Não encontramos questões suficientes para este conteúdo ainda.'], 404);
    }

    $items = array_map('exerciciosQuestionToItem', $questoes);
    $week = exerciciosWeekStart($tarefa['data_prevista'] ?: date('Y-m-d'));
    $payload = [
        'origem' => 'banco',
        'escopo' => $resultado['escopo'],
        'aviso' => $resultado['aviso'],
        'exercicios' => $items,
    ];

    $stmt = $db->prepare('
        INSERT INTO exercicios_planejados
            (usuario_id, plano_id, tarefa_id, semana_inicio, materia, conteudo, nivel, quantidade, exercicios_json, origem, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uid,
        $tarefa['plano_id'] ?: null,
        (int)$tarefa['id'],
        $week,
        $materia,
        $conteudo,
        'misto',
        count($items),
        jsonEncodeSafe($payload),
        dbOriginValue($db, 'exercicios_planejados'),
        'ativo',
    ]);

    return exerciciosFindLote($db, $uid, (int)$tarefa['id'], null) ?: [];
}

function exerciciosSalvarRespostaBase(PDO $db, int $uid, int $questaoId, string $marcada, bool $acertou, ?int $tempo = null): void {
    if (!dbTableExists($db, 'respostas_usuario')) {
        return;
    }
    $alternativaId = questoesAlternativaIdPorLetra($db, $questaoId, $marcada);
    if (dbColumnExists($db, 'respostas_usuario', 'alternativa_id')) {
        $sql = '
            INSERT INTO respostas_usuario
                (usuario_id, questao_id, alternativa_id, data, resposta_marcada, correta, acertou, tempo_resposta)
            VALUES
                (?, ?, ?, CURDATE(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                alternativa_id = VALUES(alternativa_id),
                resposta_marcada = VALUES(resposta_marcada),
                correta = VALUES(correta),
                acertou = VALUES(acertou),
                tempo_resposta = VALUES(tempo_resposta)
        ';
        $db->prepare($sql)->execute([$uid, $questaoId, $alternativaId, $marcada, $acertou ? 1 : 0, $acertou ? 1 : 0, $tempo]);
        questoesSalvarRespostaModelo($db, $uid, $questaoId, $alternativaId, $acertou, $tempo);
        return;
    }

    $db->prepare('
        INSERT INTO respostas_usuario (usuario_id, questao_id, data, resposta_marcada, acertou)
        VALUES (?, ?, CURDATE(), ?, ?)
        ON DUPLICATE KEY UPDATE resposta_marcada = VALUES(resposta_marcada), acertou = VALUES(acertou)
    ')->execute([$uid, $questaoId, $marcada, $acertou ? 1 : 0]);
    questoesSalvarRespostaModelo($db, $uid, $questaoId, $alternativaId, $acertou, $tempo);
}

try {
    if (!dbTableExists($db, 'exercicios_planejados') || !dbTableExists($db, 'respostas_exercicios_planejados')) {
        jsonResponse(['erro' => 'Banco incompleto: tabelas de exercicios planejados ausentes. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'carregar_por_tarefa':
            $tarefaId = (int)($_GET['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $lote = exerciciosFindLote($db, $uid, $tarefaId, null);
            if (!$lote) {
                $lote = exerciciosCreateForTarefa($db, $uid, $tarefaId);
            }
            jsonResponse(['ok' => true, 'lote' => $lote, 'aviso' => $lote['aviso'] ?? null]);
            break;

        case 'gerar_por_tarefa':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $tarefaId = (int)($payload['tarefa_id'] ?? 0);
            if ($tarefaId <= 0) {
                jsonResponse(['erro' => 'Tarefa nao informada.'], 400);
            }
            $db->prepare("UPDATE exercicios_planejados SET status = 'arquivado' WHERE usuario_id = ? AND tarefa_id = ? AND status = 'ativo'")
                ->execute([$uid, $tarefaId]);
            $lote = exerciciosCreateForTarefa($db, $uid, $tarefaId);
            jsonResponse(['ok' => true, 'lote' => $lote, 'aviso' => $lote['aviso'] ?? null]);
            break;

        case 'carregar_semana':
            $week = exerciciosWeekStart($_GET['semana_inicio'] ?? date('Y-m-d'));
            $stmt = $db->prepare("
                SELECT *
                FROM exercicios_planejados
                WHERE usuario_id = ? AND semana_inicio = ? AND status = 'ativo'
                ORDER BY criado_em DESC
            ");
            $stmt->execute([$uid, $week]);
            $lotes = array_map(fn($row) => exerciciosMapLote($db, $row), $stmt->fetchAll());
            jsonResponse(['ok' => true, 'semana_inicio' => $week, 'lotes' => $lotes]);
            break;

        case 'responder':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(12000);
            $loteId = (int)($payload['exercicio_planejado_id'] ?? 0);
            $key = sanitizeTextValue($payload['exercise_key'] ?? '', 80);
            if ($loteId <= 0 || $key === '') {
                jsonResponse(['erro' => 'Exercicio nao informado.'], 400);
            }
            $stmt = $db->prepare('SELECT * FROM exercicios_planejados WHERE id = ? AND usuario_id = ? LIMIT 1');
            $stmt->execute([$loteId, $uid]);
            $lote = $stmt->fetch();
            if (!$lote) {
                jsonResponse(['erro' => 'Lote de exercicios nao encontrado.'], 404);
            }
            $exercicios = exerciciosParseJson($lote['exercicios_json'])['exercicios'] ?? [];
            $exercicio = null;
            foreach ($exercicios as $item) {
                if (($item['id'] ?? '') === $key) {
                    $exercicio = $item;
                    break;
                }
            }
            if (!$exercicio) {
                jsonResponse(['erro' => 'Exercicio nao encontrado no lote.'], 404);
            }
            $respostaMarcada = strtoupper(sanitizeTextValue($payload['resposta_marcada'] ?? '', 20));
            if (!in_array($respostaMarcada, ['A', 'B', 'C', 'D', 'E'], true)) {
                jsonResponse(['erro' => 'Resposta invalida.'], 400);
            }

            $acertou = $respostaMarcada === strtoupper((string)($exercicio['resposta_correta'] ?? ''));
            $avaliacao = [
                'tipo' => 'multipla_escolha',
                'explicacao' => $exercicio['explicacao'] ?? '',
                'avaliado_por' => 'base_questoes',
            ];
            $motivosPermitidos = ['nao_sabia', 'atencao', 'calculo', 'interpretacao', 'duvida', 'chutei'];
            $motivoErro = sanitizeTextValue($payload['motivo_erro'] ?? '', 40);
            if ($acertou || !in_array($motivoErro, $motivosPermitidos, true)) {
                $motivoErro = null;
            }

            $db->prepare('
                DELETE FROM respostas_exercicios_planejados
                WHERE usuario_id = ? AND exercicio_planejado_id = ? AND exercise_key = ?
            ')->execute([$uid, $loteId, $key]);
            $columns = ['usuario_id', 'exercicio_planejado_id', 'exercise_key', 'resposta_usuario', 'resposta_marcada', 'acertou', 'avaliacao_json'];
            $values = [
                $uid,
                $loteId,
                $key,
                sanitizeTextValue($payload['resposta_usuario'] ?? '', 3000),
                $respostaMarcada,
                $acertou ? 1 : 0,
                jsonEncodeSafe($avaliacao),
            ];
            if (dbColumnExists($db, 'respostas_exercicios_planejados', 'motivo_erro')) {
                $columns[] = 'motivo_erro';
                $values[] = $motivoErro;
            }
            $db->prepare('
                INSERT INTO respostas_exercicios_planejados
                    (`' . implode('`,`', $columns) . '`)
                VALUES
                    (' . implode(',', array_fill(0, count($columns), '?')) . ')
            ')->execute($values);

            if (!empty($exercicio['questao_id'])) {
                exerciciosSalvarRespostaBase($db, $uid, (int)$exercicio['questao_id'], $respostaMarcada, $acertou, isset($payload['tempo_resposta']) ? (int)$payload['tempo_resposta'] : null);
            }

            jsonResponse(['ok' => true, 'acertou' => $acertou, 'avaliacao' => $avaliacao]);
            break;

        case 'avaliar_aberta':
            requirePost();
            validateCsrfToken();
            jsonResponse(['erro' => 'Avaliação aberta por IA não faz parte deste protótipo. Use questões objetivas da base aprovada.'], 410);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('exercicios', $e);
    jsonResponse(['erro' => 'Nao foi possivel processar exercicios agora.'], 500);
}
