<?php
// api/revisao.php - Modo de revisao com questoes aprovadas da base

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();
$uid = currentUserId();

function revisaoScoreExpr(PDO $db): string {
    return dbColumnExists($db, 'respostas_usuario', 'correta') ? 'correta' : 'acertou';
}

switch ($action) {
    case 'carregar':
        $materia = sanitizeTextValue($_GET['materia'] ?? '', 80);
        $limite = min(50, max(5, (int)($_GET['limite'] ?? 20)));
        $scoreExpr = revisaoScoreExpr($db);

        $sql = "
            SELECT DISTINCT q.id, q.area, q.materia, q.conteudo, q.competencia, q.habilidade,
                   q.dificuldade, q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status,
                   (SELECT MAX(data) FROM respostas_usuario r2
                    WHERE r2.questao_id = q.id AND r2.usuario_id = ? AND COALESCE(r2.{$scoreExpr}, 0) = 0) AS ultima_errada,
                   (SELECT r4.resposta_marcada FROM respostas_usuario r4
                    WHERE r4.questao_id = q.id AND r4.usuario_id = ? AND COALESCE(r4.{$scoreExpr}, 0) = 0
                    ORDER BY r4.data DESC, r4.id DESC LIMIT 1) AS resposta_anterior
            FROM questoes q
            JOIN respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = ?
            WHERE COALESCE(r.{$scoreExpr}, 0) = 0
              AND " . questoesApprovedWhere('q') . "
              AND NOT EXISTS (
                SELECT 1 FROM respostas_usuario r3
                WHERE r3.questao_id = q.id
                  AND r3.usuario_id = ?
                  AND COALESCE(r3.{$scoreExpr}, 0) = 1
                  AND r3.data > r.data
              )
        ";
        $params = [$uid, $uid, $uid, $uid];
        if ($materia !== '' && $materia !== 'todas') {
            $sql .= ' AND q.materia = ?';
            $params[] = $materia;
        }
        $sql .= " ORDER BY ultima_errada DESC LIMIT {$limite}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $questoes = questoesAttachAlternativas($db, $stmt->fetchAll());

        $countSql = "
            SELECT COUNT(DISTINCT q.id) AS total
            FROM questoes q
            JOIN respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = ?
            WHERE COALESCE(r.{$scoreExpr}, 0) = 0
              AND " . questoesApprovedWhere('q') . "
              AND NOT EXISTS (
                SELECT 1 FROM respostas_usuario r3
                WHERE r3.questao_id = q.id
                  AND r3.usuario_id = ?
                  AND COALESCE(r3.{$scoreExpr}, 0) = 1
                  AND r3.data > r.data
              )
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([$uid, $uid]);
        $total = (int)$countStmt->fetchColumn();

        jsonResponse(['ok' => true, 'questoes' => $questoes, 'total_disponiveis' => $total]);
        break;

    case 'responder':
        requirePost();
        validateCsrfToken();
        $questaoId = (int)($_POST['questao_id'] ?? 0);
        $resposta = strtoupper(trim($_POST['resposta'] ?? ''));
        if ($questaoId <= 0 || !in_array($resposta, ['A', 'B', 'C', 'D', 'E'], true)) {
            jsonResponse(['erro' => 'Dados invalidos.'], 400);
        }

        $stmt = $db->prepare('SELECT id, explicacao FROM questoes WHERE id = ? AND ' . questoesApprovedWhere('questoes') . ' LIMIT 1');
        $stmt->execute([$questaoId]);
        $questao = $stmt->fetch();
        if (!$questao) {
            jsonResponse(['erro' => 'Questao nao encontrada.'], 404);
        }

        $correta = questoesRespostaCorreta($db, $questaoId);
        if (!$correta) {
            jsonResponse(['erro' => 'Gabarito da questao indisponivel.'], 500);
        }
        $alternativaId = questoesAlternativaIdPorLetra($db, $questaoId, $resposta);
        $acertou = $correta['letra'] === $resposta;
        $hoje = date('Y-m-d');

        if (dbColumnExists($db, 'respostas_usuario', 'alternativa_id')) {
            $ins = $db->prepare("
                INSERT INTO respostas_usuario (usuario_id, questao_id, alternativa_id, data, resposta_marcada, correta, acertou)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE alternativa_id = VALUES(alternativa_id), resposta_marcada = VALUES(resposta_marcada), correta = VALUES(correta), acertou = VALUES(acertou)
            ");
            $ins->execute([$uid, $questaoId, $alternativaId, $hoje, $resposta, $acertou ? 1 : 0, $acertou ? 1 : 0]);
        } else {
            $ins = $db->prepare("
                INSERT INTO respostas_usuario (usuario_id, questao_id, data, resposta_marcada, acertou)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE resposta_marcada = VALUES(resposta_marcada), acertou = VALUES(acertou)
            ");
            $ins->execute([$uid, $questaoId, $hoje, $resposta, $acertou ? 1 : 0]);
        }
        questoesSalvarRespostaModelo($db, $uid, $questaoId, $alternativaId, $acertou);

        jsonResponse([
            'ok' => true,
            'acertou' => $acertou,
            'correta' => $correta['letra'],
            'explicacao' => $questao['explicacao'],
        ]);
        break;

    case 'estatisticas':
        $scoreExpr = revisaoScoreExpr($db);
        $pendentes = $db->prepare("
            SELECT q.materia, COUNT(DISTINCT q.id) AS total
            FROM questoes q
            JOIN respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = ?
            WHERE COALESCE(r.{$scoreExpr}, 0) = 0
              AND " . questoesApprovedWhere('q') . "
              AND NOT EXISTS (
                SELECT 1 FROM respostas_usuario r3
                WHERE r3.questao_id = q.id
                  AND r3.usuario_id = ?
                  AND COALESCE(r3.{$scoreExpr}, 0) = 1
                  AND r3.data > r.data
              )
            GROUP BY q.materia
        ");
        $pendentes->execute([$uid, $uid]);

        $porMateria = [];
        foreach ($pendentes->fetchAll() as $row) {
            $porMateria[$row['materia']] = (int)$row['total'];
        }

        jsonResponse(['ok' => true, 'pendentes' => $porMateria, 'total' => array_sum($porMateria)]);
        break;

    default:
        jsonResponse(['erro' => 'Acao invalida.'], 400);
}
