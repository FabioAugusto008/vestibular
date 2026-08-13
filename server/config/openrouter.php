<?php

if (!function_exists('estudai_openrouter_env')) {
    function estudai_openrouter_env(string $key, string $default = ''): string {
        static $dotenv = null;

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }

        if ($dotenv === null) {
            $dotenv = [];
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (is_file($envPath) && is_readable($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$envKey, $envValue] = array_map('trim', explode('=', $line, 2));
                    $dotenv[$envKey] = trim($envValue, "\"'");
                }
            }
        }

        if (isset($dotenv[$key]) && $dotenv[$key] !== '') {
            return (string) $dotenv[$key];
        }

        return $default;
    }
}

$completionUrl = rtrim(estudai_openrouter_env(
    'OPENROUTER_BASE_URL',
    'https://openrouter.ai/api/v1/chat/completions'
), '/');

if (substr($completionUrl, -17) !== '/chat/completions') {
    $completionUrl .= '/chat/completions';
}

$timeout = (int) estudai_openrouter_env('OPENROUTER_TIMEOUT_SECONDS', '120');
$timeout = max(10, min(90, $timeout));

return [
    'provider' => 'openrouter',
    'completion_url' => $completionUrl,
    'api_key' => estudai_openrouter_env('OPENROUTER_API_KEY', ''),
    'model' => estudai_openrouter_env('OPENROUTER_MODEL', 'liquid/lfm-2.5-1.2b-instruct:free'),
    'site_url' => estudai_openrouter_env('OPENROUTER_SITE_URL', 'http://localhost/vestibular'),
    'site_name' => estudai_openrouter_env('OPENROUTER_SITE_NAME', 'EstudAI'),
    'timeout_seconds' => $timeout,
];
