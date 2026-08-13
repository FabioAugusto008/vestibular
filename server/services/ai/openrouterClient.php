<?php

class OpenRouterRateLimitException extends RuntimeException {}

function openrouterChatCompletion(array $messages, array $options = []): array {
    $config = require __DIR__ . '/../../config/openrouter.php';

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extensao cURL do PHP nao esta habilitada.');
    }

    if (empty($config['api_key']) || strpos($config['api_key'], '<') !== false) {
        throw new RuntimeException('OPENROUTER_API_KEY nao configurada.');
    }

    if (empty($messages)) {
        throw new InvalidArgumentException('Mensagens da IA nao informadas.');
    }

    $payload = [
        'model' => $options['model'] ?? $config['model'],
        'messages' => $messages,
        'temperature' => $options['temperature'] ?? 0.4,
        'max_completion_tokens' => $options['max_completion_tokens'] ?? $options['max_tokens'] ?? 900,
    ];

    foreach (['response_format', 'tools', 'tool_choice', 'stream', 'metadata'] as $optionalKey) {
        if (array_key_exists($optionalKey, $options)) {
            $payload[$optionalKey] = $options[$optionalKey];
        }
    }

    $headers = [
        'Authorization: Bearer ' . $config['api_key'],
        'Content-Type: application/json',
    ];

    if (!empty($config['site_url'])) {
        $headers[] = 'HTTP-Referer: ' . $config['site_url'];
    }
    if (!empty($config['site_name'])) {
        $headers[] = 'X-OpenRouter-Title: ' . $config['site_name'];
    }

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($encodedPayload === false) {
        throw new RuntimeException('Payload da IA invalido.');
    }

    $ch = curl_init($config['completion_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $encodedPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => (int) $config['timeout_seconds'],
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Falha ao chamar OpenRouter: ' . $curlError);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Resposta invalida do OpenRouter.');
    }

    if ($status >= 400) {
        $message = $data['error']['message'] ?? 'Erro na chamada OpenRouter.';
        if ($status === 429) {
            throw new OpenRouterRateLimitException('A IA atingiu o limite temporario do provedor. Tente novamente em alguns instantes.', 429);
        }
        throw new RuntimeException($message, $status);
    }

    return $data;
}

function openrouterFirstText(array $response): string {
    return trim((string)($response['choices'][0]['message']['content'] ?? ''));
}
