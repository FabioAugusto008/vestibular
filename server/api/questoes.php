<?php
// api/questoes.php - Questoes do dia a partir da base aprovada

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();
$uid = currentUserId();
$hoje = date('Y-m-d');

function questoesDoDia(PDO $db, string $data): array {
    $stmt = $db->prepare("
        SELECT q.id, q.area, q.materia, q.conteudo, q.competencia, q.habilidade,
               q.dificuldade, q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status
        FROM questoes_do_dia qd
        JOIN questoes q ON q.id = qd.questao_id
        WHERE qd.data = ?
          AND " . questoesApprovedWhere('q') . "
        ORDER BY q.materia, q.conteudo, q.id
    ");
    $stmt->execute([$data]);
    return questoesAttachAlternativas($db, $stmt->fetchAll());
}

function questoesRespostaDoUsuario(PDO $db, int $uid, string $data): array {
    $corretaExpr = dbColumnExists($db, 'respostas_usuario', 'correta') ? 'correta' : 'acertou AS correta';
    $stmt = $db->prepare("
        SELECT questao_id, resposta_marcada, acertou, {$corretaExpr}
        FROM respostas_usuario
        WHERE usuario_id = ? AND data = ?
    ");
    $stmt->execute([$uid, $data]);
    $respostas = [];
    foreach ($stmt->fetchAll() as $row) {
        $respostas[(int)$row['questao_id']] = [
            'marcada' => $row['resposta_marcada'],
            'acertou' => (bool)($row['correta'] ?? $row['acertou']),
        ];
    }
    return $respostas;
}

try {
    if (!dbTableExists($db, 'questoes') || !questoesAlternativasTable($db) || !dbTableExists($db, 'questoes_do_dia')) {
        jsonResponse(['erro' => 'Banco de questoes indisponivel. Aplique database/schema.sql.'], 503);
    }

    gerarQuestoesDodia($hoje);

    switch ($action) {
        case 'carregar':
            $questoes = questoesDoDia($db, $hoje);
            $respostas = questoesRespostaDoUsuario($db, $uid, $hoje);

            $fin = $db->prepare("
                SELECT finalizado, acertos, erros, tempo_seg
                FROM desempenho_diario
                WHERE usuario_id = ? AND data = ?
            ");
            $fin->execute([$uid, $hoje]);
            $desempenho = $fin->fetch();
            $finalizado = $desempenho && !empty($desempenho['finalizado']);
            $gabarito = null;

            if ($finalizado) {
                $gabarito = [];
                foreach ($questoes as $questao) {
                    $gabarito[(int)$questao['id']] = [
                        'correta' => $questao['resposta_correta'],
                        'explicacao' => $questao['explicacao'],
                    ];
                }
            }

            jsonResponse([
                'ok' => true,
                'questoes' => $questoes,
                'respostas' => $respostas,
                'finalizado' => $finalizado,
                'total' => count($questoes),
                'respondidas' => count($respostas),
                'desempenho' => $desempenho ?: null,
                'gabarito' => $gabarito,
                'aviso' => count($questoes) < 20 ? 'Não encontramos questões suficientes para completar o treino de hoje ainda.' : null,
            ]);
            break;

        case 'responder':
            requirePost();
            validateCsrfToken();
            $questaoId = (int)($_POST['questao_id'] ?? 0);
            $resposta = strtoupper(trim($_POST['resposta'] ?? ''));

            if ($questaoId <= 0 || !in_array($resposta, ['A', 'B', 'C', 'D', 'E'], true)) {
                jsonResponse(['erro' => 'Dados invalidos.'], 400);
            }

            $fin = $db->prepare('SELECT finalizado FROM desempenho_diario WHERE usuario_id = ? AND data = ?');
            $fin->execute([$uid, $hoje]);
            $desemp = $fin->fetch();
            if ($desemp && !empty($desemp['finalizado'])) {
                jsonResponse(['erro' => 'Dia ja finalizado.'], 403);
            }

            $chk = $db->prepare("
                SELECT 1
                FROM questoes_do_dia qd
                JOIN questoes q ON q.id = qd.questao_id
                WHERE qd.data = ? AND qd.questao_id = ? AND " . questoesApprovedWhere('q') . "
                LIMIT 1
            ");
            $chk->execute([$hoje, $questaoId]);
            if (!$chk->fetch()) {
                jsonResponse(['erro' => 'Questao nao pertence ao treino de hoje.'], 403);
            }

            $dup = $db->prepare('SELECT 1 FROM respostas_usuario WHERE usuario_id = ? AND questao_id = ? AND data = ? LIMIT 1');
            $dup->execute([$uid, $questaoId, $hoje]);
            if ($dup->fetch()) {
                jsonResponse(['erro' => 'Questao ja respondida.'], 409);
            }

            $correta = questoesRespostaCorreta($db, $questaoId);
            if (!$correta) {
                jsonResponse(['erro' => 'Gabarito da questao indisponivel.'], 500);
            }
            $alternativaId = questoesAlternativaIdPorLetra($db, $questaoId, $resposta);
            $acertou = $correta['letra'] === $resposta;

            if (dbColumnExists($db, 'respostas_usuario', 'alternativa_id')) {
                $stmt = $db->prepare("
                    INSERT INTO respostas_usuario
                        (usuario_id, questao_id, alternativa_id, data, resposta_marcada, correta, acertou, tempo_resposta)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$uid, $questaoId, $alternativaId, $hoje, $resposta, $acertou ? 1 : 0, $acertou ? 1 : 0, null]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO respostas_usuario (usuario_id, questao_id, data, resposta_marcada, acertou)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$uid, $questaoId, $hoje, $resposta, $acertou ? 1 : 0]);
            }
            questoesSalvarRespostaModelo($db, $uid, $questaoId, $alternativaId, $acertou);

            jsonResponse(['ok' => true, 'acertou' => $acertou]);
            break;

        case 'finalizar':
            requirePost();
            validateCsrfToken();
            $tempoSeg = (int)($_POST['tempo_seg'] ?? 0);

            $total = count(questoesDoDia($db, $hoje));
            if ($total <= 0) {
                jsonResponse(['erro' => 'Não encontramos questões suficientes para finalizar o treino de hoje.'], 400);
            }

            $cnt = $db->prepare('SELECT COUNT(*) FROM respostas_usuario WHERE usuario_id = ? AND data = ?');
            $cnt->execute([$uid, $hoje]);
            $respondidas = (int)$cnt->fetchColumn();
            if ($respondidas < $total) {
                jsonResponse(['erro' => 'Responda todas as questoes antes de finalizar.'], 400);
            }

            $fin = $db->prepare('SELECT finalizado FROM desempenho_diario WHERE usuario_id = ? AND data = ?');
            $fin->execute([$uid, $hoje]);
            $desemp = $fin->fetch();
            if ($desemp && !empty($desemp['finalizado'])) {
                jsonResponse(['erro' => 'Dia ja finalizado.'], 409);
            }

            $scoreExpr = dbColumnExists($db, 'respostas_usuario', 'correta') ? 'correta' : 'acertou';
            $calc = $db->prepare("
                SELECT
                    SUM(COALESCE({$scoreExpr}, 0)) AS acertos,
                    SUM(1 - COALESCE({$scoreExpr}, 0)) AS erros
                FROM respostas_usuario
                WHERE usuario_id = ? AND data = ?
            ");
            $calc->execute([$uid, $hoje]);
            $resultado = $calc->fetch();
            $acertos = (int)($resultado['acertos'] ?? 0);
            $erros = (int)($resultado['erros'] ?? 0);

            $upsert = $db->prepare("
                INSERT INTO desempenho_diario (usuario_id, data, acertos, erros, tempo_seg, finalizado)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE acertos = VALUES(acertos), erros = VALUES(erros), tempo_seg = VALUES(tempo_seg), finalizado = 1
            ");
            $upsert->execute([$uid, $hoje, $acertos, $erros, $tempoSeg]);

            $gabarito = [];
            foreach (questoesDoDia($db, $hoje) as $questao) {
                $gabarito[(int)$questao['id']] = [
                    'correta' => $questao['resposta_correta'],
                    'explicacao' => $questao['explicacao'],
                ];
            }

            jsonResponse([
                'ok' => true,
                'acertos' => $acertos,
                'erros' => $erros,
                'total' => $total,
                'gabarito' => $gabarito,
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('questoes', $e);
    jsonResponse(['erro' => 'Nao foi possivel carregar as questoes agora.'], 500);
}
