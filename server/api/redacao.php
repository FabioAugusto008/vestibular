<?php
// api/redacao.php - Redacao ENEM com analise orientativa

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/ai/openrouterClient.php';
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

function redacaoGerarAnaliseIa(string $tema, string $texto): array {
    $prompt = estudaiSystemPrompt() . "\n\n" .
        "Voce vai fazer apenas uma analise orientativa de redacao ENEM. " .
        "Nao prometa nota oficial, nao substitua corretor humano e nao invente regras. " .
        "Responda em JSON valido com: resumo, competencias[{competencia, comentario}], pontos_fortes[], pontos_de_melhoria[], sugestoes[], proximos_passos[], estimativa_nao_oficial=true. Campos de lista devem ser arrays, nunca string.";

    $response = openrouterChatCompletion([
        ['role' => 'system', 'content' => $prompt],
        ['role' => 'user', 'content' => "Tema: {$tema}\n\nRedacao:\n{$texto}"],
    ], [
        'temperature' => 0.25,
        'max_tokens' => 900,
        'response_format' => ['type' => 'json_object'],
    ]);

    $raw = openrouterFirstText($response);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return redacaoAnaliseFallback($tema, $texto);
    }
    $decoded['estimativa_nao_oficial'] = true;
    $decoded['tema'] = $tema;
    return redacaoNormalizarAnalise($decoded);
}

try {
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
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(32000);
            $tema = sanitizeTextValue($payload['tema'] ?? '', 180);
            $texto = sanitizeTextValue($payload['texto'] ?? '', 12000);
            if ($tema === '' || $texto === '') {
                jsonResponse(['erro' => 'Informe o tema e o texto da redacao.'], 400);
            }
            if (!redacaoTableReady($db)) {
                jsonResponse(['ok' => true, 'persistido' => false, 'aviso' => 'Migration de redacoes ainda nao aplicada.']);
            }
            $id = (int)($payload['redacao_id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare('
                    UPDATE redacoes_enem
                    SET tema = ?, texto = ?, status = ?, atualizada_em = CURRENT_TIMESTAMP
                    WHERE id = ? AND usuario_id = ?
                ');
                $stmt->execute([$tema, $texto, 'rascunho', $id, $uid]);
            } else {
                $stmt = $db->prepare('
                    INSERT INTO redacoes_enem (usuario_id, tema, texto, status)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([$uid, $tema, $texto, 'rascunho']);
                $id = (int)$db->lastInsertId();
            }
            jsonResponse(['ok' => true, 'persistido' => true, 'redacao_id' => $id]);
            break;

        case 'analisar':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('redacao_analisar', 6, 3600);
            $payload = requestPayload(36000);
            $tema = sanitizeTextValue($payload['tema'] ?? '', 180);
            $texto = sanitizeTextValue($payload['texto'] ?? '', 12000);
            $textoLen = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
            if ($tema === '' || $textoLen < 300) {
                jsonResponse(['erro' => 'Informe um tema e um texto com desenvolvimento suficiente para analise.'], 400);
            }

            try {
                $analise = redacaoGerarAnaliseIa($tema, $texto);
            } catch (Throwable $e) {
                logTechnicalError('redacao_analise', $e);
                $analise = redacaoAnaliseFallback($tema, $texto);
            }

            $id = (int)($payload['redacao_id'] ?? 0);
            $persistido = false;
            if (redacaoTableReady($db)) {
                if ($id > 0) {
                    $stmt = $db->prepare('
                        UPDATE redacoes_enem
                        SET tema = ?, texto = ?, status = ?, analise_json = ?, atualizada_em = CURRENT_TIMESTAMP
                        WHERE id = ? AND usuario_id = ?
                    ');
                    $stmt->execute([$tema, $texto, 'analisada', jsonEncodeSafe($analise), $id, $uid]);
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO redacoes_enem (usuario_id, tema, texto, status, analise_json)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$uid, $tema, $texto, 'analisada', jsonEncodeSafe($analise)]);
                    $id = (int)$db->lastInsertId();
                }
                $persistido = true;
            }

            jsonResponse([
                'ok' => true,
                'redacao_id' => $id ?: null,
                'persistido' => $persistido,
                'analise' => $analise,
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    logTechnicalError('redacao', $e);
    jsonResponse(['erro' => 'Nao foi possivel processar redacao agora.'], 500);
}
