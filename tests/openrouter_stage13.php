<?php
// All requests use an injected transport. This test never opens a network connection.
require_once __DIR__ . '/../server/services/ai/openrouterClient.php';
putenv('ESTUDAI_AI_DISABLED=false');
putenv('ESTUDAI_AI_FEATURES=plano_semanal,diagnostico,redacao,revisao_semanal,plano_anual');
putenv('OPENROUTER_API_KEY=sk-or-stage13-sentinel');
putenv('OPENROUTER_MODEL=tests/model:free');
putenv('OPENROUTER_BASE_URL=https://openrouter.ai/api/v1');
$checks = [];
$calls = 0;
function clientCheck(bool $condition, string $name): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $name);
    }
    $GLOBALS['checks'][] = ['test' => $name, 'result' => 'PASS'];
}
function clientFailure(callable $run, string $category): void {
    try {
        $run();
    } catch (OpenRouterException $error) {
        clientCheck($error->category === $category, $category);
        clientCheck(!str_contains($error->getMessage(), 'sk-or-stage13-sentinel'), 'safe_error_' . $category);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $category);
}
$messages = [['role' => 'system', 'content' => 'Use portugues.'], ['role' => 'user', 'content' => 'Dados ficticios.']];
$valid = ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"resumo":"Teste"}']]], 'model' => 'tests/model:free', 'usage' => ['cost' => 0]];
$transport = static function ($url, $payload, $config) use ($valid): array {
    $GLOBALS['calls']++;
    clientCheck($url === 'https://openrouter.ai/api/v1/chat/completions', 'official_destination');
    clientCheck($payload['max_tokens'] === 900 && !isset($payload['max_completion_tokens']), 'compatible_token_parameter');
    clientCheck($payload['provider']['max_price'] === ['prompt' => 0, 'completion' => 0], 'zero_price_routing');
    clientCheck($payload['stream'] === false && !isset($payload['tools']), 'no_tools_or_streaming');
    return ['status' => 200, 'body' => json_encode($valid)];
};
$response = openrouterChatCompletion($messages, ['feature' => 'diagnostico'], $transport);
clientCheck(openrouterFirstText($response) === '{"resumo":"Teste"}', 'valid_response');
foreach ([401, 403, 404, 408, 429, 500, 503, 302] as $status) {
    clientFailure(
        fn() => openrouterValidateResponse(['status' => $status, 'body' => 'private provider details sk-or-stage13-sentinel']),
        $status === 429 ? 'rate_limit' : 'http_' . $status
    );
}
foreach (['', '{', '<html>error</html>', '{}', 'null', '[]'] as $body) {
    $category = $body === '' ? 'empty_response' : (in_array($body, ['{}', '[]'], true) ? 'incomplete_response' : 'invalid_response');
    clientFailure(fn() => openrouterValidateResponse(['status' => 200, 'body' => $body]), $category);
}
clientFailure(fn() => openrouterValidateResponse(['status' => 200, 'body' => str_repeat('x', 131073)]), 'response_too_large');
foreach ([null, [], ''] as $content) {
    $bad = $valid;
    $bad['choices'][0]['message']['content'] = $content;
    clientFailure(fn() => openrouterValidateResponse(['status' => 200, 'body' => json_encode($bad)]), 'empty_content');
}
$bad = $valid;
$bad['choices'][0]['finish_reason'] = 'length';
clientFailure(fn() => openrouterValidateResponse(['status' => 200, 'body' => json_encode($bad)]), 'incomplete_response');
clientFailure(fn() => openrouterChatCompletion([], ['feature' => 'diagnostico'], $transport), 'messages_invalid');
clientFailure(fn() => openrouterChatCompletion([['role' => 'tool', 'content' => 'x']], ['feature' => 'diagnostico'], $transport), 'messages_invalid');
clientFailure(fn() => openrouterChatCompletion([['role' => 'user', 'content' => str_repeat('x', 60001)]], ['feature' => 'diagnostico'], $transport), 'request_too_large');
foreach ([['model' => 'paid/model'], ['stream' => true], ['max_tokens' => -1], ['temperature' => 7]] as $option) {
    $category = isset($option['model']) ? 'paid_model_blocked' : (isset($option['stream']) ? 'unsupported_parameter' : 'parameters_invalid');
    clientFailure(fn() => openrouterChatCompletion($messages, ['feature' => 'diagnostico'] + $option, $transport), $category);
}
clientFailure(fn() => openrouterChatCompletion($messages, ['feature' => 'mensagem'], $transport), 'feature_disabled');
putenv('OPENROUTER_API_KEY=');
clientFailure(fn() => openrouterChatCompletion($messages, ['feature' => 'diagnostico'], $transport), 'key_missing');
putenv('OPENROUTER_API_KEY=sk-or-stage13-sentinel');
putenv('OPENROUTER_BASE_URL=https://unrelated.invalid/api/v1');
clientFailure(fn() => openrouterChatCompletion($messages, ['feature' => 'diagnostico'], $transport), 'destination_invalid');
putenv('OPENROUTER_BASE_URL=https://openrouter.ai/api/v1');
foreach (['true', 'TRUE', '1', 'yes', 'on'] as $disabled) {
    putenv('ESTUDAI_AI_DISABLED=' . $disabled);
    try {
        openrouterChatCompletion($messages, ['feature' => 'diagnostico'], $transport);
        throw new LogicException('Disabled guard did not run');
    } catch (RuntimeException $error) {
        clientCheck(str_contains($error->getMessage(), 'desativadas'), 'disabled_' . $disabled);
    }
}
clientCheck($calls === 1, 'invalid_requests_never_reach_transport');
echo json_encode(['result' => 'PASS', 'checks' => $checks, 'external_requests' => 0, 'mock_transport_calls' => $calls]);
