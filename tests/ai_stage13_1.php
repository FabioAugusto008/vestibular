<?php
// Synthetic fixtures only. Every completion has an injected in-memory transport.
require_once __DIR__ . '/ai_stage13_bootstrap.php';
putenv('ESTUDAI_AI_DISABLED=false');
putenv('ESTUDAI_AI_FEATURES=plano_semanal,diagnostico');
putenv('OPENROUTER_API_KEY=sk-or-stage13-1-sentinel');
putenv('OPENROUTER_MODEL=tests/model:free');
putenv('OPENROUTER_BASE_URL=https://openrouter.ai/api/v1');
$ai13Checks = [];
$fixture = ai13Fixture();
$fence = str_repeat(chr(96), 3);
$json = json_encode($fixture['plan'], JSON_UNESCAPED_UNICODE);
foreach ([$fence . 'json' . "\n" . $json, $json . "\n" . $fence, $fence . 'javascript' . "\n" . $json . "\n" . $fence] as $raw) {
    ai13Check(estudaiDecodeJsonText($raw) === null, 'unbalanced_or_wrong_markdown_rejected');
}
ai13Check(estudaiDecodeJsonText($fence . "json\n" . $json . "\n" . $fence) === $fixture['plan'], 'balanced_json_fence_accepted');
ai13Check(estudaiDecodeJsonText($json) === $fixture['plan'], 'plain_object_accepted');

function stage131Envelope(string $content, string $finish = 'stop', array $extra = []): array {
    return ['status' => 200, 'body' => json_encode(array_replace_recursive([
        'model' => 'tests/model:free',
        'choices' => [['finish_reason' => $finish, 'message' => ['content' => $content]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 90, 'cost' => 0],
    ], $extra))];
}
$payloads = [];
$capture = static function ($url, $payload) use (&$payloads, $fixture): array {
    $payloads[] = $payload;
    return stage131Envelope(json_encode($payload['max_tokens'] === 2600 ? $fixture['plan'] : $fixture['diagnostic']));
};
ai13Check(estudaiGerarPlanoSemanal($fixture['input'], $capture)['ok'], 'weekly_complete_contract');
ai13Check(estudaiGerarDiagnostico($fixture['profile'], $capture)['ok'], 'diagnostic_complete_contract');
foreach ($payloads as $payload) {
    ai13Check(array_keys($payload) === ['model', 'messages', 'temperature', 'max_tokens', 'stream', 'provider', 'response_format'], 'exact_final_payload_no_reasoning_or_extra_parameters');
    ai13Check($payload['response_format'] === ['type' => 'json_object'], 'json_object_only');
}
ai13Check($payloads[0]['max_tokens'] === 2600 && $payloads[1]['max_tokens'] === 4500, 'generation_limits_unchanged');
$bad = $fixture['plan'];
unset($bad['tarefas'][0]['materia']);
$extra = $fixture['plan'];
$extra['usuario_id'] = 999;
$extra['tarefas'][0]['sql'] = 'DROP TABLE ficticio';
$cases = [
    'truncated_json' => [substr($json, 0, -20), 'stop', false],
    'missing_field' => [json_encode($bad), 'stop', false],
    'textual_output' => ['Nao consegui montar o plano.', 'stop', false],
    'markdown' => [$fence . "json\n" . $json . "\n" . $fence, 'stop', true],
    'length_even_if_valid_json' => [$json, 'length', false],
    'content_filter' => [$json, 'content_filter', false],
    'unexpected_extra_fields' => [json_encode($extra), 'stop', true],
];
foreach ($cases as $name => [$content, $finish, $ok]) {
    $calls = 0;
    $result = estudaiGerarPlanoSemanal($fixture['input'], static function () use ($content, $finish, &$calls) {
        $calls++;
        return stage131Envelope($content, $finish);
    });
    ai13Check($result['ok'] === $ok && $calls === 1, $name . '_one_attempt');
    if ($ok) ai13Check(!isset($result['plano']['usuario_id']) && !isset($result['plano']['tarefas'][0]['sql']), $name . '_extras_never_executed_or_returned');
}
$refusal = stage131Envelope($json, 'stop', ['choices' => [['message' => ['refusal' => 'Synthetic refusal']]]]);
$result = estudaiGerarPlanoSemanal($fixture['input'], fn() => $refusal);
ai13Check(!$result['ok'], 'explicit_refusal_never_accepted_as_plan');
foreach (['0', []] as $value) {
    $refusal = stage131Envelope($json, 'stop', ['choices' => [['message' => ['refusal' => $value]]]]);
    ai13Check(!estudaiGerarPlanoSemanal($fixture['input'], fn() => $refusal)['ok'], 'nonempty_or_malformed_refusal_rejected');
}

class Stage131NoDatabase extends PDO { public function __construct() {} }
foreach (['unsupported_parameter', 'invalid_payload', 401, 403, 404, 429, 500, 503, 'timeout', 'network', 'empty', 'unavailable_model'] as $case) {
    $calls = 0;
    $transport = static function () use ($case, &$calls): array {
        $calls++;
        if (in_array($case, ['timeout', 'network'], true)) throw new OpenRouterException($case);
        $status = is_int($case) ? $case : ($case === 'unavailable_model' ? 404 : 400);
        return ['status' => $case === 'empty' ? 200 : $status,
            'body' => $case === 'empty' ? '' : json_encode(['error' => ['code' => $status, 'message' => $case . ' private sk-or-stage13-1-sentinel']])];
    };
    $result = planoGerarSemanalValidado(new Stage131NoDatabase(), 2, $fixture['profile'], $fixture['input'], $fixture['window'], $transport);
    ai13Check($result['ok'] && $result['origem'] === 'fallback' && $calls === 1, 'fallback_' . $case);
    ai13Check(!str_contains($result['aviso_usuario'], 'sentinel'), 'safe_public_fallback_' . $case);
}
$metadata = openrouterResponseMetadata(stage131Envelope($json, 'length', ['usage' => ['completion_tokens_details' => ['reasoning_tokens' => 80]] ]));
ai13Check($metadata['finish_reason'] === 'length' && $metadata['prompt_tokens'] === 100 && $metadata['completion_tokens'] === 90 && $metadata['reasoning_tokens'] === 80, 'safe_usage_and_finish_evidence');
ai13Check($metadata['content_bytes'] === strlen($json) && !$metadata['refusal_present'], 'content_shape_without_body');
$metadata = openrouterResponseMetadata(stage131Envelope('sk-or-stage13-1-sentinel', 'sk-or-stage13-1-sentinel', [
    'model' => 'sk-or-stage13-1-sentinel', 'usage' => ['cost' => -1, 'prompt_tokens' => 'secret', 'completion_tokens' => -1],
    'choices' => [['message' => ['refusal' => 'private body']]],
]));
ai13Check(!str_contains(json_encode($metadata), 'sentinel') && $metadata['finish_reason'] === 'other' && $metadata['refusal_present'], 'untrusted_metadata_cannot_log_secret');
ai13Check($metadata['actual_model'] === null && $metadata['observed_cost'] === null && $metadata['prompt_tokens'] === null && $metadata['completion_tokens'] === null, 'invalid_usage_and_model_not_recorded');
foreach (['', '{', 'null', '[]'] as $raw) {
    $metadata = openrouterResponseMetadata(['body' => $raw]);
    ai13Check($metadata['actual_model'] === null && $metadata['content_bytes'] === null, 'malformed_metadata_safe');
}
$messages = estudaiPlanoSemanalPrompt($fixture['input']);
ai13Check(strlen(json_encode($messages, JSON_UNESCAPED_UNICODE)) < 5000, 'compact_weekly_prompt_fixture_under_5000_bytes');
ai13Check(str_contains($messages[1]['content'], '18:00') && str_contains($messages[1]['content'], 'Porcentagem') && str_contains($messages[1]['content'], '2026-11-16'), 'availability_catalog_and_window_preserved');
$messages = estudaiDiagnosticoPrompt($fixture['profile']);
ai13Check(str_contains($messages[1]['content'], 'somente nomes de materias_base') && str_contains($messages[1]['content'], 'todos os sete campos'), 'diagnostic_prompt_matches_required_subjects_and_fields');
foreach (['OPENROUTER_API_KEY=' => 'key_missing', 'ESTUDAI_AI_DISABLED=true' => 'disabled'] as $setting => $name) {
    putenv($setting);
    $calls = 0;
    $result = estudaiGerarPlanoSemanal($fixture['input'], static function () use (&$calls) { $calls++; throw new LogicException('Must not run'); });
    ai13Check(!$result['ok'] && $calls === 0, $name . '_no_transport');
    putenv('OPENROUTER_API_KEY=sk-or-stage13-1-sentinel');
}
echo json_encode(['result' => 'PASS', 'checks' => $ai13Checks, 'external_requests' => 0, 'fixtures' => 'synthetic_not_real_responses']);
