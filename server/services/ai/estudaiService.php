<?php

require_once __DIR__ . '/openrouterClient.php';
require_once __DIR__ . '/prompts.php';

function estudaiDecodeJsonText(string $text): ?array {
    $clean = trim($text);
    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);

    $data = json_decode($clean, true);
    if (!is_array($data)) {
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $data = json_decode(substr($clean, $start, $end - $start + 1), true);
        }
    }
    return is_array($data) ? $data : null;
}

function estudaiText($value, int $maxLength = 500): string {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function estudaiList($value, int $maxItems = 8, int $maxLength = 80): array {
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach ($value as $item) {
        $clean = estudaiText($item, $maxLength);
        if ($clean !== '') {
            $items[] = $clean;
        }
        if (count($items) >= $maxItems) {
            break;
        }
    }
    return $items;
}

function estudaiNormalizeDiagnostico(array $data, array $perfil): array {
    return [
        'perfil_resumido' => estudaiText($data['perfil_resumido'] ?? '', 700),
        'principais_dificuldades' => estudaiList($data['principais_dificuldades'] ?? [], 10, 80),
        'materias_prioritarias' => estudaiList($data['materias_prioritarias'] ?? [], 10, 80),
        'estrategia_recomendada' => estudaiText($data['estrategia_recomendada'] ?? '', 900),
        'rotina_sugerida' => estudaiText($data['rotina_sugerida'] ?? '', 900),
        'estrategia_revisao' => estudaiText($data['estrategia_revisao'] ?? '', 900),
        'proximos_passos' => estudaiList($data['proximos_passos'] ?? [], 8, 160),
    ];
}

function estudaiDiaCodigo($value): ?string {
    $clean = strtolower(trim((string)$value));
    $prefix = substr($clean, 0, 3);
    $map = [
        'seg' => 'seg',
        'ter' => 'ter',
        'qua' => 'qua',
        'qui' => 'qui',
        'sex' => 'sex',
        'sab' => 'sab',
        'dom' => 'dom',
    ];
    return $map[$prefix] ?? null;
}

function estudaiDiasPermitidos(array $dias): array {
    $codes = [];
    foreach ($dias as $dia) {
        $code = estudaiDiaCodigo($dia);
        if ($code && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
    return $codes;
}

function estudaiDiaSemanaLabel(DateTimeInterface $date): string {
    $labels = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
    ];
    return $labels[(int)$date->format('w')] ?? 'Dia de estudo';
}

function estudaiDateCode(DateTimeInterface $date): string {
    $codes = [
        0 => 'dom',
        1 => 'seg',
        2 => 'ter',
        3 => 'qua',
        4 => 'qui',
        5 => 'sex',
        6 => 'sab',
    ];
    return $codes[(int)$date->format('w')] ?? 'seg';
}

function estudaiTipoAtividade($value): string {
    $value = strtolower(trim((string)$value));
    $value = str_replace(['ç', 'õ', 'í', 'á', 'é', 'ê'], ['c', 'o', 'i', 'a', 'e', 'e'], $value);
    if (in_array($value, ['exercicio', 'exercicios', 'pratica', 'pratica_questoes'], true)) {
        return 'questoes';
    }
    $allowed = ['teoria', 'questoes', 'revisao', 'simulado', 'resumo', 'misto', 'custom'];
    return in_array($value, $allowed, true) ? $value : 'custom';
}

function estudaiPrioridade($value): string {
    $value = strtolower(trim((string)$value));
    return in_array($value, ['baixa', 'media', 'alta'], true) ? $value : 'media';
}

function estudaiValidDateOrNull($value): ?string {
    $value = estudaiText($value, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function estudaiValidHourOrNull($value): ?string {
    $value = estudaiText($value, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return null;
    }
    [$h, $m] = array_map('intval', explode(':', $value));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        return null;
    }
    return sprintf('%02d:%02d', $h, $m);
}

function estudaiMonthKey(DateTimeInterface $date): string {
    return $date->format('Y-m');
}

function estudaiWeekStart(DateTimeInterface $date): DateTimeImmutable {
    $immutable = $date instanceof DateTimeImmutable
        ? $date
        : DateTimeImmutable::createFromMutable($date);
    return $immutable->modify('monday this week');
}

function estudaiEnemMaterias(): array {
    return [
        'Linguagens',
        'Redacao',
        'Matematica',
        'Ciencias Humanas',
        'Ciencias da Natureza',
        'Portugues',
        'Literatura',
        'Historia',
        'Geografia',
        'Filosofia',
        'Sociologia',
        'Biologia',
        'Quimica',
        'Fisica',
    ];
}

function estudaiFullDayKey(DateTimeInterface $date): string {
    $map = [
        0 => 'domingo',
        1 => 'segunda',
        2 => 'terca',
        3 => 'quarta',
        4 => 'quinta',
        5 => 'sexta',
        6 => 'sabado',
    ];
    return $map[(int)$date->format('w')] ?? 'segunda';
}

function estudaiBlocksForDate(array $blocks, DateTimeInterface $date): array {
    $short = estudaiDateCode($date);
    $full = estudaiFullDayKey($date);
    return $blocks[$full] ?? $blocks[$short] ?? [];
}

function estudaiWeightedMaterias(array $materias, array $reforcos): array {
    $weighted = [];
    foreach ($materias as $materia) {
        $materia = estudaiText($materia, 80);
        if ($materia === '') {
            continue;
        }
        $peso = max(1, min(3, (int)($reforcos[$materia] ?? $reforcos[strtolower($materia)] ?? 1)));
        for ($i = 0; $i < $peso; $i++) {
            $weighted[] = $materia;
        }
    }
    return $weighted ?: ['Matematica', 'Portugues'];
}

function estudaiConteudoBase(string $materia, int $index): string {
    $map = [
        'Redacao' => ['Tese e repertorio', 'Projeto de texto', 'Coesao e proposta de intervencao'],
        'Linguagens' => ['Interpretacao de texto', 'Generos textuais', 'Figuras de linguagem'],
        'Matematica' => ['Funcoes', 'Porcentagem', 'Geometria plana', 'Estatistica'],
        'Ciencias Humanas' => ['Cidadania e Estado', 'Brasil Republica', 'Geopolitica'],
        'Ciencias da Natureza' => ['Ecologia', 'Estequiometria', 'Energia e movimento'],
        'Portugues' => ['Interpretacao textual', 'Sintaxe essencial', 'Variacao linguistica'],
        'Literatura' => ['Escolas literarias', 'Modernismo', 'Leitura de poemas'],
        'Historia' => ['Brasil Colonia', 'Era Vargas', 'Ditadura militar'],
        'Geografia' => ['Cartografia', 'Urbanizacao', 'Climatologia'],
        'Filosofia' => ['Etica', 'Conhecimento', 'Filosofia politica'],
        'Sociologia' => ['Cultura e sociedade', 'Trabalho', 'Movimentos sociais'],
        'Biologia' => ['Citologia', 'Genetica', 'Ecologia'],
        'Quimica' => ['Ligacoes quimicas', 'Estequiometria', 'Solucoes'],
        'Fisica' => ['Cinematica', 'Leis de Newton', 'Eletricidade'],
        'Ingles' => ['Leitura instrumental', 'Cognatos e inferencia', 'Tempos verbais'],
        'Espanhol' => ['Leitura instrumental', 'Falsos cognatos', 'Tempos verbais'],
    ];
    $items = $map[$materia] ?? ['Topicos essenciais', 'Conceitos fundamentais', 'Aplicacao em exercicios'];
    return $items[$index % count($items)];
}

function estudaiNormalizePlanoSemanal(array $data, array $entrada): array {
    $tasksRaw = [];
    if (is_array($data['tarefas'] ?? null)) {
        $tasksRaw = $data['tarefas'];
    } elseif (is_array($data['dias'] ?? null)) {
        foreach ($data['dias'] as $dia) {
            foreach (($dia['tarefas'] ?? []) as $tarefa) {
                if (is_array($tarefa)) {
                    $tasksRaw[] = $tarefa + [
                        'data' => $dia['data'] ?? null,
                        'dia_semana' => $dia['dia_semana'] ?? null,
                    ];
                }
            }
        }
    }

    $tarefas = [];
    foreach ($tasksRaw as $index => $tarefa) {
        if (!is_array($tarefa)) {
            continue;
        }
        $materia = estudaiText($tarefa['materia'] ?? '', 80);
        $conteudo = estudaiText($tarefa['conteudo'] ?? '', 180);
        $titulo = estudaiText($tarefa['titulo'] ?? '', 160);
        if ($materia === '' || $conteudo === '') {
            continue;
        }
        if ($titulo === '') {
            $titulo = 'Estudar ' . $conteudo;
        }
        $horaInicio = estudaiValidHourOrNull($tarefa['hora_inicio'] ?? null);
        $horaFim = estudaiValidHourOrNull($tarefa['hora_fim'] ?? null);
        $tempoEstimado = isset($tarefa['tempo_estimado']) ? (int)$tarefa['tempo_estimado'] : 0;
        if ($tempoEstimado <= 0 && $horaInicio && $horaFim) {
            $tempoEstimado = max(0, (int)round((strtotime($horaFim) - strtotime($horaInicio)) / 60));
        }
        $tarefas[] = [
            'data' => estudaiValidDateOrNull($tarefa['data'] ?? null),
            'dia_semana' => estudaiText($tarefa['dia_semana'] ?? '', 30),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'materia' => $materia,
            'conteudo' => $conteudo,
            'tipo' => estudaiTipoAtividade($tarefa['tipo'] ?? 'misto'),
            'titulo' => $titulo,
            'descricao' => estudaiText($tarefa['descricao'] ?? '', 900),
            'tempo_estimado' => max(10, min(240, $tempoEstimado > 0 ? $tempoEstimado : 30)),
            'prioridade' => estudaiPrioridade($tarefa['prioridade'] ?? 'media'),
            'objetivo' => estudaiText($tarefa['objetivo'] ?? '', 300),
        ];
        if (count($tarefas) >= 30) {
            break;
        }
    }

    if (!$tarefas) {
        return [
            'titulo' => estudaiText($data['titulo'] ?? 'Plano semanal personalizado', 160),
            'resumo' => estudaiText($data['resumo'] ?? '', 900),
            'semana_inicio' => estudaiValidDateOrNull($data['semana_inicio'] ?? $data['data_inicio'] ?? null),
            'semana_fim' => estudaiValidDateOrNull($data['semana_fim'] ?? $data['data_fim'] ?? null),
            'estrategia_da_semana' => estudaiText($data['estrategia_da_semana'] ?? '', 900),
            'tarefas' => [],
            'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 180),
            'alertas' => estudaiList($data['alertas'] ?? [], 8, 180),
        ];
    }

    return [
        'titulo' => estudaiText($data['titulo'] ?? 'Plano semanal personalizado', 160),
        'resumo' => estudaiText($data['resumo'] ?? '', 900),
        'semana_inicio' => estudaiValidDateOrNull($data['semana_inicio'] ?? $data['data_inicio'] ?? null),
        'semana_fim' => estudaiValidDateOrNull($data['semana_fim'] ?? $data['data_fim'] ?? null),
        'estrategia_da_semana' => estudaiText($data['estrategia_da_semana'] ?? '', 900),
        'tarefas' => $tarefas,
        'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 180),
        'alertas' => estudaiList($data['alertas'] ?? [], 8, 180),
    ];
}

function estudaiNormalizePlano(array $data, array $entrada): array {
    $dias = [];
    foreach (($data['dias'] ?? []) as $dia) {
        if (!is_array($dia)) {
            continue;
        }
        $tarefas = [];
        foreach (($dia['tarefas'] ?? []) as $tarefa) {
            if (!is_array($tarefa)) {
                continue;
            }
            $titulo = estudaiText($tarefa['titulo'] ?? '', 160);
            if ($titulo === '') {
                continue;
            }
            $tarefas[] = [
                'titulo' => $titulo,
                'materia' => estudaiText($tarefa['materia'] ?? '', 80),
                'tipo' => estudaiTipoAtividade($tarefa['tipo'] ?? 'custom'),
                'descricao' => estudaiText($tarefa['descricao'] ?? '', 700),
                'tempo_estimado' => max(10, min(240, (int)($tarefa['tempo_estimado'] ?? 30))),
                'prioridade' => estudaiPrioridade($tarefa['prioridade'] ?? 'media'),
            ];
            if (count($tarefas) >= 4) {
                break;
            }
        }

        if (!$tarefas) {
            continue;
        }

        $dataPrevista = estudaiText($dia['data'] ?? '', 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPrevista)) {
            $dataPrevista = null;
        }

        $dias[] = [
            'data' => $dataPrevista,
            'dia_semana' => estudaiText($dia['dia_semana'] ?? '', 30),
            'tarefas' => $tarefas,
        ];
        if (count($dias) >= 7) {
            break;
        }
    }

    if (!$dias) {
        return [
            'titulo' => estudaiText($data['titulo'] ?? 'Plano semanal personalizado', 160),
            'resumo' => estudaiText($data['resumo'] ?? '', 900),
            'data_inicio' => estudaiText($data['data_inicio'] ?? '', 10),
            'data_fim' => estudaiText($data['data_fim'] ?? '', 10),
            'dias' => [],
            'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 160),
        ];
    }

    return [
        'titulo' => estudaiText($data['titulo'] ?? 'Plano semanal personalizado', 160),
        'resumo' => estudaiText($data['resumo'] ?? '', 900),
        'data_inicio' => estudaiText($data['data_inicio'] ?? ($dias[0]['data'] ?? ''), 10),
        'data_fim' => estudaiText($data['data_fim'] ?? ($dias[count($dias) - 1]['data'] ?? ''), 10),
        'dias' => $dias,
        'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 160),
    ];
}

function estudaiNormalizePlanoAnual(array $data, array $entrada): array {
    $meses = [];

    foreach (($data['meses'] ?? []) as $mes) {
        if (!is_array($mes)) {
            continue;
        }

        $semanas = [];
        foreach (($mes['semanas'] ?? []) as $semana) {
            if (!is_array($semana)) {
                continue;
            }

            $tarefas = [];
            foreach (($semana['tarefas'] ?? []) as $tarefa) {
                if (!is_array($tarefa)) {
                    continue;
                }
                $titulo = estudaiText($tarefa['titulo'] ?? '', 160);
                if ($titulo === '') {
                    continue;
                }
                $tarefas[] = [
                    'data' => estudaiValidDateOrNull($tarefa['data'] ?? null),
                    'hora_inicio' => estudaiValidHourOrNull($tarefa['hora_inicio'] ?? null),
                    'hora_fim' => estudaiValidHourOrNull($tarefa['hora_fim'] ?? null),
                    'titulo' => $titulo,
                    'materia' => estudaiText($tarefa['materia'] ?? '', 80),
                    'conteudo' => estudaiText($tarefa['conteudo'] ?? '', 180),
                    'tipo' => estudaiTipoAtividade($tarefa['tipo'] ?? 'misto'),
                    'descricao' => estudaiText($tarefa['descricao'] ?? '', 700),
                    'tempo_estimado' => max(10, min(240, (int)($tarefa['tempo_estimado'] ?? 40))),
                    'prioridade' => estudaiPrioridade($tarefa['prioridade'] ?? 'media'),
                    'liberar_simulado' => !empty($tarefa['liberar_simulado']),
                    'exercicios_planejados' => !empty($tarefa['exercicios_planejados']) || (($tarefa['tipo'] ?? '') === 'questoes'),
                ];
                if (count($tarefas) >= 60) {
                    break;
                }
            }

            $semanas[] = [
                'semana_inicio' => estudaiValidDateOrNull($semana['semana_inicio'] ?? null),
                'semana_fim' => estudaiValidDateOrNull($semana['semana_fim'] ?? null),
                'objetivo_da_semana' => estudaiText($semana['objetivo_da_semana'] ?? '', 300),
                'tarefas' => $tarefas,
            ];
            if (count($semanas) >= 8) {
                break;
            }
        }

        $meses[] = [
            'mes' => estudaiText($mes['mes'] ?? '', 7),
            'objetivo_do_mes' => estudaiText($mes['objetivo_do_mes'] ?? '', 300),
            'foco_principal' => estudaiList($mes['foco_principal'] ?? [], 8, 80),
            'semanas' => $semanas,
        ];
        if (count($meses) >= 12) {
            break;
        }
    }

    if (!$meses) {
        return [
            'titulo' => estudaiText($data['titulo'] ?? 'Plano anual personalizado', 160),
            'resumo' => estudaiText($data['resumo'] ?? '', 1200),
            'data_inicio' => estudaiValidDateOrNull($data['data_inicio'] ?? null),
            'data_fim' => estudaiValidDateOrNull($data['data_fim'] ?? null),
            'estrategia_geral' => estudaiText($data['estrategia_geral'] ?? '', 1200),
            'meses' => [],
            'regras_de_adaptacao' => estudaiList($data['regras_de_adaptacao'] ?? [], 8, 180),
            'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 180),
        ];
    }

    return [
        'titulo' => estudaiText($data['titulo'] ?? 'Plano anual personalizado', 160),
        'resumo' => estudaiText($data['resumo'] ?? '', 1200),
        'data_inicio' => estudaiValidDateOrNull($data['data_inicio'] ?? null),
        'data_fim' => estudaiValidDateOrNull($data['data_fim'] ?? null),
        'estrategia_geral' => estudaiText($data['estrategia_geral'] ?? '', 1200),
        'meses' => $meses,
        'regras_de_adaptacao' => estudaiList($data['regras_de_adaptacao'] ?? [], 8, 180),
        'observacoes' => estudaiList($data['observacoes'] ?? [], 8, 180),
    ];
}

function estudaiScheduleBlocks(array $perfil): array {
    $perfilAvancado = is_array($perfil['perfil_avancado'] ?? null) ? $perfil['perfil_avancado'] : $perfil;
    $horarios = $perfilAvancado['horarios'] ?? $perfil['horarios'] ?? [];
    if (is_string($horarios)) {
        $decoded = json_decode($horarios, true);
        $horarios = is_array($decoded) ? $decoded : [];
    }
    if (is_array($horarios) && $horarios) {
        return $horarios;
    }

    $dias = estudaiDiasPermitidos($perfil['dias'] ?? $perfil['dias_semana'] ?? []);
    $blocks = [];
    foreach ($dias ?: ['seg', 'qua', 'sex'] as $dia) {
        $blocks[$dia] = [[
            'inicio' => '19:00',
            'fim' => '20:00',
            'foco' => 'medio',
        ]];
    }
    return $blocks;
}

function estudaiNormalizeExercicios(array $data, array $entrada): array {
    $items = [];
    foreach (($data['exercicios'] ?? []) as $index => $exercicio) {
        if (!is_array($exercicio)) {
            continue;
        }
        $tipo = in_array(($exercicio['tipo'] ?? ''), ['multipla_escolha', 'aberta'], true) ? $exercicio['tipo'] : 'multipla_escolha';
        $alternativas = is_array($exercicio['alternativas'] ?? null) ? $exercicio['alternativas'] : [];
        $items[] = [
            'id' => estudaiText($exercicio['id'] ?? ('ex' . ($index + 1)), 80),
            'tipo' => $tipo,
            'materia' => estudaiText($exercicio['materia'] ?? ($entrada['materia'] ?? ''), 80),
            'conteudo' => estudaiText($exercicio['conteudo'] ?? ($entrada['conteudo'] ?? ''), 180),
            'dificuldade' => in_array(($exercicio['dificuldade'] ?? ''), ['facil', 'medio', 'dificil'], true) ? $exercicio['dificuldade'] : 'medio',
            'pergunta' => estudaiText($exercicio['pergunta'] ?? '', 1200),
            'alternativas' => [
                'A' => estudaiText($alternativas['A'] ?? '', 400),
                'B' => estudaiText($alternativas['B'] ?? '', 400),
                'C' => estudaiText($alternativas['C'] ?? '', 400),
                'D' => estudaiText($alternativas['D'] ?? '', 400),
                'E' => estudaiText($alternativas['E'] ?? '', 400),
            ],
            'resposta_correta' => estudaiText($exercicio['resposta_correta'] ?? 'A', 20),
            'explicacao' => estudaiText($exercicio['explicacao'] ?? '', 900),
        ];
        if (count($items) >= 20) {
            break;
        }
    }
    return ['exercicios' => $items];
}

function estudaiNormalizeRevisaoConteudo(array $data, array $entrada): array {
    $questoes = [];
    foreach (($data['questoes_revisao'] ?? []) as $questao) {
        if (!is_array($questao)) {
            continue;
        }
        $pergunta = estudaiText($questao['pergunta'] ?? '', 800);
        if ($pergunta === '') {
            continue;
        }
        $questoes[] = [
            'pergunta' => $pergunta,
            'resposta' => estudaiText($questao['resposta'] ?? '', 900),
            'explicacao' => estudaiText($questao['explicacao'] ?? '', 900),
        ];
        if (count($questoes) >= 5) {
            break;
        }
    }

    return [
        'materia' => estudaiText($data['materia'] ?? ($entrada['materia'] ?? ''), 80),
        'conteudo' => estudaiText($data['conteudo'] ?? ($entrada['conteudo'] ?? ''), 180),
        'resumo_revisao' => estudaiText($data['resumo_revisao'] ?? '', 1400),
        'pontos_importantes' => estudaiList($data['pontos_importantes'] ?? [], 8, 160),
        'erros_comuns' => estudaiList($data['erros_comuns'] ?? [], 8, 160),
        'exemplo_resolvido' => estudaiText($data['exemplo_resolvido'] ?? '', 1400),
        'questoes_revisao' => $questoes,
    ];
}

function estudaiGerarRevisaoConteudo(array $entrada): array {
    return [
        'ok' => false,
        'origem' => 'banco',
        'erro' => 'Revisoes por conteudo nao sao fabricadas por IA neste prototipo. Use historico real e questoes aprovadas da base.',
    ];
}

function estudaiNormalizeSimuladoPlanejado(array $data, array $entrada): array {
    $questoes = [];
    foreach (($data['questoes'] ?? []) as $index => $questao) {
        if (!is_array($questao)) {
            continue;
        }
        $alternativas = is_array($questao['alternativas'] ?? null) ? $questao['alternativas'] : [];
        $questoes[] = [
            'id' => estudaiText($questao['id'] ?? ('q' . ($index + 1)), 80),
            'materia' => estudaiText($questao['materia'] ?? ($entrada['materia'] ?? ''), 80),
            'conteudo' => estudaiText($questao['conteudo'] ?? '', 180),
            'dificuldade' => in_array(($questao['dificuldade'] ?? ''), ['facil', 'medio', 'dificil'], true) ? $questao['dificuldade'] : 'medio',
            'pergunta' => estudaiText($questao['pergunta'] ?? '', 1200),
            'alternativas' => [
                'A' => estudaiText($alternativas['A'] ?? '', 400),
                'B' => estudaiText($alternativas['B'] ?? '', 400),
                'C' => estudaiText($alternativas['C'] ?? '', 400),
                'D' => estudaiText($alternativas['D'] ?? '', 400),
                'E' => estudaiText($alternativas['E'] ?? '', 400),
            ],
            'resposta_correta' => estudaiText($questao['resposta_correta'] ?? 'A', 20),
            'explicacao' => estudaiText($questao['explicacao'] ?? '', 900),
        ];
        if (count($questoes) >= 20) {
            break;
        }
    }
    return [
        'titulo' => estudaiText($data['titulo'] ?? '', 160),
        'descricao' => estudaiText($data['descricao'] ?? '', 900),
        'questoes' => $questoes,
    ];
}

function estudaiIaError(Throwable $e, string $tipo, array $config): array {
    $message = $e instanceof OpenRouterRateLimitException
        ? 'A IA atingiu o limite temporario do provedor. Tente novamente em alguns instantes.'
        : 'Nao foi possivel gerar a analise agora. Verifique a conexao com a IA ou tente novamente.';

    return [
        'ok' => false,
        'origem' => 'erro',
        'provider' => 'openrouter',
        'modelo' => $config['model'] ?? null,
        'tipo' => $tipo,
        'erro' => $message,
        'erro_tecnico' => $e->getMessage(),
    ];
}

function estudaiGerarDiagnostico(array $perfil): array {
    $config = require __DIR__ . '/../../config/openrouter.php';
    try {
        $response = openrouterChatCompletion(estudaiDiagnosticoPrompt($perfil), [
            'temperature' => 0.25,
            'max_tokens' => 900,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = estudaiDecodeJsonText(openrouterFirstText($response));
        if (!$parsed) {
            throw new RuntimeException('A IA retornou um diagnostico fora do formato esperado.');
        }

        $diagnostico = estudaiNormalizeDiagnostico($parsed, $perfil);
        if ($diagnostico['perfil_resumido'] === '' || $diagnostico['estrategia_recomendada'] === '') {
            throw new RuntimeException('A IA retornou um diagnostico incompleto.');
        }

        return [
            'ok' => true,
            'origem' => 'ia',
            'provider' => 'openrouter',
            'modelo' => $config['model'],
            'usage' => $response['usage'] ?? [],
            'diagnostico' => $diagnostico,
        ];
    } catch (Throwable $e) {
        return estudaiIaError($e, 'diagnostico', $config);
    }
}

function estudaiGerarPlanoEstudos(array $entrada): array {
    return estudaiGerarPlanoSemanal($entrada);
}

function estudaiGerarPlanoSemanal(array $entrada): array {
    $config = require __DIR__ . '/../../config/openrouter.php';
    try {
        $response = openrouterChatCompletion(estudaiPlanoSemanalPrompt($entrada), [
            'temperature' => 0.25,
            'max_tokens' => 2600,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = estudaiDecodeJsonText(openrouterFirstText($response));
        if (!$parsed) {
            throw new RuntimeException('A IA retornou um plano fora do formato esperado.');
        }

        $plano = estudaiNormalizePlanoSemanal($parsed, $entrada);
        if (empty($plano['tarefas'])) {
            throw new RuntimeException('A IA retornou um plano sem tarefas validas.');
        }

        return [
            'ok' => true,
            'origem' => 'ia',
            'provider' => 'openrouter',
            'modelo' => $config['model'],
            'usage' => $response['usage'] ?? [],
            'plano' => $plano,
        ];
    } catch (Throwable $e) {
        return estudaiIaError($e, 'plano_semanal', $config);
    }
}

function estudaiGerarPlanoAnual(array $entrada): array {
    $config = require __DIR__ . '/../../config/openrouter.php';
    try {
        $response = openrouterChatCompletion(estudaiPlanoAnualPrompt($entrada), [
            'temperature' => 0.22,
            'max_tokens' => 4500,
            'response_format' => ['type' => 'json_object'],
        ]);

        $parsed = estudaiDecodeJsonText(openrouterFirstText($response));
        if (!$parsed) {
            throw new RuntimeException('A IA retornou um plano anual fora do formato esperado.');
        }

        $plano = estudaiNormalizePlanoAnual($parsed, $entrada);
        if (empty($plano['meses'])) {
            throw new RuntimeException('A IA retornou um plano anual sem tarefas validas.');
        }

        return [
            'ok' => true,
            'origem' => 'ia',
            'provider' => 'openrouter',
            'modelo' => $config['model'],
            'usage' => $response['usage'] ?? [],
            'plano' => $plano,
        ];
    } catch (Throwable $e) {
        return estudaiIaError($e, 'plano_anual', $config);
    }
}

function estudaiGerarExercicios(array $entrada): array {
    return [
        'ok' => false,
        'origem' => 'banco',
        'erro' => 'Exercicios nao sao gerados por IA em tempo real. Use a base de questoes aprovada do banco.',
    ];
}

function estudaiGerarSimuladoPlanejado(array $entrada): array {
    return [
        'ok' => false,
        'origem' => 'banco',
        'erro' => 'Simulados nao sao criados por IA em tempo real. Use questoes aprovadas da base do banco.',
    ];
}

function estudaiGerarRevisaoSemanal(array $entrada): array {
    $config = require __DIR__ . '/../../config/openrouter.php';
    try {
        $response = openrouterChatCompletion(estudaiRevisaoSemanalPrompt($entrada), [
            'temperature' => 0.2,
            'max_tokens' => 1800,
            'response_format' => ['type' => 'json_object'],
        ]);
        $parsed = estudaiDecodeJsonText(openrouterFirstText($response));
        if (!$parsed) {
            throw new RuntimeException('A IA retornou revisao semanal fora do formato esperado.');
        }

        $acao = $parsed['decisao']['acao'] ?? 'manter';
        $ajuste = $parsed['decisao']['ajuste_tipo'] ?? null;
        if (!$ajuste) {
            $ajuste = match ($acao) {
                'manter' => 'sem_ajustes',
                'reduzir_carga' => 'grandes_ajustes',
                'aumentar_revisao' => 'pequenos_ajustes',
                'gerar_proxima_semana' => 'pequenos_ajustes',
                default => 'sem_ajustes',
            };
        }
        if (!in_array($ajuste, ['sem_ajustes', 'pequenos_ajustes', 'grandes_ajustes', 'recriacao'], true)) {
            $ajuste = 'sem_ajustes';
        }
        $parsed['decisao']['ajuste_tipo'] = $ajuste;
        $parsed['decisao']['acao'] = in_array($acao, ['gerar_proxima_semana', 'manter', 'reduzir_carga', 'aumentar_revisao'], true)
            ? $acao
            : 'manter';

        return [
            'ok' => true,
            'origem' => 'ia',
            'provider' => 'openrouter',
            'modelo' => $config['model'],
            'usage' => $response['usage'] ?? [],
            'analise' => $parsed,
        ];
    } catch (Throwable $e) {
        return estudaiIaError($e, 'revisao_semanal', $config);
    }
}
