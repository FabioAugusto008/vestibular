<?php
// api/auth.php — Cadastro, Login, Logout

require_once __DIR__ . '/../helpers/helpers.php';

initSession();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── CADASTRO ──────────────────────────────────────────
    case 'cadastrar':
        requirePost();
        rateLimitGuard('auth_cadastrar', 8, 900);
        $nome  = trim($_POST['nome'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';

        if (!$nome || !$email || !$senha) {
            jsonResponse(['erro' => 'Preencha todos os campos.'], 400);
        }
        if (strlen($nome) < 2 || strlen($nome) > 100) {
            jsonResponse(['erro' => 'Informe um nome entre 2 e 100 caracteres.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['erro' => 'E-mail invalido.'], 400);
        }
        if (strlen($senha) < 6) {
            jsonResponse(['erro' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
        }

        $nome = clampString($nome, 100);
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            jsonResponse(['erro' => 'E-mail ja cadastrado.'], 409);
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $ins  = $db->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        $ins->execute([$nome, $email, $hash]);

        jsonResponse(['ok' => true, 'mensagem' => 'Cadastro realizado! Faca login.']);
        break;

    // ── LOGIN ─────────────────────────────────────────────
    case 'login':
        requirePost();
        rateLimitGuard('auth_login', 12, 900);
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';

        if (!$email || !$senha) {
            jsonResponse(['erro' => 'Preencha e-mail e senha.'], 400);
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($senha, $user['senha'])) {
            jsonResponse(['erro' => 'E-mail ou senha incorretos.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id']   = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        unset($_SESSION['csrf_token']);

        jsonResponse(['ok' => true, 'nome' => $user['nome'], 'csrf_token' => csrfToken()]);
        break;

    // ── LOGOUT ────────────────────────────────────────────
    case 'logout':
        session_destroy();
        jsonResponse(['ok' => true]);
        break;

    // ── STATUS (verifica se esta logado) ──────────────────
    case 'status':
        if (isLoggedIn()) {
            jsonResponse(['logado' => true, 'nome' => currentUserName(), 'csrf_token' => csrfToken()]);
        } else {
            jsonResponse(['logado' => false, 'csrf_token' => csrfToken()]);
        }
        break;

    default:
        jsonResponse(['erro' => 'Acao invalida.'], 400);
}
