<?php
// ============================================================
// HELPERS DE SESSÃO E AUTENTICAÇÃO
// ============================================================

require_once __DIR__ . '/../config/database.php';

define('SESSION_NAME', 'estudai_session');
define('ESTUDAI_VERSION', '0.1.0-alpha');

function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['usuario_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        if (isAjax()) {
            jsonResponse(['erro' => 'Nao autenticado.'], 401);
        }
        header('Location: ../../app/public/index.html');
        exit;
    }
}

function currentUserId(): int {
    initSession();
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

function currentUserName(): string {
    initSession();
    return $_SESSION['usuario_nome'] ?? '';
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function csrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): void {
    initSession();
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        jsonResponse(['erro' => 'Sua sessao de seguranca expirou. Recarregue a pagina e tente novamente.'], 419);
    }
}

function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['erro' => 'Metodo nao permitido.'], 405);
    }
}

function rateLimitGuard(string $bucket, int $limit, int $windowSeconds): void {
    initSession();
    $limit = max(1, $limit);
    $windowSeconds = max(10, $windowSeconds);
    $ip = sanitizeTextValue($_SERVER['REMOTE_ADDR'] ?? 'local', 80);
    $user = (string)($_SESSION['usuario_id'] ?? 'anon');
    $key = hash('sha256', $bucket . '|' . $user . '|' . $ip);
    $now = time();

    if (!isset($_SESSION['rate_limits']) || !is_array($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }

    $hits = $_SESSION['rate_limits'][$key] ?? [];
    if (!is_array($hits)) {
        $hits = [];
    }

    $hits = array_values(array_filter($hits, static fn($timestamp) => is_int($timestamp) && $timestamp > ($now - $windowSeconds)));
    if (count($hits) >= $limit) {
        jsonResponse([
            'erro' => 'Muitas tentativas em pouco tempo. Aguarde um instante e tente novamente.',
            'retry_after' => max(1, ($hits[0] + $windowSeconds) - $now),
        ], 429);
    }

    $hits[] = $now;
    $_SESSION['rate_limits'][$key] = $hits;
}

function requestJsonBody(int $maxBytes = 16000): array {
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > $maxBytes) {
        jsonResponse(['erro' => 'Payload muito grande.'], 413);
    }

    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['erro' => 'JSON invalido.'], 400);
    }

    return $data;
}

function requestPayload(int $maxBytes = 16000): array {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        jsonResponse(['erro' => 'Payload muito grande.'], 413);
    }

    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        return requestJsonBody($maxBytes);
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > $maxBytes) {
        jsonResponse(['erro' => 'Payload muito grande.'], 413);
    }

    if ($raw === '') {
        return [];
    }

    $parsed = [];
    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function clampString(string $value, int $maxLength): string {
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function sanitizeTextValue($value, int $maxLength): string {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return clampString($value, $maxLength);
}

function normalizeStringList($value, int $maxItems = 12, int $maxLength = 80): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = explode(',', $value);
        }
    }

    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach ($value as $item) {
        $clean = sanitizeTextValue($item, $maxLength);
        if ($clean !== '' && !in_array($clean, $items, true)) {
            $items[] = $clean;
        }
        if (count($items) >= $maxItems) {
            break;
        }
    }

    return $items;
}

function jsonEncodeSafe($value): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    return $json === false ? 'null' : $json;
}

function dbTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function dbColumnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function dbEnumAllows(PDO $db, string $table, string $column, string $value): bool {
    $stmt = $db->prepare("
        SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    $type = (string)($stmt->fetchColumn() ?: '');
    return $type !== '' && strpos($type, "'" . str_replace("'", "''", $value) . "'") !== false;
}

function dbOriginValue(PDO $db, string $table, string $preferred = 'banco'): string {
    return dbEnumAllows($db, $table, 'origem', $preferred) ? $preferred : 'manual';
}

function logTechnicalError(string $context, Throwable $error): void {
    $line = '[' . date('c') . '] ' . $context . ': ' . $error->getMessage() . PHP_EOL;
    $path = dirname(__DIR__, 2) . '/storage/logs/app.log';
    $dir = dirname($path);
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($path, $line, FILE_APPEND);
        return;
    }
    error_log(trim($line));
}

function questoesApprovedWhere(string $alias = 'q'): string {
    return "{$alias}.status IN ('aprovada','revisada','aprovado','revisado','ativo')";
}

function questoesAlternativasTable(PDO $db): ?string {
    if (dbTableExists($db, 'questoes_alternativas')) {
        return 'questoes_alternativas';
    }
    if (dbTableExists($db, 'alternativas')) {
        return 'alternativas';
    }
    return null;
}

function questoesAlternativasByIds(PDO $db, array $questaoIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $questaoIds))));
    $table = questoesAlternativasTable($db);
    if (!$ids || !$table) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT id, questao_id, letra, texto, correta
        FROM {$table}
        WHERE questao_id IN ({$placeholders})
        ORDER BY questao_id ASC, FIELD(letra, 'A', 'B', 'C', 'D', 'E'), letra ASC
    ");
    $stmt->execute($ids);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $qid = (int)$row['questao_id'];
        $letra = strtoupper((string)$row['letra']);
        if (!isset($map[$qid])) {
            $map[$qid] = [];
        }
        $map[$qid][$letra] = [
            'id' => (int)$row['id'],
            'letra' => $letra,
            'texto' => (string)$row['texto'],
            'correta' => (bool)$row['correta'],
        ];
    }
    return $map;
}

function questoesAttachAlternativas(PDO $db, array $questoes): array {
    $alternativasMap = questoesAlternativasByIds($db, array_column($questoes, 'id'));
    foreach ($questoes as &$questao) {
        $qid = (int)$questao['id'];
        $alternativas = $alternativasMap[$qid] ?? [];
        $questao['alternativas'] = [];
        $correta = strtoupper((string)($questao['resposta_correta'] ?? ''));
        foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
            $alt = $alternativas[$letra] ?? null;
            $questao['alternativa_' . strtolower($letra)] = $alt['texto'] ?? ($questao['alternativa_' . strtolower($letra)] ?? '');
            if ($alt) {
                $questao['alternativas'][] = $alt;
                if ($alt['correta']) {
                    $correta = $letra;
                    $questao['alternativa_correta_id'] = $alt['id'];
                }
            }
        }
        $questao['resposta_correta'] = $correta;
    }
    unset($questao);
    return $questoes;
}

function questoesRespostaCorreta(PDO $db, int $questaoId): ?array {
    $table = questoesAlternativasTable($db);
    if ($table) {
        $stmt = $db->prepare("
            SELECT id, letra
            FROM {$table}
            WHERE questao_id = ? AND correta = 1
            LIMIT 1
        ");
        $stmt->execute([$questaoId]);
        $row = $stmt->fetch();
        if ($row) {
            return ['alternativa_id' => (int)$row['id'], 'letra' => strtoupper((string)$row['letra'])];
        }
    }

    if (dbColumnExists($db, 'questoes', 'resposta_correta')) {
        $stmt = $db->prepare('SELECT resposta_correta FROM questoes WHERE id = ? LIMIT 1');
        $stmt->execute([$questaoId]);
        $letra = strtoupper((string)($stmt->fetchColumn() ?: ''));
        return $letra !== '' ? ['alternativa_id' => null, 'letra' => $letra] : null;
    }

    return null;
}

function questoesAlternativaIdPorLetra(PDO $db, int $questaoId, string $letra): ?int {
    $table = questoesAlternativasTable($db);
    if (!$table) {
        return null;
    }
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE questao_id = ? AND letra = ? LIMIT 1");
    $stmt->execute([$questaoId, strtoupper($letra)]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function questoesSalvarRespostaModelo(PDO $db, int $usuarioId, int $questaoId, ?int $alternativaId, bool $correta, ?int $tempoResposta = null): void {
    if (!dbTableExists($db, 'questoes_respostas_usuario')) {
        return;
    }

    $stmt = $db->prepare('
        INSERT INTO questoes_respostas_usuario
            (usuario_id, questao_id, alternativa_id, correta, tempo_resposta)
        VALUES
            (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $usuarioId,
        $questaoId,
        $alternativaId,
        $correta ? 1 : 0,
        $tempoResposta,
    ]);
}

function questoesBuscarAprovadas(PDO $db, ?string $materia, ?string $conteudo, int $quantidade = 5): array {
    if (!dbTableExists($db, 'questoes')) {
        return ['questoes' => [], 'escopo' => 'indisponivel', 'aviso' => 'Base de questoes indisponivel.'];
    }

    $quantidade = max(1, min(40, $quantidade));
    $baseSelect = "
        SELECT q.id, q.area, q.materia, q.conteudo, q.competencia, q.habilidade, q.dificuldade,
               q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status
        FROM questoes q
        WHERE " . questoesApprovedWhere('q');

    $attempts = [];
    $materia = sanitizeTextValue($materia ?? '', 80);
    $conteudo = sanitizeTextValue($conteudo ?? '', 180);
    if ($materia !== '' && $conteudo !== '') {
        $attempts[] = [
            'sql' => $baseSelect . " AND q.materia = ? AND q.conteudo LIKE ? ORDER BY RAND() LIMIT {$quantidade}",
            'params' => [$materia, '%' . $conteudo . '%'],
            'escopo' => 'conteudo',
        ];
    }
    if ($materia !== '') {
        $attempts[] = [
            'sql' => $baseSelect . " AND q.materia = ? ORDER BY RAND() LIMIT {$quantidade}",
            'params' => [$materia],
            'escopo' => 'materia',
        ];
    }
    $attempts[] = [
        'sql' => $baseSelect . " ORDER BY RAND() LIMIT {$quantidade}",
        'params' => [],
        'escopo' => 'geral',
    ];

    foreach ($attempts as $attempt) {
        $stmt = $db->prepare($attempt['sql']);
        $stmt->execute($attempt['params']);
        $rows = questoesAttachAlternativas($db, $stmt->fetchAll());
        $rows = array_values(array_filter($rows, static function ($questao) {
            foreach (['alternativa_a', 'alternativa_b', 'alternativa_c', 'alternativa_d', 'alternativa_e'] as $key) {
                if (trim((string)($questao[$key] ?? '')) === '') {
                    return false;
                }
            }
            return trim((string)($questao['enunciado'] ?? '')) !== '' && trim((string)($questao['resposta_correta'] ?? '')) !== '';
        }));
        if ($rows) {
            if ($conteudo !== '' && $attempt['escopo'] !== 'conteudo') {
                return [
                    'questoes' => [],
                    'escopo' => 'vazio',
                    'aviso' => 'Nao encontramos questoes suficientes para este conteudo ainda.',
                ];
            }
            if ($materia !== '' && $conteudo === '' && $attempt['escopo'] !== 'materia') {
                return [
                    'questoes' => [],
                    'escopo' => 'vazio',
                    'aviso' => 'Nao encontramos questoes suficientes para esta materia ainda.',
                ];
            }
            if ($attempt['escopo'] !== 'geral' && count($rows) < $quantidade) {
                return [
                    'questoes' => [],
                    'escopo' => 'vazio',
                    'aviso' => 'Nao encontramos questoes suficientes para este conteudo ainda.',
                ];
            }
            $aviso = null;
            if ($attempt['escopo'] !== 'conteudo' && $conteudo !== '') {
                $aviso = 'Não encontramos questões suficientes para este conteúdo ainda. Mostrando questões aprovadas da base mais próxima.';
            } elseif (count($rows) < $quantidade) {
                $aviso = 'Encontramos menos questões do que o solicitado para este filtro.';
            }
            return ['questoes' => $rows, 'escopo' => $attempt['escopo'], 'aviso' => $aviso];
        }
    }

    return [
        'questoes' => [],
        'escopo' => 'vazio',
        'aviso' => 'Não encontramos questões suficientes para este conteúdo ainda.',
    ];
}

function gerarQuestoesDodia(string $data): void {
    $db = getDB();
    if (!dbTableExists($db, 'questoes') || !dbTableExists($db, 'questoes_do_dia')) {
        return;
    }

    $count = $db->prepare('SELECT COUNT(*) FROM questoes_do_dia WHERE data = ?');
    $count->execute([$data]);
    $faltam = max(0, 20 - (int)$count->fetchColumn());
    if ($faltam <= 0) {
        return;
    }

    $stmt = $db->prepare("
        INSERT IGNORE INTO questoes_do_dia (data, questao_id)
        SELECT ?, q.id
        FROM questoes q
        WHERE " . questoesApprovedWhere('q') . "
          AND q.id NOT IN (SELECT questao_id FROM questoes_do_dia WHERE data = ?)
        ORDER BY RAND()
        LIMIT {$faltam}
    ");
    $stmt->execute([$data, $data]);
}
