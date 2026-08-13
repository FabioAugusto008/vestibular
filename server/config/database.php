<?php
// ============================================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// Edite as credenciais abaixo conforme seu ambiente
// ============================================================

function envValue(string $key, string $default = ''): string {
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

$dbUrl = envValue('DATABASE_URL', envValue('DB_URL', ''));

if ($dbUrl !== '' && ($parsedUrl = parse_url($dbUrl))) {
    $host = $parsedUrl['host'] ?? 'localhost';
    $port = $parsedUrl['port'] ?? '3306';
    $user = $parsedUrl['user'] ?? 'root';
    $pass = $parsedUrl['pass'] ?? 'root';
    $name = ltrim($parsedUrl['path'] ?? '', '/');
    if ($name === '') {
        $name = 'vestibular_estudos';
    }
    
    $charset = 'utf8mb4';
    $ssl = false;
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
        if (isset($queryParams['ssl-mode']) && strtolower($queryParams['ssl-mode']) === 'required') {
            $ssl = true;
        }
        if (isset($queryParams['sslmode']) && strtolower($queryParams['sslmode']) === 'required') {
            $ssl = true;
        }
        if (isset($queryParams['charset'])) {
            $charset = $queryParams['charset'];
        }
    }
    
    define('DB_HOST', $host);
    define('DB_PORT', $port);
    define('DB_NAME', $name);
    define('DB_USER', $user);
    define('DB_PASS', $pass);
    define('DB_CHARSET', $charset);
    define('DB_SSL', $ssl);
} else {
    define('DB_HOST', envValue('DB_HOST', 'localhost'));
    define('DB_PORT', envValue('DB_PORT', '3306'));
    define('DB_NAME', envValue('DB_NAME', 'vestibular_estudos'));
    define('DB_USER', envValue('DB_USER', 'root'));
    define('DB_PASS', envValue('DB_PASS', 'root'));
    define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));
    define('DB_SSL', envValue('DB_SSL', 'false') === 'true');
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            if (defined('DB_SSL') && DB_SSL) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = true;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            if (function_exists('logTechnicalError')) {
                logTechnicalError('getDB connection error', $e);
            }
            die(json_encode([
                'erro' => 'Falha na conexão com o banco de dados.',
                'detalhe' => $e->getMessage()
            ]));
        }
    }
    return $pdo;
}
