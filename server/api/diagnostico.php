<?php
// api/diagnostico.php - Diagnostico funcional do estudante

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/ai/estudaiService.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'gerar' : 'status');

function diagnosticoDecodeList($value): array {
    $decoded = json_decode((string)($value ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function diagnosticoPerfil(PDO $db, int $uid): ?array {
    $stmt = $db->prepare('SELECT * FROM estudo_perfis WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $dias = diagnosticoDecodeList($row['dias_semana_json'] ?? '');
    return [
        'id' => (int)$row['id'],
        'objetivo' => (string)$row['objetivo'],
        'data_prova' => $row['data_prova'] ?: null,
        'horas_dia' => isset($row['horas_dia']) ? (float)$row['horas_dia'] : null,
        'dias' => $dias,
        'dias_semana' => $dias,
        'dificuldades' => diagnosticoDecodeList($row['dificuldades_json'] ?? ''),
        'prioridades' => diagnosticoDecodeList($row['prioridades_json'] ?? ''),
        'preferencia' => $row['preferencia_estudo'] ?? 'misto',
        'preferencia_estudo' => $row['preferencia_estudo'] ?? 'misto',
        'meta_semanal' => $row['meta_semanal'] ?? '',
        'notificacoes' => !empty($row['notificacoes']),
    ];
}

function diagnosticoUltimo(PDO $db, int $uid): ?array {
    if (!dbTableExists($db, 'ia_historico')) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT resposta_json, status, criado_em
        FROM ia_historico
        WHERE usuario_id = ? AND tipo = 'diagnostico'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || empty($row['resposta_json'])) {
        return null;
    }
    $decoded = json_decode((string)$row['resposta_json'], true);
    if (!is_array($decoded)) {
        return null;
    }
    $decoded['criado_em'] = $row['criado_em'] ?? null;
    return $decoded;
}

function diagnosticoRegistrarHistorico(PDO $db, int $uid, array $perfil, array $resultado): void {
    if (!dbTableExists($db, 'ia_historico')) {
        return;
    }

    $usage = $resultado['usage'] ?? [];
    $status = ($resultado['origem'] ?? '') === 'ia' ? 'sucesso' : 'erro';
    $erro = $status === 'erro' ? sanitizeTextValue($resultado['erro_tecnico'] ?? ($resultado['erro'] ?? ''), 600) : null;
    $entradaResumo = 'Objetivo: ' . ($perfil['objetivo'] ?? 'nao definido') .
        '; horas/dia: ' . ($perfil['horas_dia'] ?? 'n/a') .
        '; dificuldades: ' . implode(', ', array_slice($perfil['dificuldades'] ?? [], 0, 5));

    $params = [
        ':usuario_id' => $uid,
        ':provider' => $resultado['provider'] ?? 'openrouter',
        ':modelo' => $resultado['modelo'] ?? null,
        ':tipo' => 'diagnostico',
        ':entrada_resumo' => sanitizeTextValue($entradaResumo, 900),
        ':resposta_json' => jsonEncodeSafe([
            'ok' => true,
            'origem' => $resultado['origem'] ?? 'erro',
            'diagnostico' => $resultado['diagnostico'] ?? [],
        ]),
        ':status' => $status,
        ':erro' => $erro,
    ];

    if (dbColumnExists($db, 'ia_historico', 'tokens_entrada')) {
        $stmt = $db->prepare('
            INSERT INTO ia_historico
                (usuario_id, provider, modelo, tipo, entrada_resumo, resposta_json, status, erro, tokens_entrada, tokens_saida)
            VALUES
                (:usuario_id, :provider, :modelo, :tipo, :entrada_resumo, :resposta_json, :status, :erro, :tokens_entrada, :tokens_saida)
        ');
        $params[':tokens_entrada'] = isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : null;
        $params[':tokens_saida'] = isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : null;
        $stmt->execute($params);
        return;
    }

    $stmt = $db->prepare('
        INSERT INTO ia_historico
            (usuario_id, provider, modelo, tipo, entrada_resumo, resposta_json, status, erro, prompt_tokens, completion_tokens)
        VALUES
            (:usuario_id, :provider, :modelo, :tipo, :entrada_resumo, :resposta_json, :status, :erro, :tokens_entrada, :tokens_saida)
    ');
    $params[':tokens_entrada'] = isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : 0;
    $params[':tokens_saida'] = isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : 0;
    $stmt->execute($params);
}

try {
    if (!dbTableExists($db, 'estudo_perfis')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'status':
            $perfil = diagnosticoPerfil($db, $uid);
            $ultimo = diagnosticoUltimo($db, $uid);
            jsonResponse([
                'ok' => true,
                'tem_perfil' => $perfil !== null,
                'tem_diagnostico' => $ultimo !== null,
                'diagnostico' => $ultimo['diagnostico'] ?? null,
                'origem' => $ultimo['origem'] ?? null,
            ]);
            break;

        case 'carregar_ultimo':
            jsonResponse([
                'ok' => true,
                'resultado' => diagnosticoUltimo($db, $uid),
            ]);
            break;

        case 'gerar':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('diagnostico_gerar', 8, 3600);

            $perfil = diagnosticoPerfil($db, $uid);
            if (!$perfil) {
                jsonResponse(['erro' => 'Complete o perfil de estudo antes de gerar o diagnostico.'], 400);
            }

            $resultado = estudaiGerarDiagnostico($perfil);
            diagnosticoRegistrarHistorico($db, $uid, $perfil, $resultado);
            if (empty($resultado['ok'])) {
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao foi possivel gerar o diagnostico agora.'], 503);
            }

            jsonResponse([
                'ok' => true,
                'origem' => $resultado['origem'] ?? 'ia',
                'diagnostico' => $resultado['diagnostico'] ?? [],
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('diagnostico_api_error', $e);
    try {
        if (dbTableExists($db, 'ia_historico')) {
            $stmt = $db->prepare('
                INSERT INTO ia_historico (usuario_id, provider, modelo, tipo, entrada_resumo, status, erro)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$uid, 'openrouter', null, 'diagnostico', 'Falha ao gerar diagnostico', 'erro', sanitizeTextValue($e->getMessage(), 600)]);
        }
    } catch (Throwable $ignored) {
    }
    jsonResponse(['erro' => 'Nao foi possivel gerar o diagnostico agora.'], 500);
}
