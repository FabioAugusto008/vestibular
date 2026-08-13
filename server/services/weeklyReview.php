<?php
// server/services/weeklyReview.php - Revisao semanal com IA do EstudAI

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/ai/estudaiService.php';

function estudaiWeeklyTempoExpr(PDO $db): string {
    return dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
        ? 'COALESCE(tempo_estimado, tempo_estimado_min)'
        : 'tempo_estimado';
}

function estudaiWeeklyJson($raw): array {
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function estudaiWeeklyWindow(?DateTimeImmutable $base = null): array {
    $base = $base ?: new DateTimeImmutable('today');
    $thisMonday = $base->modify('monday this week');
    $lastMonday = $thisMonday->modify('-7 days');
    $lastSunday = $thisMonday->modify('-1 day');
    $nextMonday = $thisMonday->modify('+7 days');
    $nextSunday = $thisMonday->modify('+13 days');
    return [
        'semana_inicio' => $lastMonday->format('Y-m-d'),
        'semana_fim' => $lastSunday->format('Y-m-d'),
        'proxima_inicio' => $nextMonday->format('Y-m-d'),
        'proxima_fim' => $nextSunday->format('Y-m-d'),
    ];
}

function estudaiWeeklyActivePlans(PDO $db, ?int $uid = null): array {
    if (!dbTableExists($db, 'planos_estudo')) {
        return [];
    }
    $where = $uid ? 'AND usuario_id = ?' : '';
    $tipoFilter = dbColumnExists($db, 'planos_estudo', 'tipo_plano') ? "AND tipo_plano = 'semanal'" : '';
    $stmt = $db->prepare("
        SELECT *
        FROM planos_estudo
        WHERE status = 'ativo' {$tipoFilter} {$where}
        ORDER BY id DESC
    ");
    $stmt->execute($uid ? [$uid] : []);
    return $stmt->fetchAll();
}

function estudaiWeeklyTaskStats(PDO $db, int $uid, string $inicio, string $fim): array {
    if (!dbTableExists($db, 'tarefas_estudo')) {
        return [
            'total' => 0,
            'concluidas' => 0,
            'atrasadas' => 0,
            'percentual_conclusao' => 0,
            'minutos_planejados' => 0,
            'minutos_concluidos' => 0,
            'materias_com_atraso' => [],
            'tipos_com_atraso' => [],
        ];
    }

    $tempoExpr = estudaiWeeklyTempoExpr($db);
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
            SUM(CASE WHEN status <> 'concluida' AND status <> 'cancelada' AND data_prevista < CURDATE() THEN 1 ELSE 0 END) AS atrasadas,
            SUM(CASE WHEN status <> 'cancelada' THEN {$tempoExpr} ELSE 0 END) AS minutos_planejados,
            SUM(CASE WHEN status = 'concluida' THEN {$tempoExpr} ELSE 0 END) AS minutos_concluidos
        FROM tarefas_estudo
        WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
    ");
    $stmt->execute([$uid, $inicio, $fim]);
    $row = $stmt->fetch() ?: [];

    $materias = $db->prepare("
        SELECT COALESCE(NULLIF(materia, ''), 'Geral') AS materia, COUNT(*) AS total
        FROM tarefas_estudo
        WHERE usuario_id = ?
          AND data_prevista BETWEEN ? AND ?
          AND status <> 'concluida'
          AND status <> 'cancelada'
          AND data_prevista < CURDATE()
        GROUP BY COALESCE(NULLIF(materia, ''), 'Geral')
        ORDER BY total DESC
        LIMIT 6
    ");
    $materias->execute([$uid, $inicio, $fim]);

    $tipos = $db->prepare("
        SELECT COALESCE(NULLIF(tipo, ''), 'custom') AS tipo, COUNT(*) AS total
        FROM tarefas_estudo
        WHERE usuario_id = ?
          AND data_prevista BETWEEN ? AND ?
          AND status <> 'concluida'
          AND status <> 'cancelada'
          AND data_prevista < CURDATE()
        GROUP BY COALESCE(NULLIF(tipo, ''), 'custom')
        ORDER BY total DESC
        LIMIT 6
    ");
    $tipos->execute([$uid, $inicio, $fim]);

    $total = (int)($row['total'] ?? 0);
    $concluidas = (int)($row['concluidas'] ?? 0);
    return [
        'total' => $total,
        'concluidas' => $concluidas,
        'atrasadas' => (int)($row['atrasadas'] ?? 0),
        'percentual_conclusao' => $total > 0 ? round(($concluidas / $total) * 100, 2) : 0,
        'minutos_planejados' => (int)($row['minutos_planejados'] ?? 0),
        'minutos_concluidos' => (int)($row['minutos_concluidos'] ?? 0),
        'materias_com_atraso' => array_column($materias->fetchAll(), 'materia'),
        'tipos_com_atraso' => array_column($tipos->fetchAll(), 'tipo'),
    ];
}

function estudaiWeeklyUpcomingTasks(PDO $db, int $uid, string $inicio, string $fim): array {
    if (!dbTableExists($db, 'tarefas_estudo')) {
        return [];
    }
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $horaInicioExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_inicio') ? 'hora_inicio' : "NULL AS hora_inicio";
    $horaFimExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_fim') ? 'hora_fim' : "NULL AS hora_fim";
    $stmt = $db->prepare("
        SELECT id, titulo, materia, {$conteudoExpr}, tipo, data_prevista, {$horaInicioExpr}, {$horaFimExpr},
               tempo_estimado, prioridade, status
        FROM tarefas_estudo
        WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
        ORDER BY data_prevista ASC, hora_inicio ASC, id ASC
        LIMIT 80
    ");
    $stmt->execute([$uid, $inicio, $fim]);
    return $stmt->fetchAll();
}

function estudaiWeeklyExerciseStats(PDO $db, int $uid, string $inicio, string $fim): array {
    if (!dbTableExists($db, 'respostas_exercicios_planejados')) {
        return ['respondidos' => 0, 'acertos' => 0, 'erros' => 0, 'taxa_acerto' => 0];
    }
    $stmt = $db->prepare("
        SELECT COUNT(*) AS respondidos,
               SUM(CASE WHEN acertou = 1 THEN 1 ELSE 0 END) AS acertos,
               SUM(CASE WHEN acertou = 0 THEN 1 ELSE 0 END) AS erros
        FROM respostas_exercicios_planejados
        WHERE usuario_id = ? AND DATE(respondido_em) BETWEEN ? AND ?
    ");
    $stmt->execute([$uid, $inicio, $fim]);
    $row = $stmt->fetch() ?: [];
    $respondidos = (int)($row['respondidos'] ?? 0);
    $acertos = (int)($row['acertos'] ?? 0);
    return [
        'respondidos' => $respondidos,
        'acertos' => $acertos,
        'erros' => (int)($row['erros'] ?? 0),
        'taxa_acerto' => $respondidos > 0 ? round(($acertos / $respondidos) * 100, 2) : 0,
    ];
}

function estudaiWeeklySimulationStats(PDO $db, int $uid, string $inicio, string $fim): array {
    if (!dbTableExists($db, 'simulados_planejados')) {
        return ['liberados' => 0, 'finalizados' => 0];
    }
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN status IN ('liberado','iniciado','finalizado') THEN 1 ELSE 0 END) AS liberados,
            SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
        FROM simulados_planejados
        WHERE usuario_id = ? AND data_liberacao BETWEEN ? AND ?
    ");
    $stmt->execute([$uid, $inicio, $fim]);
    $row = $stmt->fetch() ?: [];
    return [
        'liberados' => (int)($row['liberados'] ?? 0),
        'finalizados' => (int)($row['finalizados'] ?? 0),
    ];
}

function estudaiWeeklyBuildEntrada(PDO $db, int $uid, array $plano, array $window): array {
    $passada = estudaiWeeklyTaskStats($db, $uid, $window['semana_inicio'], $window['semana_fim']);
    $proximaTarefas = estudaiWeeklyUpcomingTasks($db, $uid, $window['proxima_inicio'], $window['proxima_fim']);
    $exercicios = estudaiWeeklyExerciseStats($db, $uid, $window['semana_inicio'], $window['semana_fim']);
    $simulados = estudaiWeeklySimulationStats($db, $uid, $window['semana_inicio'], $window['semana_fim']);

    return [
        'usuario_id' => $uid,
        'plano' => [
            'id' => (int)$plano['id'],
            'titulo' => $plano['titulo'] ?? 'Plano ativo',
            'data_inicio' => $plano['data_inicio'] ?? null,
            'data_fim' => $plano['data_fim'] ?? null,
        ],
        'semana_passada' => [
            'inicio' => $window['semana_inicio'],
            'fim' => $window['semana_fim'],
            'tarefas_total' => $passada['total'],
            'tarefas_concluidas' => $passada['concluidas'],
            'tarefas_atrasadas' => $passada['atrasadas'],
            'percentual_conclusao' => $passada['percentual_conclusao'],
            'minutos_planejados' => $passada['minutos_planejados'],
            'minutos_concluidos' => $passada['minutos_concluidos'],
            'materias_com_atraso' => $passada['materias_com_atraso'],
            'tipos_com_atraso' => $passada['tipos_com_atraso'],
            'exercicios' => $exercicios,
            'simulados' => $simulados,
        ],
        'proxima_semana' => [
            'inicio' => $window['proxima_inicio'],
            'fim' => $window['proxima_fim'],
            'tarefas' => $proximaTarefas,
            'total_tarefas' => count($proximaTarefas),
            'minutos_planejados' => array_sum(array_map(static fn($t) => (int)($t['tempo_estimado'] ?? 0), $proximaTarefas)),
        ],
    ];
}

function estudaiWeeklySaveHistory(PDO $db, int $uid, array $resultado): void {
    if (!dbTableExists($db, 'ia_historico')) {
        return;
    }
    $stmt = $db->prepare('
        INSERT INTO ia_historico
            (usuario_id, provider, modelo, tipo, entrada_resumo, resposta_json, status, erro, tokens_entrada, tokens_saida)
        VALUES
            (:usuario_id, :provider, :modelo, :tipo, :entrada_resumo, :resposta_json, :status, :erro, :tokens_entrada, :tokens_saida)
    ');
    $usage = $resultado['usage'] ?? [];
    $stmt->execute([
        ':usuario_id' => $uid,
        ':provider' => $resultado['provider'] ?? 'openrouter',
        ':modelo' => $resultado['modelo'] ?? null,
        ':tipo' => 'revisao_semanal_ia',
        ':entrada_resumo' => 'Analise semanal controlada do plano ativo.',
        ':resposta_json' => jsonEncodeSafe($resultado['analise'] ?? []),
        ':status' => ($resultado['origem'] ?? 'erro') === 'ia' ? 'sucesso' : 'erro',
        ':erro' => $resultado['erro_tecnico'] ?? null,
        ':tokens_entrada' => $usage['prompt_tokens'] ?? null,
        ':tokens_saida' => $usage['completion_tokens'] ?? null,
    ]);
}

function estudaiWeeklyApply(PDO $db, int $uid, array $plano, array $entrada, array $resultado, string $origem): array {
    $analise = $resultado['analise'] ?? [];
    if (!$analise) {
        return ['ok' => false, 'erro' => 'Não foi possível gerar a revisão semanal agora.'];
    }
    $ajusteTipo = $analise['decisao']['ajuste_tipo'] ?? 'sem_ajustes';
    if (!in_array($ajusteTipo, ['sem_ajustes', 'pequenos_ajustes', 'grandes_ajustes', 'recriacao'], true)) {
        $ajusteTipo = 'sem_ajustes';
    }

    $semana = $entrada['semana_passada'];
    $ins = $db->prepare('
        INSERT INTO revisoes_semanais_ia
            (usuario_id, plano_id, semana_inicio, semana_fim, origem, tarefas_total, tarefas_concluidas,
             tarefas_atrasadas, percentual_conclusao, minutos_planejados, minutos_concluidos,
             analise_json, ajuste_tipo, aplicado)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $aplicado = $ajusteTipo === 'sem_ajustes' ? 0 : 1;
    $ins->execute([
        $uid,
        (int)$plano['id'],
        $semana['inicio'],
        $semana['fim'],
        $origem,
        (int)$semana['tarefas_total'],
        (int)$semana['tarefas_concluidas'],
        (int)$semana['tarefas_atrasadas'],
        (float)$semana['percentual_conclusao'],
        (int)$semana['minutos_planejados'],
        (int)$semana['minutos_concluidos'],
        jsonEncodeSafe($analise),
        $ajusteTipo,
        $aplicado,
    ]);
    $revisaoId = (int)$db->lastInsertId();

    $tarefasAjustadas = 0;
    if ($ajusteTipo !== 'sem_ajustes' && dbTableExists($db, 'tarefas_estudo')) {
        $futureStart = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $futureEnd = $entrada['proxima_semana']['fim'];
        $metadata = jsonEncodeSafe([
            'revisao_semanal_id' => $revisaoId,
            'ajuste_tipo' => $ajusteTipo,
            'mensagem' => $analise['mensagem_usuario'] ?? '',
        ]);

        if (dbColumnExists($db, 'tarefas_estudo', 'metadata_json')) {
            $stmt = $db->prepare("
                UPDATE tarefas_estudo
                SET prioridade = CASE
                        WHEN tipo IN ('revisao','questoes') THEN 'alta'
                        WHEN ? IN ('grandes_ajustes','recriacao') AND prioridade = 'alta' THEN 'media'
                        ELSE prioridade
                    END,
                    metadata_json = ?,
                    atualizada_em = CURRENT_TIMESTAMP
                WHERE usuario_id = ?
                  AND plano_id = ?
                  AND data_prevista BETWEEN ? AND ?
                  AND status <> 'concluida'
                  AND status <> 'cancelada'
            ");
            $stmt->execute([$ajusteTipo, $metadata, $uid, (int)$plano['id'], $futureStart, $futureEnd]);
        } else {
            $stmt = $db->prepare("
                UPDATE tarefas_estudo
                SET prioridade = CASE WHEN tipo IN ('revisao','questoes') THEN 'alta' ELSE prioridade END,
                    atualizada_em = CURRENT_TIMESTAMP
                WHERE usuario_id = ?
                  AND plano_id = ?
                  AND data_prevista BETWEEN ? AND ?
                  AND status <> 'concluida'
                  AND status <> 'cancelada'
            ");
            $stmt->execute([$uid, (int)$plano['id'], $futureStart, $futureEnd]);
        }
        $tarefasAjustadas = $stmt->rowCount();
    }

    if (dbTableExists($db, 'plano_versoes')) {
        $versao = $db->prepare('SELECT COALESCE(MAX(versao_numero), 0) + 1 FROM plano_versoes WHERE plano_id = ? AND usuario_id = ?');
        $versao->execute([(int)$plano['id'], $uid]);
        $versaoNumero = (int)$versao->fetchColumn();
        $planoJson = estudaiWeeklyJson($plano['plano_json'] ?? '');
        $planoJson['ultima_revisao_semanal'] = [
            'revisao_id' => $revisaoId,
            'ajuste_tipo' => $ajusteTipo,
            'mensagem_usuario' => $analise['mensagem_usuario'] ?? '',
            'tarefas_ajustadas' => $tarefasAjustadas,
        ];
        $db->prepare('
            INSERT INTO plano_versoes (plano_id, usuario_id, versao_numero, motivo, tipo_ajuste, plano_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([
            (int)$plano['id'],
            $uid,
            $versaoNumero,
            'Revisao semanal IA 0.0.3-alpha',
            $ajusteTipo,
            jsonEncodeSafe($planoJson),
        ]);
    }

    estudaiWeeklySaveHistory($db, $uid, $resultado);

    return [
        'revisao_id' => $revisaoId,
        'origem' => $resultado['origem'] ?? 'ia',
        'ajuste_tipo' => $ajusteTipo,
        'aplicado' => (bool)$aplicado,
        'tarefas_ajustadas' => $tarefasAjustadas,
        'analise' => $analise,
    ];
}

function estudaiWeeklyPerfil(PDO $db, int $uid): ?array {
    if (!dbTableExists($db, 'estudo_perfis')) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM estudo_perfis WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || empty($row['onboarding_completo'])) {
        return null;
    }
    $json = estudaiWeeklyJson($row['perfil_json'] ?? '');
    $disponibilidade = estudaiWeeklyJson($row['disponibilidade_json'] ?? '');
    $reforcos = estudaiWeeklyJson($row['reforcos_json'] ?? '');
    $materias = estudaiWeeklyJson($row['materias_base_json'] ?? '');
    return array_merge($json, [
        'id' => (int)$row['id'],
        'objetivo' => $row['objetivo'] ?? ($json['objetivo'] ?? ''),
        'modo_estudo' => $row['modo_estudo'] ?? ($json['modo_estudo'] ?? $row['objetivo'] ?? ''),
        'data_prova' => $row['data_prova'] ?: null,
        'disponibilidade' => $disponibilidade ?: ($json['disponibilidade'] ?? $json['horarios'] ?? []),
        'horarios' => $disponibilidade ?: ($json['disponibilidade'] ?? $json['horarios'] ?? []),
        'reforcos' => $reforcos ?: ($json['reforcos'] ?? []),
        'materias_base' => $materias ?: ($json['materias_base'] ?? []),
    ]);
}

function estudaiWeeklyDayKey(DateTimeInterface $date): string {
    $map = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
    return $map[(int)$date->format('w')] ?? 'segunda';
}

function estudaiWeeklyNextPlanWindow(array $perfil, array $window): ?array {
    $start = new DateTimeImmutable($window['proxima_inicio']);
    $end = new DateTimeImmutable($window['proxima_fim']);
    if (!empty($perfil['data_prova'])) {
        $limit = DateTimeImmutable::createFromFormat('Y-m-d', (string)$perfil['data_prova']);
        if ($limit && $limit < $start) {
            return null;
        }
        if ($limit && $limit < $end) {
            $end = $limit;
        }
    }
    $blocks = $perfil['disponibilidade'] ?? $perfil['horarios'] ?? [];
    $cursor = $start;
    $realStart = null;
    while ($cursor <= $end) {
        if (!empty($blocks[estudaiWeeklyDayKey($cursor)]) || !empty($blocks[estudaiDateCode($cursor)])) {
            $realStart = $cursor;
            break;
        }
        $cursor = $cursor->modify('+1 day');
    }
    if (!$realStart) {
        return null;
    }
    return [
        'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'semana_inicio' => $realStart->format('Y-m-d'),
        'semana_fim' => $end->format('Y-m-d'),
        'dias_disponiveis' => $blocks,
        'data_limite' => $perfil['data_prova'] ?? null,
        'timezone' => 'America/Sao_Paulo',
    ];
}

function estudaiWeeklyInsertOptional(PDO $db, string $table, array $values): int {
    $columns = [];
    $params = [];
    foreach ($values as $column => $value) {
        if (dbColumnExists($db, $table, $column)) {
            $columns[] = "`{$column}`";
            $params[] = $value;
        }
    }
    $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$db->lastInsertId();
}

function estudaiWeeklyTaskFitsWindow(array $task, array $janela): bool {
    if (!is_array($task)) return false;
    $data = $task['data'] ?? '';
    $inicio = $task['hora_inicio'] ?? '';
    $fim = $task['hora_fim'] ?? '';
    if ($data < $janela['semana_inicio'] || $data > $janela['semana_fim'] || !$inicio || !$fim || $fim <= $inicio) {
        return false;
    }
    if (!empty($janela['data_limite']) && $data > $janela['data_limite']) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    if (!$date) {
        return false;
    }
    $day = estudaiWeeklyDayKey($date);
    foreach (($janela['dias_disponiveis'][$day] ?? $janela['dias_disponiveis'][estudaiDateCode($date)] ?? []) as $block) {
        if ($inicio >= ($block['inicio'] ?? '') && $fim <= ($block['fim'] ?? '')) {
            return !empty($task['materia']) && !empty($task['conteudo']);
        }
    }
    return false;
}

function estudaiWeeklySaveNextPlan(PDO $db, int $uid, array $perfil, array $janela, array $resultado): ?array {
    $plano = $resultado['plano'] ?? [];
    $tarefas = array_values(array_filter((array)($plano['tarefas'] ?? []), static fn($task) => estudaiWeeklyTaskFitsWindow($task, $janela)));
    if (!$tarefas) {
        return null;
    }

    $origem = ($resultado['origem'] ?? 'erro') === 'ia' ? 'ia' : 'manual';
    $db->prepare("UPDATE planos_estudo SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'")
        ->execute([$uid]);
    if (dbTableExists($db, 'planejamento_semanal_controle')) {
        $db->prepare("UPDATE planejamento_semanal_controle SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'")
            ->execute([$uid]);
    }

    $plano['semana_inicio'] = $janela['semana_inicio'];
    $plano['semana_fim'] = $janela['semana_fim'];
    $planoId = estudaiWeeklyInsertOptional($db, 'planos_estudo', [
        'usuario_id' => $uid,
        'perfil_id' => $perfil['id'] ?? null,
        'titulo' => $plano['titulo'] ?? 'Plano semanal personalizado',
        'origem' => $origem,
        'status' => 'ativo',
        'tipo_plano' => 'semanal',
        'escopo' => 'semanal',
        'resumo' => $plano['resumo'] ?? '',
        'data_inicio' => $janela['semana_inicio'],
        'semana_inicio' => $janela['semana_inicio'],
        'semana_fim' => $janela['semana_fim'],
        'valido_de' => $janela['semana_inicio'],
        'valido_ate' => $janela['semana_fim'],
        'data_fim' => $janela['semana_fim'],
        'plano_json' => jsonEncodeSafe($plano),
    ]);

    if (dbTableExists($db, 'planejamento_semanal_controle')) {
        estudaiWeeklyInsertOptional($db, 'planejamento_semanal_controle', [
            'usuario_id' => $uid,
            'plano_id' => $planoId,
            'semana_inicio' => $janela['semana_inicio'],
            'semana_fim' => $janela['semana_fim'],
            'status' => 'ativo',
            'origem' => 'revisao_domingo',
        ]);
    }

    $ordem = 0;
    foreach ($tarefas as $task) {
        $ordem++;
        $data = $task['data'];
        $itemId = estudaiWeeklyInsertOptional($db, 'plano_estudo_itens', [
            'plano_id' => $planoId,
            'usuario_id' => $uid,
            'dia_semana' => $task['dia_semana'] ?? '',
            'data_prevista' => $data,
            'hora_inicio' => $task['hora_inicio'] ?? null,
            'hora_fim' => $task['hora_fim'] ?? null,
            'materia' => $task['materia'] ?? '',
            'conteudo' => $task['conteudo'] ?? '',
            'tipo_atividade' => estudaiTipoAtividade($task['tipo'] ?? 'misto'),
            'titulo' => $task['titulo'] ?? 'Tarefa de estudo',
            'descricao' => $task['descricao'] ?? '',
            'tempo_estimado' => (int)($task['tempo_estimado'] ?? 45),
            'status' => 'pendente',
            'ordem' => $ordem,
        ]);
        $tarefaId = estudaiWeeklyInsertOptional($db, 'tarefas_estudo', [
            'usuario_id' => $uid,
            'plano_id' => $planoId,
            'item_id' => $itemId ?: null,
            'titulo' => $task['titulo'] ?? 'Tarefa de estudo',
            'descricao' => $task['descricao'] ?? '',
            'materia' => $task['materia'] ?? '',
            'conteudo' => $task['conteudo'] ?? '',
            'tipo' => estudaiTipoAtividade($task['tipo'] ?? 'misto'),
            'data_prevista' => $data,
            'hora_inicio' => $task['hora_inicio'] ?? null,
            'hora_fim' => $task['hora_fim'] ?? null,
            'tempo_estimado' => (int)($task['tempo_estimado'] ?? 45),
            'prioridade' => estudaiPrioridade($task['prioridade'] ?? 'media'),
            'status' => 'pendente',
            'origem' => $origem,
            'fonte_conteudo' => 'plano',
            'metadata_json' => jsonEncodeSafe(['semana_inicio' => $janela['semana_inicio'], 'semana_fim' => $janela['semana_fim'], 'conteudo' => $task['conteudo'] ?? '']),
        ]);
        if (dbTableExists($db, 'eventos_calendario_estudai')) {
            estudaiWeeklyInsertOptional($db, 'eventos_calendario_estudai', [
                'usuario_id' => $uid,
                'plano_id' => $planoId,
                'tarefa_id' => $tarefaId,
                'tipo' => ($task['tipo'] ?? '') === 'questoes' ? 'exercicio' : (in_array(($task['tipo'] ?? ''), ['revisao', 'simulado', 'resumo'], true) ? $task['tipo'] : 'tarefa'),
                'titulo' => $task['titulo'] ?? 'Tarefa de estudo',
                'descricao' => $task['descricao'] ?? '',
                'conteudo' => $task['conteudo'] ?? '',
                'data_evento' => $data,
                'hora_inicio' => $task['hora_inicio'] ?? null,
                'hora_fim' => $task['hora_fim'] ?? null,
                'semana_inicio' => $janela['semana_inicio'],
                'semana_fim' => $janela['semana_fim'],
                'status' => 'pendente',
                'metadata_json' => jsonEncodeSafe(['materia' => $task['materia'] ?? '', 'conteudo' => $task['conteudo'] ?? '', 'tipo_tarefa' => $task['tipo'] ?? 'misto']),
            ]);
        }
    }

    return ['plano_id' => $planoId, 'tarefas_criadas' => count($tarefas), 'origem' => $origem];
}

function estudaiWeeklyRunForUser(PDO $db, int $uid, string $origem = 'manual'): array {
    $plans = estudaiWeeklyActivePlans($db, $uid);
    if (!$plans) {
        return ['ok' => false, 'erro' => 'Nenhum plano ativo encontrado.'];
    }

    $window = estudaiWeeklyWindow();
    $plano = $plans[0];
    $entrada = estudaiWeeklyBuildEntrada($db, $uid, $plano, $window);
    $resultado = estudaiGerarRevisaoSemanal($entrada);
    if (empty($resultado['ok'])) {
        return ['ok' => false, 'erro' => $resultado['erro'] ?? 'Não foi possível gerar a revisão semanal agora.'];
    }
    $salvo = estudaiWeeklyApply($db, $uid, $plano, $entrada, $resultado, $origem);
    if (empty($salvo['ok']) && isset($salvo['erro'])) {
        return $salvo;
    }
    $perfil = estudaiWeeklyPerfil($db, $uid);
    $proxima = null;
    if ($perfil && dbTableExists($db, 'planejamento_semanal_controle')) {
        $janela = estudaiWeeklyNextPlanWindow($perfil, $window);
        if ($janela) {
            $entradaPlano = [
                'perfil' => $perfil,
                'janela' => $janela,
                'analise_semana' => $salvo['analise'] ?? [],
                'origem_revisao' => $origem,
            ];
            $novoPlano = estudaiGerarPlanoSemanal($entradaPlano);
            if (!empty($novoPlano['ok'])) {
                $proxima = estudaiWeeklySaveNextPlan($db, $uid, $perfil, $janela, $novoPlano);
            }
        }
    }
    return ['ok' => true, 'proxima_semana' => $proxima] + $salvo;
}

function estudaiWeeklyLatest(PDO $db, int $uid): ?array {
    if (!dbTableExists($db, 'revisoes_semanais_ia')) {
        return null;
    }
    $stmt = $db->prepare('
        SELECT *
        FROM revisoes_semanais_ia
        WHERE usuario_id = ?
        ORDER BY executada_em DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'plano_id' => $row['plano_id'] ? (int)$row['plano_id'] : null,
        'semana_inicio' => $row['semana_inicio'],
        'semana_fim' => $row['semana_fim'],
        'executada_em' => $row['executada_em'],
        'origem' => $row['origem'],
        'tarefas_total' => (int)$row['tarefas_total'],
        'tarefas_concluidas' => (int)$row['tarefas_concluidas'],
        'tarefas_atrasadas' => (int)$row['tarefas_atrasadas'],
        'percentual_conclusao' => (float)$row['percentual_conclusao'],
        'ajuste_tipo' => $row['ajuste_tipo'],
        'aplicado' => (bool)$row['aplicado'],
        'analise' => estudaiWeeklyJson($row['analise_json'] ?? ''),
    ];
}
