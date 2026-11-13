<?php

class OpenRouterException extends RuntimeException {
    public function __construct(public string $category, int $status = 0) {
        parent::__construct('OpenRouter indisponivel (' . $category . ').', $status);
    }
}

class OpenRouterRateLimitException extends OpenRouterException {
    public function __construct() {
        parent::__construct('rate_limit', 429);
    }
}

function openrouterReserveRequest(array $config, string $feature): int {
    require_once __DIR__ . '/../../config/database.php';
    if (DB_NAME !== 'estudai_teste_2026' || DB_USER !== 'estudai_teste_app') {
        throw new OpenRouterException('database_scope');
    }
    $db = getDB();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== DB_NAME ||
        $db->query('SELECT CURRENT_USER()')->fetchColumn() !== 'estudai_teste_app@localhost') {
        throw new OpenRouterException('database_scope');
    }
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if (!in_array($uid, $config['allowed_users'], true)) {
        throw new OpenRouterException('user_scope');
    }
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND email LIKE '%@estudai.invalid'");
    $stmt->execute([$uid]);
    if (!$stmt->fetchColumn()) {
        throw new OpenRouterException('user_scope');
    }

    $directory = realpath(dirname($config['budget_file']));
    $privateRoot = realpath('C:/Users/player/AppData/Local/EstudAI/Recovery');
    if (!$directory || !$privateRoot ||
        !str_starts_with(strtolower($directory), strtolower($privateRoot . DIRECTORY_SEPARATOR))) {
        throw new OpenRouterException('budget_configuration');
    }
    $file = fopen($config['budget_file'], 'c+');
    if (!$file) {
        throw new OpenRouterException('budget_unavailable');
    }
    try {
        if (!flock($file, LOCK_EX)) {
            throw new OpenRouterException('budget_unavailable');
        }
        $raw = stream_get_contents($file);
        $ledger = $raw === '' ? ['requests' => []] : json_decode($raw, true);
        if (!is_array($ledger) || !is_array($ledger['requests'] ?? null)) {
            throw new OpenRouterException('budget_invalid');
        }
        if (!empty($ledger['stopped_after_error'])) {
            throw new OpenRouterException('provider_stopped');
        }
        if (count($ledger['requests']) >= $config['max_requests']) {
            throw new OpenRouterException('budget_exhausted');
        }
        $ledger['requests'][] = [
            'feature' => $feature,
            'model' => $config['model'],
            'reserved_at' => gmdate('c'),
        ];
        rewind($file);
        $encoded = json_encode($ledger, JSON_THROW_ON_ERROR);
        if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
            throw new OpenRouterException('budget_unavailable');
        }
        return count($ledger['requests']);
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function openrouterRecordResult(array $config, array $metadata): void {
    // Only transport metadata is stored. Prompts, bodies and authorization stay in memory.
    $path = $config['budget_file'];
    file_put_contents($path . '.results.jsonl', json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
    $invalidContent = ['incomplete_response', 'invalid_response', 'empty_response', 'empty_content', 'response_too_large'];
    if ($metadata['category'] !== null && !in_array($metadata['category'], $invalidContent, true)) {
        $file = fopen($path, 'r+');
        if ($file) {
            try {
                if (flock($file, LOCK_EX)) {
                    $ledger = json_decode(stream_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
                    $ledger['stopped_after_error'] = true;
                    rewind($file);
                    ftruncate($file, 0);
                    fwrite($file, json_encode($ledger, JSON_THROW_ON_ERROR));
                    fflush($file);
                }
            } finally {
                flock($file, LOCK_UN);
                fclose($file);
            }
        }
    }
}

function openrouterCurlRequest(string $url, array $payload, array $config): array {
    if (!function_exists('curl_init')) {
        throw new OpenRouterException('curl_missing');
    }
    $body = '';
    $tooLarge = false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
            'HTTP-Referer: ' . $config['site_url'],
            'X-OpenRouter-Title: ' . $config['site_name'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $config['timeout_seconds'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 131072) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $success = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($tooLarge) {
        throw new OpenRouterException('response_too_large', $status);
    }
    if ($success === false) {
        throw new OpenRouterException($errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network', $status);
    }
    return ['status' => $status, 'body' => $body];
}

function openrouterValidateResponse(array $response): array {
    $status = $response['status'] ?? 0;
    $raw = $response['body'] ?? '';
    if (!is_int($status) || !is_string($raw)) {
        throw new OpenRouterException('transport_contract');
    }
    if ($status === 429) {
        throw new OpenRouterRateLimitException();
    }
    if ($status < 200 || $status >= 300) {
        throw new OpenRouterException('http_' . $status, $status);
    }
    if ($raw === '' || strlen($raw) > 131072) {
        throw new OpenRouterException($raw === '' ? 'empty_response' : 'response_too_large', $status);
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || isset($data['error'])) {
        throw new OpenRouterException('invalid_response', $status);
    }
    $choice = $data['choices'][0] ?? [];
    if (($choice['finish_reason'] ?? '') !== 'stop') {
        throw new OpenRouterException('incomplete_response', $status);
    }
    $refusal = $choice['message']['refusal'] ?? null;
    if (($refusal !== null && $refusal !== '') || isset($choice['error'])) {
        throw new OpenRouterException('invalid_response', $status);
    }
    $content = $choice['message']['content'] ?? null;
    if (!is_string($content) || trim($content) === '') {
        throw new OpenRouterException('empty_content', $status);
    }
    return $data;
}

function openrouterResponseMetadata(array $response): array {
    // Whitelist small typed facts; never retain provider text, prompts or errors.
    $decoded = json_decode(is_string($response['body'] ?? null) ? $response['body'] : '', true);
    $decoded = is_array($decoded) ? $decoded : [];
    $choice = $decoded['choices'][0] ?? null;
    $choice = is_array($choice) ? $choice : [];
    $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
    $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
    $model = $decoded['model'] ?? null;
    if (!is_string($model) || !preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.:-]{1,100}$/D', $model) || str_contains($model, 'sk-or-')) $model = null;
    $finish = $choice['finish_reason'] ?? null;
    if ($finish !== null && !in_array($finish, ['stop', 'length', 'content_filter', 'tool_calls', 'error'], true)) $finish = 'other';
    $count = static fn($value) => is_int($value) && $value >= 0 && $value <= 10000000 ? $value : null;
    $cost = $usage['cost'] ?? null;
    $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
    $parameter = $error['param'] ?? null;
    if (!in_array($parameter, ['model', 'messages', 'temperature', 'max_tokens', 'response_format', 'reasoning', 'provider'], true)) $parameter = null;
    return [
        'actual_model' => $model,
        'observed_cost' => is_numeric($cost) && is_finite((float)$cost) && (float)$cost >= 0 ? (float)$cost : null,
        'finish_reason' => $finish,
        'prompt_tokens' => $count($usage['prompt_tokens'] ?? null),
        'completion_tokens' => $count($usage['completion_tokens'] ?? null),
        'reasoning_tokens' => $count($usage['completion_tokens_details']['reasoning_tokens'] ?? null),
        'content_bytes' => is_string($message['content'] ?? null) ? strlen($message['content']) : null,
        'refusal_present' => isset($message['refusal']) && $message['refusal'] !== '',
        'error_present' => isset($decoded['error']) || isset($choice['error']),
        'error_parameter' => $parameter,
    ];
}

function openrouterChatCompletion(array $messages, array $options = [], ?callable $transport = null): array {
    $config = require __DIR__ . '/../../config/openrouter.php';
    if (!$config['enabled']) {
        throw new RuntimeException('Chamadas de IA desativadas neste ambiente local.');
    }
    $feature = $options['feature'] ?? '';
    if (!is_string($feature) || !in_array($feature, $config['approved_features'], true)) {
        throw new OpenRouterException('feature_disabled');
    }
    if (!preg_match('/^sk-or-[A-Za-z0-9_-]+$/D', $config['api_key'])) {
        throw new OpenRouterException('key_missing');
    }
    if ($config['completion_url'] !== 'https://openrouter.ai/api/v1/chat/completions') {
        throw new OpenRouterException('destination_invalid');
    }
    $model = $options['model'] ?? $config['model'];
    if ($model !== $config['model'] || !str_ends_with($model, ':free')) {
        throw new OpenRouterException('paid_model_blocked');
    }
    if (!$messages || count($messages) > 8) {
        throw new OpenRouterException('messages_invalid');
    }
    foreach ($messages as $message) {
        if (!is_array($message) || !in_array($message['role'] ?? '', ['system', 'user', 'assistant'], true) ||
            !is_string($message['content'] ?? null) || trim($message['content']) === '') {
            throw new OpenRouterException('messages_invalid');
        }
    }
    if (strlen(json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > 60000) {
        throw new OpenRouterException('request_too_large');
    }
    $tokens = $options['max_completion_tokens'] ?? $options['max_tokens'] ?? 900;
    $temperature = $options['temperature'] ?? 0.25;
    if (!is_int($tokens) || $tokens < 1 || $tokens > 4500 || !is_numeric($temperature) ||
        $temperature < 0 || $temperature > 1) {
        throw new OpenRouterException('parameters_invalid');
    }
    foreach (['tools', 'tool_choice', 'stream', 'metadata'] as $key) {
        if (!empty($options[$key])) {
            throw new OpenRouterException('unsupported_parameter');
        }
    }
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (float)$temperature,
        'max_tokens' => $tokens,
        'stream' => false,
        'provider' => ['max_price' => ['prompt' => 0, 'completion' => 0], 'allow_fallbacks' => false],
    ];
    if (isset($options['response_format'])) {
        if ($options['response_format'] !== ['type' => 'json_object']) {
            throw new OpenRouterException('parameters_invalid');
        }
        $payload['response_format'] = $options['response_format'];
    }
    $live = $transport === null;
    if ($live) {
        openrouterReserveRequest($config, $feature);
        $transport = 'openrouterCurlRequest';
    }
    $started = microtime(true);
    $reply = [];
    $category = null;
    try {
        $reply = $transport($config['completion_url'], $payload, $config);
        return openrouterValidateResponse($reply);
    } catch (Throwable $error) {
        $category = $error instanceof OpenRouterException ? $error->category : 'transport_failure';
        throw $error;
    } finally {
        if ($live) {
            openrouterRecordResult($config, [
                'feature' => $feature, 'model' => $config['model'],
                'http_status' => $reply['status'] ?? 0, 'latency_ms' => (int)round(1000 * (microtime(true) - $started)),
                'category' => $category, 'max_tokens' => $tokens,
                'message_bytes' => strlen(json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'request_bytes' => strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            ] + openrouterResponseMetadata($reply));
        }
    }
}

function openrouterFirstText(array $response): string {
    $text = $response['choices'][0]['message']['content'] ?? null;
    if (!is_string($text)) {
        throw new OpenRouterException('empty_content');
    }
    return trim($text);
}
