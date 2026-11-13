<?php

function estudaiRequireTestDatabase(PDO $db): void {
    if (!defined('DB_NAME') || DB_NAME !== 'estudai_teste_2026' || DB_USER !== 'estudai_teste_app' ||
        $db->query('SELECT DATABASE()')->fetchColumn() !== DB_NAME ||
        $db->query('SELECT CURRENT_USER()')->fetchColumn() !== 'estudai_teste_app@localhost') {
        throw new RuntimeException('Ambiente de testes de IA indisponivel.');
    }
}

function estudaiAiText($value, int $limit, bool $required = false): string {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new RuntimeException('Resposta da IA com tipo de texto invalido.');
    }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit || strip_tags($value) !== $value) {
        throw new RuntimeException('Resposta da IA com texto fora do contrato.');
    }
    return $value;
}

function estudaiAiList($value, int $count = 10, int $length = 260): array {
    if (!is_array($value) || !array_is_list($value) || count($value) > $count) {
        throw new RuntimeException('Resposta da IA com lista invalida.');
    }
    return array_map(static fn($item) => estudaiAiText($item, $length, true), $value);
}

function estudaiAiKey(string $value): string {
    return strtr(mb_strtolower(trim($value), 'UTF-8'), [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
        'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
    ]);
}

function estudaiAiMateria(string $value, array $allowed): string {
    foreach ($allowed as $subject) {
        if (is_string($subject) && estudaiAiKey($value) === estudaiAiKey($subject)) {
            return $subject;
        }
    }
    throw new RuntimeException('Resposta da IA com materia fora do perfil.');
}

function estudaiAiDiagnostico(array $data, array $perfil): array {
    $result = [];
    foreach (['perfil_resumido' => 700, 'estrategia_recomendada' => 900, 'rotina_sugerida' => 900, 'estrategia_revisao' => 900] as $key => $limit) {
        $result[$key] = estudaiAiText($data[$key] ?? null, $limit, true);
    }
    $result['principais_dificuldades'] = estudaiAiList($data['principais_dificuldades'] ?? null, 10, 80);
    $result['materias_prioritarias'] = estudaiAiList($data['materias_prioritarias'] ?? null, 10, 80);
    foreach ($result['materias_prioritarias'] as &$subject) {
        $subject = estudaiAiMateria($subject, $perfil['materias_base'] ?? []);
    }
    unset($subject);
    $result['proximos_passos'] = estudaiAiList($data['proximos_passos'] ?? null, 8, 160);
    return $result;
}

function estudaiAiPlanoSemanal(array $data, array $entrada): array {
    $tasks = $data['tarefas'] ?? null;
    if ($tasks === null && is_array($data['dias'] ?? null)) {
        $tasks = [];
        foreach ($data['dias'] as $day) {
            if (!is_array($day) || !is_array($day['tarefas'] ?? null)) {
                throw new RuntimeException('Resposta da IA com dia invalido.');
            }
            foreach ($day['tarefas'] as $task) {
                if (!is_array($task)) {
                    throw new RuntimeException('Resposta da IA com tarefa invalida.');
                }
                $tasks[] = $task + ['data' => $day['data'] ?? null];
            }
        }
    }
    if (!is_array($tasks) || !array_is_list($tasks) || !$tasks || count($tasks) > 30) {
        throw new RuntimeException('Resposta da IA sem conjunto valido de tarefas.');
    }
    $perfil = $entrada['perfil'] ?? [];
    $window = $entrada['janela'] ?? [];
    $catalog = $entrada['catalogo_conteudos'] ?? [];
    if (!$catalog) {
        throw new RuntimeException('Catalogo de conteudos necessario para validar a IA.');
    }
    $seen = [];
    foreach ($tasks as &$task) {
        if (!is_array($task)) {
            throw new RuntimeException('Resposta da IA com tarefa invalida.');
        }
        $date = $task['data'] ?? null;
        $start = $task['hora_inicio'] ?? null;
        $end = $task['hora_fim'] ?? null;
        $duration = $task['tempo_estimado'] ?? null;
        if (!is_string($date) || estudaiValidDateOrNull($date) !== $date ||
            !is_string($start) || estudaiValidHourOrNull($start) !== $start ||
            !is_string($end) || estudaiValidHourOrNull($end) !== $end ||
            !is_int($duration) || $duration < 10 || $duration > 240 || $start >= $end) {
            throw new RuntimeException('Resposta da IA com data, horario ou duracao invalidos.');
        }
        $minutes = (strtotime($end) - strtotime($start)) / 60;
        if ($minutes < 10 || $minutes > 240 || abs($minutes - $duration) > 10 ||
            $date < ($window['semana_inicio'] ?? '') || $date > ($window['semana_fim'] ?? '') ||
            $date < ($window['hoje'] ?? '') || (!empty($window['data_limite']) && $date > $window['data_limite'])) {
            throw new RuntimeException('Resposta da IA fora da janela de estudo.');
        }
        $subject = estudaiAiMateria(estudaiAiText($task['materia'] ?? null, 80, true), $entrada['materias_base'] ?? $perfil['materias_base'] ?? []);
        $content = estudaiAiText($task['conteudo'] ?? null, 180, true);
        $allowedContent = $catalog[$subject] ?? [];
        $matches = array_values(array_filter($allowedContent, static fn($item) => estudaiAiKey($content) === estudaiAiKey($item)));
        if (!$matches) {
            throw new RuntimeException('Resposta da IA com conteudo nao cadastrado.');
        }
        if (!in_array($task['tipo'] ?? null, ['teoria', 'questoes', 'revisao', 'simulado', 'resumo', 'misto'], true) ||
            !in_array($task['prioridade'] ?? null, ['baixa', 'media', 'alta'], true)) {
            throw new RuntimeException('Resposta da IA com tipo ou prioridade invalidos.');
        }
        $task['materia'] = $subject;
        $task['conteudo'] = $matches[0];
        foreach (['titulo' => 160, 'descricao' => 900, 'objetivo' => 300] as $key => $limit) {
            $task[$key] = estudaiAiText($task[$key] ?? '', $limit, $key === 'titulo');
        }
        $fits = false;
        foreach (estudaiBlocksForDate($window['dias_disponiveis'] ?? [], new DateTimeImmutable($date)) as $block) {
            if ($start >= $block['inicio'] && $end <= $block['fim']) {
                $fits = true;
            }
        }
        if (!$fits) {
            throw new RuntimeException('Resposta da IA fora da disponibilidade.');
        }
        foreach ($seen[$date] ?? [] as $previous) {
            if ($start < $previous[1] && $end > $previous[0]) {
                throw new RuntimeException('Resposta da IA com tarefas sobrepostas.');
            }
        }
        $seen[$date][] = [$start, $end];
    }
    unset($task);
    foreach ($window['dias_cobertura_obrigatoria'] ?? [] as $day) {
        if (!isset($seen[$day['data']])) {
            throw new RuntimeException('Resposta da IA sem cobertura de dia obrigatorio.');
        }
    }
    foreach (['semana_inicio', 'semana_fim'] as $key) {
        if (($data[$key] ?? null) !== ($window[$key] ?? null)) {
            throw new RuntimeException('Resposta da IA com semana divergente.');
        }
    }
    foreach (['titulo' => 160, 'resumo' => 900, 'estrategia_da_semana' => 900] as $key => $limit) {
        $data[$key] = estudaiAiText($data[$key] ?? null, $limit, true);
    }
    $data['observacoes'] = estudaiAiList($data['observacoes'] ?? [], 8, 180);
    $data['alertas'] = estudaiAiList($data['alertas'] ?? [], 8, 180);
    $data['tarefas'] = $tasks;
    unset($data['dias']);
    return estudaiNormalizePlanoSemanal($data, $entrada);
}

function estudaiAiRevisao(array $data, array $entrada): array {
    $summary = estudaiAiText($data['resumo_semana'] ?? null, 900, true);
    $message = estudaiAiText($data['mensagem_usuario'] ?? null, 900, true);
    $performance = $data['desempenho'] ?? null;
    $decision = $data['decisao'] ?? null;
    if (!is_array($performance) || !is_array($decision) ||
        !in_array($performance['classificacao'] ?? '', ['bom', 'medio', 'ruim'], true) ||
        !is_numeric($performance['percentual_conclusao'] ?? null) ||
        $performance['percentual_conclusao'] < 0 || $performance['percentual_conclusao'] > 100 ||
        !in_array($decision['acao'] ?? '', ['gerar_proxima_semana', 'manter', 'reduzir_carga', 'aumentar_revisao'], true)) {
        throw new RuntimeException('Revisao da IA fora do contrato.');
    }
    $weights = $data['ajuste_pesos'] ?? null;
    if (!is_array($weights)) {
        throw new RuntimeException('Pesos da revisao invalidos.');
    }
    $allowed = $entrada['perfil']['materias_base'] ?? $entrada['materias_base'] ?? [];
    $safeWeights = [];
    foreach ($weights as $subject => $weight) {
        if (!is_int($weight) || $weight < 1 || $weight > 3) {
            throw new RuntimeException('Peso da revisao invalido.');
        }
        $safeWeights[estudaiAiMateria((string)$subject, $allowed)] = $weight;
    }
    return [
        'resumo_semana' => $summary,
        'mensagem_usuario' => $message,
        'desempenho' => [
            'classificacao' => $performance['classificacao'],
            'percentual_conclusao' => (float)$performance['percentual_conclusao'],
            'pontos_fortes' => estudaiAiList($performance['pontos_fortes'] ?? null),
            'pontos_fracos' => estudaiAiList($performance['pontos_fracos'] ?? null),
        ],
        'decisao' => ['acao' => $decision['acao'], 'motivos' => estudaiAiList($decision['motivos'] ?? null)],
        'ajuste_pesos' => $safeWeights,
    ];
}

function estudaiAiPlanoAnual(array $data, array $entrada): array {
    $start = $data['data_inicio'] ?? null;
    $end = $data['data_fim'] ?? null;
    if (!is_string($start) || estudaiValidDateOrNull($start) !== $start ||
        !is_string($end) || estudaiValidDateOrNull($end) !== $end || $end < $start ||
        !is_array($data['meses'] ?? null) || !$data['meses'] || count($data['meses']) > 12) {
        throw new RuntimeException('Plano anual da IA fora do contrato.');
    }
    foreach (['titulo' => 160, 'resumo' => 1200, 'estrategia_geral' => 1200] as $key => $limit) {
        $data[$key] = estudaiAiText($data[$key] ?? null, $limit, true);
    }
    foreach ($data['meses'] as &$month) {
        if (!is_array($month) || !is_string($month['mes'] ?? null) ||
            !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month['mes']) ||
            !is_array($month['semanas'] ?? null) || !$month['semanas'] || count($month['semanas']) > 8) {
            throw new RuntimeException('Mes do plano anual invalido.');
        }
        $month['objetivo_do_mes'] = estudaiAiText($month['objetivo_do_mes'] ?? null, 300, true);
        $month['foco_principal'] = estudaiAiList($month['foco_principal'] ?? null, 8, 80);
        foreach ($month['semanas'] as &$week) {
            $from = $week['semana_inicio'] ?? null;
            $until = $week['semana_fim'] ?? null;
            if (!is_string($from) || estudaiValidDateOrNull($from) !== $from ||
                !is_string($until) || estudaiValidDateOrNull($until) !== $until ||
                $from < $start || $until > $end || $until < $from) {
                throw new RuntimeException('Semana do plano anual invalida.');
            }
            $week['objetivo_da_semana'] = estudaiAiText($week['objetivo_da_semana'] ?? null, 300, true);
            $weeklyInput = $entrada;
            $weeklyInput['janela'] = [
                'semana_inicio' => $from, 'semana_fim' => $until,
                'hoje' => $entrada['janela']['hoje'] ?? $start, 'data_limite' => $end,
                'dias_disponiveis' => $entrada['perfil']['disponibilidade'] ?? [],
            ];
            $weekly = estudaiAiPlanoSemanal([
                'titulo' => $data['titulo'], 'resumo' => $data['resumo'],
                'estrategia_da_semana' => $data['estrategia_geral'],
                'semana_inicio' => $from, 'semana_fim' => $until,
                'tarefas' => $week['tarefas'] ?? null,
            ], $weeklyInput);
            $week['tarefas'] = $weekly['tarefas'];
        }
        unset($week);
    }
    unset($month);
    $data['regras_de_adaptacao'] = estudaiAiList($data['regras_de_adaptacao'] ?? [], 8, 180);
    $data['observacoes'] = estudaiAiList($data['observacoes'] ?? [], 8, 180);
    return estudaiNormalizePlanoAnual($data, $entrada);
}

function estudaiAiRedacao(array $data): array {
    $result = ['resumo' => estudaiAiText($data['resumo'] ?? null, 900, true), 'estimativa_nao_oficial' => true];
    if (!is_array($data['competencias'] ?? null) || count($data['competencias']) !== 5) {
        throw new RuntimeException('Analise da IA sem as cinco competencias.');
    }
    $seen = [];
    foreach ($data['competencias'] as $item) {
        if (!is_array($item) || !in_array($item['competencia'] ?? '', ['C1', 'C2', 'C3', 'C4', 'C5'], true) ||
            isset($seen[$item['competencia']])) {
            throw new RuntimeException('Competencia invalida na analise da IA.');
        }
        $seen[$item['competencia']] = true;
        $result['competencias'][] = ['competencia' => $item['competencia'], 'comentario' => estudaiAiText($item['comentario'] ?? null, 500, true)];
    }
    foreach (['pontos_fortes', 'pontos_de_melhoria', 'sugestoes', 'proximos_passos'] as $key) {
        $result[$key] = estudaiAiList($data[$key] ?? null);
    }
    return $result;
}
