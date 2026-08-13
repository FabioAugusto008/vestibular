<?php
// api/historico.php — Histórico de desempenho e streak

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db  = getDB();
$uid = currentUserId();

// ── HISTÓRICO DOS ÚLTIMOS 30 DIAS ─────────────────────────
$hist = $db->prepare("
    SELECT data, acertos, erros, tempo_seg,
           ROUND(acertos * 100.0 / (acertos + erros), 1) AS percentual
    FROM desempenho_diario
    WHERE usuario_id = ? AND finalizado = 1
    ORDER BY data DESC
    LIMIT 30
");
$hist->execute([$uid]);
$historico = $hist->fetchAll();

// ── STREAK (dias seguidos) ────────────────────────────────
$streak = 0;
$dia    = new DateTime();

foreach (range(0, 60) as $offset) {
    $dataStr = $dia->format('Y-m-d');
    $chk     = $db->prepare("
        SELECT 1 FROM desempenho_diario
        WHERE usuario_id = ? AND data = ? AND finalizado = 1
    ");
    $chk->execute([$uid, $dataStr]);
    if ($chk->fetch()) {
        $streak++;
        $dia->modify('-1 day');
    } else {
        // Se for hoje e não finalizou, não quebra o streak ainda
        if ($offset === 0) {
            $dia->modify('-1 day');
            continue;
        }
        break;
    }
}

// ── TOTAL GERAL ───────────────────────────────────────────
$tot = $db->prepare("
    SELECT
        COUNT(*) AS dias_estudados,
        SUM(acertos) AS total_acertos,
        SUM(erros) AS total_erros
    FROM desempenho_diario
    WHERE usuario_id = ? AND finalizado = 1
");
$tot->execute([$uid]);
$totais = $tot->fetch();

jsonResponse([
    'ok'        => true,
    'historico' => $historico,
    'streak'    => $streak,
    'totais'    => $totais,
]);
