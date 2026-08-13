<?php
// api/ia.php - Endpoint interno base para chamadas educacionais de IA

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/ai/openrouterClient.php';
require_once __DIR__ . '/../services/ai/prompts.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/openrouter.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    jsonResponse([
        'ok' => true,
        'provider' => 'openrouter',
        'configured' => !empty($config['api_key']) && strpos($config['api_key'], '<') === false,
        'model' => $config['model'],
    ]);
}

requirePost();
validateCsrfToken();
rateLimitGuard('ia_mensagem', 20, 3600);

$body = requestJsonBody();
$action = $body['action'] ?? 'mensagem';

if ($action !== 'mensagem') {
    jsonResponse(['erro' => 'Acao invalida.'], 400);
}

$mensagem = clampString((string)($body['mensagem'] ?? ''), 2000);
$contexto = clampString((string)($body['contexto'] ?? ''), 2000);

if ($mensagem === '') {
    jsonResponse(['erro' => 'Mensagem nao informada.'], 400);
}

try {
    $messages = [
        ['role' => 'system', 'content' => estudaiSystemPrompt()],
        ['role' => 'user', 'content' => trim($contexto . "\n\n" . $mensagem)],
    ];

    $response = openrouterChatCompletion($messages, [
        'temperature' => 0.35,
        'max_tokens' => 700,
    ]);

    jsonResponse([
        'ok' => true,
        'provider' => 'openrouter',
        'model' => $config['model'],
        'resposta' => openrouterFirstText($response),
    ]);
} catch (Throwable $e) {
    logTechnicalError('ia_mensagem', $e);
    $message = $e instanceof OpenRouterRateLimitException
        ? 'A IA atingiu o limite temporário do provedor. Tente novamente em alguns instantes.'
        : 'IA indisponivel no momento. Tente novamente mais tarde.';
    jsonResponse(['erro' => $message], 503);
}
