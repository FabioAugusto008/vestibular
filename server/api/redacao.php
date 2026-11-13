<?php
// api/redacao.php - Redacao ENEM com analise orientativa

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/ai/estudaiService.php';
require_once __DIR__ . '/../services/dailyQuestions.php';
require_once __DIR__ . '/../services/ai/prompts.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

function redacaoTableReady(PDO $db): bool {
    return dbTableExists($db, 'redacoes_enem');
}

function redacaoJson($raw): array {
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function redacaoList($value, int $maxItems = 10, int $maxLength = 220): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/\r\n|\r|\n|;/', $value) ?: [$value];
        }
    }
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $item = $item['texto'] ?? $item['descricao'] ?? $item['comentario'] ?? jsonEncodeSafe($item);
        }
        $clean = sanitizeTextValue($item, $maxLength);
        if ($clean !== '') {
            $items[] = $clean;
        }
        if (count($items) >= $maxItems) {
            break;
        }
    }
    return $items;
}

function redacaoCompetencias($value): array {
    if (!is_array($value)) {
        return [];
    }
    $items = [];
    foreach ($value as $item) {
        if (is_string($item)) {
            $items[] = ['competencia' => '', 'comentario' => sanitizeTextValue($item, 400)];
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $items[] = [
            'competencia' => sanitizeTextValue($item['competencia'] ?? '', 20),
            'comentario' => sanitizeTextValue($item['comentario'] ?? $item['descricao'] ?? '', 500),
        ];
        if (count($items) >= 5) {
            break;
        }
    }
    return $items;
}

function redacaoNormalizarAnalise(array $analise): array {
    $melhorias = redacaoList(
        $analise['pontos_de_melhoria'] ?? $analise['pontos_melhoria'] ?? $analise['sugestoes'] ?? [],
        10,
        260
    );
    $sugestoes = redacaoList($analise['sugestoes'] ?? [], 10, 260);
    return [
        'aviso' => sanitizeTextValue($analise['aviso'] ?? '', 300),
        'origem' => in_array($analise['origem'] ?? '', ['ia', 'fallback'], true) ? $analise['origem'] : 'indisponivel',
        'estimativa_nao_oficial' => true,
        'tema' => sanitizeTextValue($analise['tema'] ?? '', 180),
        'resumo' => sanitizeTextValue($analise['resumo'] ?? '', 900),
        'competencias' => redacaoCompetencias($analise['competencias'] ?? []),
        'pontos_fortes' => redacaoList($analise['pontos_fortes'] ?? [], 10, 260),
        'pontos_de_melhoria' => $melhorias,
        'pontos_melhoria' => $melhorias,
        'sugestoes' => $sugestoes,
        'proximos_passos' => redacaoList($analise['proximos_passos'] ?? $sugestoes, 10, 220),
    ];
}

function redacaoMap(array $row): array {
    $analiseRaw = redacaoJson($row['analise_json'] ?? '');
    return [
        'id' => (int)$row['id'],
        'tema' => $row['tema'] ?? '',
        'texto' => $row['texto'] ?? '',
        'status' => $row['status'] ?? 'rascunho',
        'analise' => $analiseRaw ? redacaoNormalizarAnalise($analiseRaw) : [],
        'criada_em' => $row['criada_em'] ?? null,
        'atualizada_em' => $row['atualizada_em'] ?? null,
    ];
}

function redacaoAnaliseFallback(string $tema, string $texto): array {
    $palavras = preg_split('/\s+/', trim($texto)) ?: [];
    return redacaoNormalizarAnalise([
        'aviso' => 'Analise orientativa local. A IA nao ficou disponivel agora.',
        'origem' => 'fallback',
        'estimativa_nao_oficial' => true,
        'tema' => $tema,
        'resumo' => 'Seu texto foi salvo. Revise tese, repertorio, coesao e proposta de intervencao antes de enviar para avaliacao humana.',
        'competencias' => [
            ['competencia' => 'C1', 'comentario' => 'Revise norma-padrao, pontuacao e concordancia.'],
            ['competencia' => 'C2', 'comentario' => 'Confira se a tese responde diretamente ao tema.'],
            ['competencia' => 'C3', 'comentario' => 'Organize argumentos com exemplos claros e progressao.'],
            ['competencia' => 'C4', 'comentario' => 'Use conectivos para ligar ideias entre frases e paragrafos.'],
            ['competencia' => 'C5', 'comentario' => 'Garanta uma proposta com agente, acao, meio e finalidade.'],
        ],
        'pontos_fortes' => count($palavras) >= 250 ? ['Texto com extensao proxima de uma redacao ENEM.'] : [],
        'pontos_de_melhoria' => count($palavras) < 250 ? ['Desenvolva mais os argumentos para aproximar a extensao do padrao ENEM.'] : [],
        'proximos_passos' => ['Releia o tema', 'Marque a tese', 'Cheque a proposta de intervencao', 'Peça avaliacao humana quando possivel'],
    ]);
}

function redacaoGerarAnaliseIa(string $tema, string $texto, ?callable $transport = null): array {
    $prompt = estudaiSystemPrompt() . "\n\n" .
        "Voce vai fazer apenas uma analise orientativa de redacao ENEM. " .
        "Nao prometa nota oficial, nao substitua corretor humano e nao invente regras. " .
        "Responda em JSON valido com: resumo, competencias[{competencia, comentario}], pontos_fortes[], pontos_de_melhoria[], sugestoes[], proximos_passos[], estimativa_nao_oficial=true. Campos de lista devem ser arrays, nunca string.";

    $response = openrouterChatCompletion([
        ['role' => 'system', 'content' => $prompt],
        ['role' => 'user', 'content' => "Tema: {$tema}\n\nRedacao:\n{$texto}"],
    ], [
        'feature' => 'redacao',
        'temperature' => 0.25,
        'max_tokens' => 900,
        'response_format' => ['type' => 'json_object'],
    ], $transport);

    $raw = openrouterFirstText($response);
    $decoded = estudaiDecodeJsonText($raw);
    if (!$decoded) {
        throw new RuntimeException('Analise da IA fora do contrato.');
    }
    $decoded = estudaiAiRedacao($decoded);
    $decoded['origem'] = 'ia';
    $decoded['tema'] = $tema;
    return redacaoNormalizarAnalise($decoded);
}

function redacaoProcessar(PDO $db, int $uid, array $payload, bool $analisar, ?callable $transport = null): array {
    estudaiRequireTestDatabase($db);
    if (!is_string($payload['tema'] ?? null) || !is_string($payload['texto'] ?? null)) {
        questionFlowFail('Informe tema e texto validos.', 400);
    }
    $tema = trim($payload['tema']);
    $texto = trim($payload['texto']);
    if ($tema === '' || $texto === '' || mb_strlen($tema, 'UTF-8') > 180 ||
        mb_strlen($texto, 'UTF-8') > 12000 || ($analisar && mb_strlen($texto, 'UTF-8') < 300)) {
        questionFlowFail('Tema ou texto fora dos limites da redacao.', 400);
    }
    $tema = sanitizeTextValue($tema, 180);
    $texto = sanitizeTextValue($texto, 12000);
    $rawId = $payload['redacao_id'] ?? null;
    $id = ($rawId === null || $rawId === 0 || $rawId === '0') ? 0 : questionPositiveId($rawId);
    if ($tema === '' || $texto === '' || ($analisar && mb_strlen($texto, 'UTF-8') < 300)) {
        questionFlowFail('Informe texto suficiente para a redacao.', 400);
    }
    $db->beginTransaction();
    try {
        questionLockUser($db, $uid);
        $stmt = $id
            ? $db->prepare('SELECT * FROM redacoes_enem WHERE id = ? AND usuario_id = ?')
            : $db->prepare('SELECT * FROM redacoes_enem WHERE usuario_id = ? AND tema = ? AND texto = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute($id ? [$id, $uid] : [$uid, $tema, $texto]);
        $existing = $stmt->fetch();
        if ($id && !$existing) {
            questionFlowFail('Redacao indisponivel.', 404);
        }
        $same = $existing && $existing['tema'] === $tema && $existing['texto'] === $texto;
        if ($same && (!$analisar || $existing['status'] === 'analisada')) {
            $mapped = redacaoMap($existing);
            $db->commit();
            return [
                'ok' => true, 'persistido' => true, 'reutilizado' => true,
                'redacao_id' => $mapped['id'], 'analise' => $mapped['analise'],
                'origem' => $mapped['analise']['origem'] ?? null,
            ];
        }
        rateLimitGuard('redacao_' . ($analisar ? 'analisar' : 'salvar'), 6, 3600);
        $analise = null;
        if ($analisar) {
            try {
                $analise = redacaoGerarAnaliseIa($tema, $texto, $transport);
            } catch (Throwable $error) {
                logTechnicalError('redacao_analise', $error);
                $analise = redacaoAnaliseFallback($tema, $texto);
            }
        }
        $status = $analisar ? 'analisada' : 'rascunho';
        if ($existing) {
            $id = (int)$existing['id'];
            $stmt = $db->prepare('UPDATE redacoes_enem SET tema = ?, texto = ?, status = ?, analise_json = ?, atualizada_em = CURRENT_TIMESTAMP WHERE id = ? AND usuario_id = ?');
            $stmt->execute([$tema, $texto, $status, $analise ? jsonEncodeSafe($analise) : null, $id, $uid]);
        } else {
            $stmt = $db->prepare('INSERT INTO redacoes_enem (usuario_id, tema, texto, status, analise_json) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$uid, $tema, $texto, $status, $analise ? jsonEncodeSafe($analise) : null]);
            $id = (int)$db->lastInsertId();
        }
        $db->commit();
        return [
            'ok' => true, 'persistido' => true, 'reutilizado' => false,
            'redacao_id' => $id, 'analise' => $analise,
            'origem' => $analise['origem'] ?? null,
        ];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

try {
    [$action, $payload] = questionFlowAction(['GET' => ['listar'], 'POST' => ['salvar', 'analisar']], 65536);
    beginDashboardReadOnlyGet($db, ['listar']);
    if (!redacaoTableReady($db)) jsonResponse(['erro' => 'Historico de redacao indisponivel.'], 503);
    switch ($action) {
        case 'listar':
            if (!redacaoTableReady($db)) {
                jsonResponse(['ok' => true, 'redacoes' => [], 'aviso' => 'Historico de redacoes aguardando migration.']);
            }
            $stmt = $db->prepare('
                SELECT *
                FROM redacoes_enem
                WHERE usuario_id = ?
                ORDER BY atualizada_em DESC, id DESC
                LIMIT 30
            ');
            $stmt->execute([$uid]);
            jsonResponse(['ok' => true, 'redacoes' => array_map('redacaoMap', $stmt->fetchAll())]);
            break;

        case 'salvar':
        case 'analisar':
            validateCsrfToken();
            jsonResponse(redacaoProcessar($db, $uid, $payload, $action === 'analisar'));
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('redacao', $e);
    jsonResponse(['erro' => $e instanceof QuestionFlowError ? $e->getMessage() : 'Nao foi possivel processar redacao agora.'], $e instanceof QuestionFlowError ? $e->getCode() : 503);
}
