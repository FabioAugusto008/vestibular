<?php
// api/anotacoes.php — Anotações em questões

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

function anotacoesTableReady(PDO $db): bool {
    return dbTableExists($db, 'anotacoes') && dbTableExists($db, 'questoes');
}

switch ($action) {

    // ── SALVAR/ATUALIZAR ANOTAÇÃO ──────────────────────────
    case 'salvar':
        requirePost();
        validateCsrfToken();
        if (!anotacoesTableReady($db)) {
            jsonResponse(['erro' => 'Anotacoes de questoes ainda aguardam a estrutura do banco.'], 503);
        }
        $payload = requestPayload(4000);
        $questao_id = (int)($payload['questao_id'] ?? 0);
        $texto = sanitizeTextValue($payload['texto'] ?? '', 2000);

        if (!$questao_id) {
            jsonResponse(['erro' => 'Questão inválida.'], 400);
        }

        // Se texto vazio, remove anotação
        $check = $db->prepare('SELECT 1 FROM questoes WHERE id = ? LIMIT 1');
        $check->execute([$questao_id]);
        if (!$check->fetchColumn()) {
            jsonResponse(['erro' => 'Questao nao encontrada para anotacao.'], 404);
        }

        if (empty($texto)) {
            $del = $db->prepare("DELETE FROM anotacoes WHERE usuario_id = ? AND questao_id = ?");
            $del->execute([$uid, $questao_id]);
            jsonResponse(['ok' => true, 'removida' => true]);
        }

        $stmt = $db->prepare("
            INSERT INTO anotacoes (usuario_id, questao_id, texto)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE texto = ?, atualizada_em = NOW()
        ");
        $stmt->execute([$uid, $questao_id, $texto, $texto]);

        jsonResponse(['ok' => true]);
        break;

    // ── CARREGAR ANOTAÇÃO DE UMA QUESTÃO ───────────────────
    case 'carregar':
        if (!anotacoesTableReady($db)) {
            jsonResponse(['ok' => true, 'anotacao' => null, 'aviso' => 'Anotacoes de questoes aguardam a estrutura do banco.']);
        }
        $questao_id = (int)($_GET['questao_id'] ?? 0);

        if (!$questao_id) {
            jsonResponse(['erro' => 'Questão inválida.'], 400);
        }

        $stmt = $db->prepare("
            SELECT texto, criada_em, atualizada_em
            FROM anotacoes
            WHERE usuario_id = ? AND questao_id = ?
        ");
        $stmt->execute([$uid, $questao_id]);
        $anotacao = $stmt->fetch();

        jsonResponse([
            'ok' => true,
            'anotacao' => $anotacao ? [
                'texto' => $anotacao['texto'],
                'criada_em' => $anotacao['criada_em'],
                'atualizada_em' => $anotacao['atualizada_em']
            ] : null
        ]);
        break;

    // ── LISTAR TODAS ANOTAÇÕES ─────────────────────────────
    case 'listar':
        if (!anotacoesTableReady($db)) {
            jsonResponse([
                'ok' => true,
                'anotacoes' => [],
                'total' => 0,
                'aviso' => 'Anotacoes de questoes aguardam a estrutura do banco.',
            ]);
        }
        $stmt = $db->prepare("
            SELECT a.*, q.enunciado, q.materia
            FROM anotacoes a
            JOIN questoes q ON q.id = a.questao_id
            WHERE a.usuario_id = ?
            ORDER BY a.atualizada_em DESC
        ");
        $stmt->execute([$uid]);
        $anotacoes = $stmt->fetchAll();

        // Trunca enunciados para preview
        foreach ($anotacoes as &$a) {
            $enunciado = (string)($a['enunciado'] ?? '');
            $preview = function_exists('mb_substr') ? mb_substr($enunciado, 0, 100, 'UTF-8') : substr($enunciado, 0, 100);
            $length = function_exists('mb_strlen') ? mb_strlen($enunciado, 'UTF-8') : strlen($enunciado);
            $a['enunciado_preview'] = $preview . ($length > 100 ? '...' : '');
        }

        jsonResponse([
            'ok' => true,
            'anotacoes' => $anotacoes,
            'total' => count($anotacoes)
        ]);
        break;

    // ── REMOVER ANOTAÇÃO ───────────────────────────────────
    case 'remover':
        requirePost();
        validateCsrfToken();
        if (!anotacoesTableReady($db)) {
            jsonResponse(['erro' => 'Anotacoes de questoes ainda aguardam a estrutura do banco.'], 503);
        }
        $payload = requestPayload(2000);
        $questao_id = (int)($payload['questao_id'] ?? 0);

        if (!$questao_id) {
            jsonResponse(['erro' => 'Questão inválida.'], 400);
        }

        $stmt = $db->prepare("DELETE FROM anotacoes WHERE usuario_id = ? AND questao_id = ?");
        $stmt->execute([$uid, $questao_id]);

        jsonResponse(['ok' => true]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
