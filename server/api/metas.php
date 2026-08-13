<?php
// api/metas.php — Metas semanais

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

// Calcula início da semana (segunda-feira)
function inicioSemana($data = null): string {
    $dt = $data ? new DateTime($data) : new DateTime();
    $dia_semana = (int)$dt->format('N'); // 1 = segunda, 7 = domingo
    $dt->modify('-' . ($dia_semana - 1) . ' days');
    return $dt->format('Y-m-d');
}

switch ($action) {

    // ── CARREGAR META DA SEMANA ATUAL ──────────────────────
    case 'carregar':
        $semana = inicioSemana();
        
        $stmt = $db->prepare("
            SELECT meta_questoes, questoes_feitas, acertos, concluida
            FROM metas_semanais
            WHERE usuario_id = ? AND semana_inicio = ?
        ");
        $stmt->execute([$uid, $semana]);
        $meta = $stmt->fetch();

        // Calcula questões feitas na semana atual
        $fim_semana = (new DateTime($semana))->modify('+6 days')->format('Y-m-d');
        $feitas = $db->prepare("
            SELECT COUNT(*) as total, SUM(acertou) as acertos
            FROM respostas_usuario
            WHERE usuario_id = ? AND data BETWEEN ? AND ?
        ");
        $feitas->execute([$uid, $semana, $fim_semana]);
        $resultado = $feitas->fetch();

        $questoes_feitas = (int)$resultado['total'];
        $acertos = (int)$resultado['acertos'];

        // Atualiza ou cria meta
        if ($meta) {
            // Atualiza progresso
            $upd = $db->prepare("
                UPDATE metas_semanais 
                SET questoes_feitas = ?, acertos = ?, concluida = IF(? >= meta_questoes, 1, 0)
                WHERE usuario_id = ? AND semana_inicio = ?
            ");
            $upd->execute([$questoes_feitas, $acertos, $questoes_feitas, $uid, $semana]);
            $meta['questoes_feitas'] = $questoes_feitas;
            $meta['acertos'] = $acertos;
            $meta['concluida'] = $questoes_feitas >= $meta['meta_questoes'];
        } else {
            // Cria meta padrão
            $ins = $db->prepare("
                INSERT INTO metas_semanais (usuario_id, semana_inicio, meta_questoes, questoes_feitas, acertos)
                VALUES (?, ?, 100, ?, ?)
            ");
            $ins->execute([$uid, $semana, $questoes_feitas, $acertos]);
            $meta = [
                'meta_questoes' => 100,
                'questoes_feitas' => $questoes_feitas,
                'acertos' => $acertos,
                'concluida' => false
            ];
        }

        jsonResponse([
            'ok' => true,
            'meta' => [
                'semana_inicio' => $semana,
                'meta_questoes' => (int)$meta['meta_questoes'],
                'questoes_feitas' => (int)$meta['questoes_feitas'],
                'acertos' => (int)$meta['acertos'],
                'concluida' => (bool)$meta['concluida'],
                'percentual' => $meta['meta_questoes'] > 0 
                    ? min(100, round(($meta['questoes_feitas'] / $meta['meta_questoes']) * 100, 1)) 
                    : 0
            ]
        ]);
        break;

    // ── DEFINIR META ───────────────────────────────────────
    case 'definir':
        requirePost();
        validateCsrfToken();
        $semana = inicioSemana();
        $meta_questoes = max(10, min(500, (int)($_POST['meta_questoes'] ?? 100)));

        $stmt = $db->prepare("
            INSERT INTO metas_semanais (usuario_id, semana_inicio, meta_questoes)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE meta_questoes = ?
        ");
        $stmt->execute([$uid, $semana, $meta_questoes, $meta_questoes]);

        jsonResponse(['ok' => true, 'meta_questoes' => $meta_questoes]);
        break;

    // ── HISTÓRICO DE METAS ─────────────────────────────────
    case 'historico':
        $stmt = $db->prepare("
            SELECT semana_inicio, meta_questoes, questoes_feitas, acertos, concluida,
                   ROUND(questoes_feitas * 100.0 / meta_questoes, 1) as percentual
            FROM metas_semanais
            WHERE usuario_id = ?
            ORDER BY semana_inicio DESC
            LIMIT 12
        ");
        $stmt->execute([$uid]);
        
        jsonResponse(['ok' => true, 'historico' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
