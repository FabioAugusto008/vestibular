<?php
// api/preferencias.php — Preferências do usuário (tema, notificações)

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

    // ── CARREGAR PREFERÊNCIAS ──────────────────────────────
    case 'carregar':
        $stmt = $db->prepare("
            SELECT tema, notificacoes, horario_lembrete
            FROM preferencias_usuario
            WHERE usuario_id = ?
        ");
        $stmt->execute([$uid]);
        $prefs = $stmt->fetch();

        // Se não existe, cria com valores padrão
        if (!$prefs) {
            $ins = $db->prepare("
                INSERT INTO preferencias_usuario (usuario_id, tema, notificacoes, horario_lembrete)
                VALUES (?, 'light', 1, '08:00')
            ");
            $ins->execute([$uid]);
            $prefs = ['tema' => 'light', 'notificacoes' => 1, 'horario_lembrete' => '08:00'];
        }

        jsonResponse([
            'ok' => true,
            'preferencias' => [
                'tema' => $prefs['tema'],
                'notificacoes' => (bool)$prefs['notificacoes'],
                'horario_lembrete' => $prefs['horario_lembrete']
            ]
        ]);
        break;

    // ── SALVAR PREFERÊNCIAS ────────────────────────────────
    case 'salvar':
        requirePost();
        validateCsrfToken();
        $tema = $_POST['tema'] ?? 'light';
        $notificacoes = isset($_POST['notificacoes']) ? (int)$_POST['notificacoes'] : 1;
        $horario = $_POST['horario_lembrete'] ?? '08:00';

        // Valida tema
        if (!in_array($tema, ['dark', 'light'])) {
            $tema = 'light';
        }

        // Valida horário
        if (!preg_match('/^\d{2}:\d{2}$/', $horario)) {
            $horario = '08:00';
        }

        $stmt = $db->prepare("
            INSERT INTO preferencias_usuario (usuario_id, tema, notificacoes, horario_lembrete)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE tema = ?, notificacoes = ?, horario_lembrete = ?
        ");
        $stmt->execute([$uid, $tema, $notificacoes, $horario, $tema, $notificacoes, $horario]);

        jsonResponse(['ok' => true]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
