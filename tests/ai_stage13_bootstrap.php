<?php
// Load declarations from trusted API sources without executing their HTTP dispatchers.
require_once __DIR__ . '/../server/helpers/helpers.php';
require_once __DIR__ . '/../server/services/dailyQuestions.php';
require_once __DIR__ . '/../server/services/ai/estudaiService.php';
require_once __DIR__ . '/../server/services/weeklyReview.php';

function ai13LoadFunctions(string $path): void {
    $tokens = token_get_all(file_get_contents($path));
    for ($index = 0; $index < count($tokens); $index++) {
        if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) continue;
        $next = $index + 1;
        while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
        if (!is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) continue;
        $source = '';
        $depth = 0;
        $opened = false;
        for (; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            $source .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $opened = true;
            } elseif ($token === '}' && --$depth === 0 && $opened) {
                break;
            }
        }
        eval($source);
    }
}
foreach (['plano-estudos.php', 'diagnostico.php', 'redacao.php'] as $api) {
    ai13LoadFunctions(__DIR__ . '/../server/api/' . $api);
}

function ai13Check(bool $condition, string $name): void {
    if (!$condition) throw new RuntimeException('Failed: ' . $name);
    $GLOBALS['ai13Checks'][] = ['test' => $name, 'result' => 'PASS'];
}

function ai13Fixture(): array {
    $profile = [
        'id' => 2, 'materias_base' => ['Matematica'], 'objetivo' => 'enem',
        'disponibilidade' => ['segunda' => [['inicio' => '18:00', 'fim' => '19:00']]],
        'onboarding_completo' => true, 'dias' => ['segunda'], 'data_prova' => '2026-11-17',
    ];
    $window = [
        'hoje' => '2026-11-13', 'semana_inicio' => '2026-11-16', 'semana_fim' => '2026-11-16',
        'data_limite' => '2026-11-17', 'dias_disponiveis' => $profile['disponibilidade'],
        'dias_cobertura_obrigatoria' => [[
            'data' => '2026-11-16', 'dia_semana' => 'Segunda-feira', 'dia_key' => 'segunda',
            'blocos' => [['inicio' => '18:00', 'fim' => '19:00']],
        ]],
        'datas_disponiveis' => [[
            'data' => '2026-11-16', 'dia_semana' => 'Segunda-feira', 'dia_key' => 'segunda',
            'blocos' => [['inicio' => '18:00', 'fim' => '19:00']],
        ]],
    ];
    $plan = [
        'titulo' => 'Plano ficticio', 'resumo' => 'Estudo de matematica na disponibilidade informada.',
        'semana_inicio' => '2026-11-16', 'semana_fim' => '2026-11-16',
        'estrategia_da_semana' => 'Praticar porcentagem.',
        'tarefas' => [[
            'data' => '2026-11-16', 'hora_inicio' => '18:00', 'hora_fim' => '19:00',
            'materia' => 'Matematica', 'conteudo' => 'Porcentagem', 'tipo' => 'teoria',
            'tempo_estimado' => 60, 'prioridade' => 'media', 'titulo' => 'Estudar porcentagem',
            'descricao' => 'Revisar conceitos.', 'objetivo' => 'Compreender porcentagens.',
        ]],
        'observacoes' => [], 'alertas' => [],
    ];
    $diagnostic = [
        'perfil_resumido' => 'Estudante ficticio com uma hora disponivel na segunda-feira.',
        'principais_dificuldades' => ['Porcentagem'], 'materias_prioritarias' => ['Matematica'],
        'estrategia_recomendada' => 'Estudar conceitos e praticar com a base aprovada.',
        'rotina_sugerida' => 'Usar o bloco de segunda-feira.',
        'estrategia_revisao' => 'Rever os erros ao final da atividade.',
        'proximos_passos' => ['Revisar porcentagem'],
    ];
    $essay = [
        'resumo' => 'Texto ficticio com tese que pode ser aprofundada.',
        'competencias' => array_map(static fn($number) => ['competencia' => 'C' . $number, 'comentario' => 'Revisar a clareza do texto.'], range(1, 5)),
        'pontos_fortes' => ['Tema presente'], 'pontos_de_melhoria' => ['Aprofundar argumentos'],
        'sugestoes' => ['Revisar conectivos'], 'proximos_passos' => ['Reler o texto'],
    ];
    return [
        'profile' => $profile, 'window' => $window, 'plan' => $plan,
        'input' => ['perfil' => $profile, 'janela' => $window, 'materias_base' => $profile['materias_base'], 'catalogo_conteudos' => ['Matematica' => ['Porcentagem']]],
        'diagnostic' => $diagnostic, 'essay' => $essay,
    ];
}

function ai13Transport(array $content): callable {
    return static function () use ($content): array {
        $GLOBALS['ai13MockCalls'] = ($GLOBALS['ai13MockCalls'] ?? 0) + 1;
        return ['status' => 200, 'body' => json_encode([
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
            'model' => 'tests/model:free', 'usage' => ['cost' => 0],
        ])];
    };
}
