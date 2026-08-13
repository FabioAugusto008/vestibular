<?php
// api/plano-estudos.php - Plano de estudos funcional

require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../services/ai/estudaiService.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'gerar' : 'carregar_ativo');

function planoDecodeList($value): array {
    $decoded = json_decode((string)($value ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function planoPerfil(PDO $db, int $uid): ?array {
    $stmt = $db->prepare('SELECT * FROM estudo_perfis WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $perfilJson = [];
    if (!empty($row['perfil_json'])) {
        $decoded = json_decode((string)$row['perfil_json'], true);
        if (is_array($decoded)) {
            $perfilJson = $decoded;
        }
    }
    $dias = planoDecodeList($row['dias_semana_json'] ?? '');
    $disponibilidade = planoDecodeList($row['disponibilidade_json'] ?? '');
    $reforcos = planoDecodeList($row['reforcos_json'] ?? '');
    $materiasBase = planoDecodeList($row['materias_base_json'] ?? '');
    return array_merge($perfilJson, [
        'id' => (int)$row['id'],
        'onboarding_completo' => !empty($row['onboarding_completo']),
        'objetivo' => (string)$row['objetivo'],
        'modo_estudo' => $row['modo_estudo'] ?? ($perfilJson['modo_estudo'] ?? $row['objetivo']),
        'lingua_estrangeira' => $row['lingua_estrangeira'] ?? ($perfilJson['lingua_estrangeira'] ?? null),
        'data_prova' => $row['data_prova'] ?: null,
        'horas_dia' => isset($row['horas_dia']) ? (float)$row['horas_dia'] : null,
        'dias' => $dias ?: array_keys($disponibilidade),
        'dias_semana' => $dias ?: array_keys($disponibilidade),
        'disponibilidade' => $disponibilidade ?: ($perfilJson['disponibilidade'] ?? $perfilJson['horarios'] ?? []),
        'horarios' => $disponibilidade ?: ($perfilJson['disponibilidade'] ?? $perfilJson['horarios'] ?? []),
        'reforcos' => $reforcos ?: ($perfilJson['reforcos'] ?? []),
        'materias_base' => $materiasBase ?: ($perfilJson['materias_base'] ?? []),
        'dificuldades' => planoDecodeList($row['dificuldades_json'] ?? ''),
        'prioridades' => planoDecodeList($row['prioridades_json'] ?? ''),
        'preferencia' => $row['preferencia_estudo'] ?? 'misto',
        'preferencia_estudo' => $row['preferencia_estudo'] ?? 'misto',
        'meta_semanal' => $row['meta_semanal'] ?? '',
    ]);
}

function planoUltimoDiagnostico(PDO $db, int $uid): ?array {
    if (!dbTableExists($db, 'ia_historico')) {
        return null;
    }
    $stmt = $db->prepare("
        SELECT resposta_json
        FROM ia_historico
        WHERE usuario_id = ? AND tipo = 'diagnostico'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    $raw = $stmt->fetchColumn();
    if (!$raw) {
        return null;
    }
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? ($decoded['diagnostico'] ?? $decoded) : null;
}

function planoNormalizarData($value): ?string {
    $value = sanitizeTextValue($value, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    return $value;
}

function planoLoadTarefas(PDO $db, int $uid, ?int $planoId): array {
    if (!$planoId || !dbTableExists($db, 'tarefas_estudo')) {
        return [];
    }
    $tempoExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
        ? 'COALESCE(tempo_estimado, tempo_estimado_min)'
        : 'tempo_estimado';
    $conteudoExpr = dbColumnExists($db, 'tarefas_estudo', 'conteudo') ? 'conteudo' : "NULL AS conteudo";
    $horaInicioExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_inicio') ? 'hora_inicio' : "NULL AS hora_inicio";
    $horaFimExpr = dbColumnExists($db, 'tarefas_estudo', 'hora_fim') ? 'hora_fim' : "NULL AS hora_fim";
    $metadataExpr = dbColumnExists($db, 'tarefas_estudo', 'metadata_json') ? 'metadata_json' : "NULL AS metadata_json";
    $stmt = $db->prepare("
        SELECT id, usuario_id, plano_id, item_id, titulo, descricao, materia, {$conteudoExpr}, tipo, data_prevista,
               {$horaInicioExpr}, {$horaFimExpr},
               {$tempoExpr} AS tempo_estimado,
               prioridade, status, origem, {$metadataExpr}, concluida_em, criada_em, atualizada_em
        FROM tarefas_estudo
        WHERE usuario_id = ? AND plano_id = ?
        ORDER BY data_prevista ASC, hora_inicio ASC, id ASC
    ");
    $stmt->execute([$uid, $planoId]);
    return array_map(static function ($row) {
        $row['id'] = (int)$row['id'];
        $row['plano_id'] = isset($row['plano_id']) ? (int)$row['plano_id'] : null;
        $row['item_id'] = isset($row['item_id']) ? (int)$row['item_id'] : null;
        $row['tempo_estimado'] = isset($row['tempo_estimado']) ? (int)$row['tempo_estimado'] : null;
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string)$row['metadata_json'], true);
            $row['metadata'] = is_array($decoded) ? $decoded : null;
        }
        return $row;
    }, $stmt->fetchAll());
}

function planoFromRow(PDO $db, int $uid, ?array $row): ?array {
    if (!$row) {
        return null;
    }
    $json = json_decode((string)($row['plano_json'] ?? ''), true);
    if (!is_array($json)) {
        $json = [];
    }

    return array_merge($json, [
        'id' => (int)$row['id'],
        'titulo' => $row['titulo'] ?? ($json['titulo'] ?? 'Plano de estudos'),
        'origem' => $row['origem'] ?? 'manual',
        'status' => $row['status'] ?? 'ativo',
        'escopo' => $row['escopo'] ?? ($json['escopo'] ?? 'semanal'),
        'tipo_plano' => $row['tipo_plano'] ?? ($json['tipo_plano'] ?? ($row['escopo'] ?? 'semanal')),
        'resumo' => $row['resumo'] ?? ($json['resumo'] ?? ''),
        'data_inicio' => $row['data_inicio'] ?? ($json['data_inicio'] ?? null),
        'data_fim' => $row['data_fim'] ?? ($json['data_fim'] ?? null),
        'semana_inicio' => $row['semana_inicio'] ?? ($json['semana_inicio'] ?? null),
        'semana_fim' => $row['semana_fim'] ?? ($json['semana_fim'] ?? null),
        'criado_em' => $row['criado_em'] ?? null,
        'tarefas' => planoLoadTarefas($db, $uid, (int)$row['id']),
    ]);
}

function planoAtivo(PDO $db, int $uid): ?array {
    if (!dbTableExists($db, 'planos_estudo')) {
        return null;
    }
    $stmt = $db->prepare("
        SELECT *
        FROM planos_estudo
        WHERE usuario_id = ? AND status = 'ativo'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    return planoFromRow($db, $uid, $stmt->fetch() ?: null);
}

function planoReplanejamentoContexto(PDO $db, int $uid): array {
    $today = new DateTimeImmutable('today');
    $todayStr = $today->format('Y-m-d');
    $weekStart = $today->modify('monday this week')->format('Y-m-d');
    $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
    $context = [
        'tarefas_atrasadas' => [],
        'tarefas_semana' => ['total' => 0, 'concluidas' => 0, 'pendentes' => 0],
        'desempenho' => ['questoes_respondidas' => 0, 'acertos' => 0, 'erros' => 0, 'taxa_acerto' => 0],
        'simulados' => ['finalizados' => 0],
    ];

    if (dbTableExists($db, 'tarefas_estudo')) {
        $stmt = $db->prepare("
            SELECT id, titulo, materia, conteudo, tipo, data_prevista, hora_inicio, hora_fim, prioridade, status
            FROM tarefas_estudo
            WHERE usuario_id = ?
              AND data_prevista < ?
              AND status IN ('pendente','em_andamento','adiada','remarcada')
            ORDER BY data_prevista ASC, prioridade DESC
            LIMIT 12
        ");
        $stmt->execute([$uid, $todayStr]);
        $context['tarefas_atrasadas'] = $stmt->fetchAll();

        $week = $db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
                SUM(CASE WHEN status NOT IN ('concluida','cancelada') THEN 1 ELSE 0 END) AS pendentes
            FROM tarefas_estudo
            WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ? AND status <> 'cancelada'
        ");
        $week->execute([$uid, $weekStart, $weekEnd]);
        $row = $week->fetch() ?: [];
        $context['tarefas_semana'] = [
            'total' => (int)($row['total'] ?? 0),
            'concluidas' => (int)($row['concluidas'] ?? 0),
            'pendentes' => (int)($row['pendentes'] ?? 0),
        ];
    }

    if (dbTableExists($db, 'respostas_usuario')) {
        $scoreExpr = dbColumnExists($db, 'respostas_usuario', 'correta') ? 'correta' : 'acertou';
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total, SUM(COALESCE({$scoreExpr}, 0)) AS acertos
            FROM respostas_usuario
            WHERE usuario_id = ? AND data BETWEEN ? AND ?
        ");
        $stmt->execute([$uid, $weekStart, $weekEnd]);
        $row = $stmt->fetch() ?: [];
        $total = (int)($row['total'] ?? 0);
        $acertos = (int)($row['acertos'] ?? 0);
        $context['desempenho'] = [
            'questoes_respondidas' => $total,
            'acertos' => $acertos,
            'erros' => max(0, $total - $acertos),
            'taxa_acerto' => $total > 0 ? (int)round(($acertos / $total) * 100) : 0,
        ];
    }

    if (dbTableExists($db, 'simulados_planejados')) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM simulados_planejados WHERE usuario_id = ? AND status = 'finalizado'");
        $stmt->execute([$uid]);
        $context['simulados']['finalizados'] = (int)$stmt->fetchColumn();
    }

    return $context;
}

function planoRegistrarHistorico(PDO $db, int $uid, array $perfil, array $resultado): void {
    if (!dbTableExists($db, 'ia_historico')) {
        return;
    }

    $usage = $resultado['usage'] ?? [];
    $status = in_array(($resultado['origem'] ?? ''), ['ia', 'fallback'], true) ? 'sucesso' : 'erro';
    $params = [
        ':usuario_id' => $uid,
        ':provider' => $resultado['provider'] ?? 'openrouter',
        ':modelo' => $resultado['modelo'] ?? null,
        ':tipo' => 'plano_estudos',
        ':entrada_resumo' => sanitizeTextValue('Objetivo: ' . ($perfil['objetivo'] ?? '') . '; dias: ' . implode(', ', $perfil['dias'] ?? []), 900),
        ':resposta_json' => jsonEncodeSafe([
            'ok' => true,
            'origem' => $resultado['origem'] ?? 'erro',
            'fallback_usado' => !empty($resultado['fallback_usado']),
            'aviso_usuario' => $resultado['aviso_usuario'] ?? null,
            'erro_tecnico' => $resultado['erro_tecnico'] ?? null,
            'plano' => $resultado['plano'] ?? [],
        ]),
        ':status' => $status,
        ':erro' => $status === 'erro' ? sanitizeTextValue($resultado['erro_tecnico'] ?? ($resultado['erro'] ?? ''), 600) : null,
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

function planoEnsure0003(PDO $db): void {
    $required = [
        'plano_versoes',
        'eventos_calendario_estudai',
        'exercicios_planejados',
        'simulados_planejados',
    ];
    foreach ($required as $table) {
        if (!dbTableExists($db, $table)) {
            jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
        }
    }
    if (!dbColumnExists($db, 'tarefas_estudo', 'hora_inicio') || !dbColumnExists($db, 'planos_estudo', 'escopo')) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }
}

function planoFlattenAnnualTasks(array $plano): array {
    $tasks = [];
    foreach (($plano['meses'] ?? []) as $mes) {
        foreach (($mes['semanas'] ?? []) as $semana) {
            foreach (($semana['tarefas'] ?? []) as $tarefa) {
                if (!is_array($tarefa) || empty($tarefa['data']) || empty($tarefa['titulo'])) {
                    continue;
                }
                $tasks[] = $tarefa + [
                    'semana_inicio' => $semana['semana_inicio'] ?? null,
                    'semana_fim' => $semana['semana_fim'] ?? null,
                    'mes' => $mes['mes'] ?? null,
                ];
            }
        }
    }
    return $tasks;
}

function planoWeekStart(string $date): string {
    return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
}

function planoTipoEvento(string $tipo): string {
    if ($tipo === 'questoes') {
        return 'exercicio';
    }
    if (in_array($tipo, ['revisao', 'simulado', 'resumo'], true)) {
        return $tipo;
    }
    return 'tarefa';
}

function planoCriarEventosExerciciosSimulados(PDO $db, int $uid, int $planoId): void {
    $today = new DateTimeImmutable('today');
    $weekStart = $today->modify('monday this week')->format('Y-m-d');
    $nextWeekEnd = $today->modify('sunday next week')->format('Y-m-d');

    $stmt = $db->prepare("
        SELECT id, titulo, descricao, materia, conteudo, tipo, data_prevista, hora_inicio, hora_fim,
               tempo_estimado, prioridade, origem, metadata_json
        FROM tarefas_estudo
        WHERE usuario_id = ? AND plano_id = ?
        ORDER BY data_prevista ASC, hora_inicio ASC, id ASC
    ");
    $stmt->execute([$uid, $planoId]);
    $tarefas = $stmt->fetchAll();

    $insEvento = $db->prepare('
        INSERT INTO eventos_calendario_estudai
            (usuario_id, plano_id, tarefa_id, tipo, titulo, descricao, data_evento, hora_inicio, hora_fim, status, metadata_json)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($tarefas as $tarefa) {
        $tipo = (string)($tarefa['tipo'] ?? 'custom');
        $data = $tarefa['data_prevista'] ?: date('Y-m-d');
        $metadata = [
            'materia' => $tarefa['materia'] ?? '',
            'conteudo' => $tarefa['conteudo'] ?? '',
            'prioridade' => $tarefa['prioridade'] ?? 'media',
            'tempo_estimado' => (int)($tarefa['tempo_estimado'] ?? 0),
            'tipo_tarefa' => $tipo,
            'acao_contextual' => $tipo,
        ];

        $insEvento->execute([
            $uid,
            $planoId,
            (int)$tarefa['id'],
            planoTipoEvento($tipo),
            $tarefa['titulo'],
            $tarefa['descricao'],
            $data,
            $tarefa['hora_inicio'] ?: null,
            $tarefa['hora_fim'] ?: null,
            $tipo === 'simulado' && $data > date('Y-m-d') ? 'bloqueado' : 'pendente',
            jsonEncodeSafe($metadata),
        ]);

        // Exercicios e simulados nao sao pre-gerados: as telas buscam questoes aprovadas no banco.
    }
}

function planoSalvarAnual(PDO $db, int $uid, array $perfil, array $resultado): array {
    $plano = $resultado['plano'] ?? [];
    $origem = ($resultado['origem'] ?? 'erro') === 'ia' ? 'ia' : 'manual';
    $titulo = sanitizeTextValue($plano['titulo'] ?? 'Plano anual personalizado', 160);
    $resumo = sanitizeTextValue($plano['resumo'] ?? '', 1500);
    $dataInicio = planoNormalizarData($plano['data_inicio'] ?? null) ?: date('Y-m-d');
    $dataFim = planoNormalizarData($plano['data_fim'] ?? null) ?: date('Y-12-31');
    $tarefas = planoFlattenAnnualTasks($plano);
    if (!$tarefas) {
        jsonResponse(['erro' => 'Nao foi possivel montar tarefas para o plano anual.'], 500);
    }

    $db->beginTransaction();

    $db->prepare("UPDATE planos_estudo SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'")
        ->execute([$uid]);
    if (dbTableExists($db, 'exercicios_planejados')) {
        $db->prepare("UPDATE exercicios_planejados SET status = 'arquivado' WHERE usuario_id = ? AND status = 'ativo'")
            ->execute([$uid]);
    }
    if (dbTableExists($db, 'simulados_planejados')) {
        $db->prepare("UPDATE simulados_planejados SET status = 'arquivado' WHERE usuario_id = ? AND status <> 'finalizado'")
            ->execute([$uid]);
    }

    $insPlano = $db->prepare('
        INSERT INTO planos_estudo
            (usuario_id, perfil_id, titulo, origem, status, escopo, resumo, data_inicio, data_fim, plano_json)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $plano['escopo'] = 'anual';
    $insPlano->execute([
        $uid,
        $perfil['id'],
        $titulo,
        $origem,
        'ativo',
        'anual',
        $resumo,
        $dataInicio,
        $dataFim,
        jsonEncodeSafe($plano),
    ]);
    $planoId = (int)$db->lastInsertId();

    $db->prepare('
        INSERT INTO plano_versoes (plano_id, usuario_id, versao_numero, motivo, tipo_ajuste, plano_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([$planoId, $uid, 1, 'Criacao do plano anual 0.0.3-alpha', 'criacao', jsonEncodeSafe($plano)]);

    $insItem = $db->prepare('
        INSERT INTO plano_estudo_itens
            (plano_id, usuario_id, dia_semana, data_prevista, hora_inicio, hora_fim, materia, conteudo,
             tipo_atividade, titulo, descricao, tempo_estimado, status, ordem)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insTarefa = $db->prepare('
        INSERT INTO tarefas_estudo
            (usuario_id, plano_id, item_id, titulo, descricao, materia, conteudo, tipo, data_prevista,
             hora_inicio, hora_fim, tempo_estimado, prioridade, status, origem, metadata_json)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $ordem = 0;
    foreach ($tarefas as $tarefa) {
        $ordem++;
        $data = planoNormalizarData($tarefa['data'] ?? null) ?: $dataInicio;
        $tipo = estudaiTipoAtividade($tarefa['tipo'] ?? 'misto');
        $prioridade = estudaiPrioridade($tarefa['prioridade'] ?? 'media');
        $tituloTarefa = sanitizeTextValue($tarefa['titulo'] ?? 'Tarefa de estudo', 160);
        $descricao = sanitizeTextValue($tarefa['descricao'] ?? '', 1500);
        $materia = sanitizeTextValue($tarefa['materia'] ?? '', 80);
        $conteudo = sanitizeTextValue($tarefa['conteudo'] ?? '', 180);
        $horaInicio = sanitizeTextValue($tarefa['hora_inicio'] ?? '', 5) ?: null;
        $horaFim = sanitizeTextValue($tarefa['hora_fim'] ?? '', 5) ?: null;
        $tempo = max(10, min(240, (int)($tarefa['tempo_estimado'] ?? 40)));

        $insItem->execute([
            $planoId,
            $uid,
            estudaiDiaSemanaLabel(new DateTimeImmutable($data)),
            $data,
            $horaInicio,
            $horaFim,
            $materia,
            $conteudo,
            $tipo,
            $tituloTarefa,
            $descricao,
            $tempo,
            'pendente',
            $ordem,
        ]);
        $itemId = (int)$db->lastInsertId();
        $metadata = [
            'semana_inicio' => $tarefa['semana_inicio'] ?? null,
            'mes' => $tarefa['mes'] ?? null,
            'exercicios_planejados' => !empty($tarefa['exercicios_planejados']),
            'liberar_simulado' => !empty($tarefa['liberar_simulado']),
        ];

        $insTarefa->execute([
            $uid,
            $planoId,
            $itemId,
            $tituloTarefa,
            $descricao,
            $materia,
            $conteudo,
            $tipo,
            $data,
            $horaInicio,
            $horaFim,
            $tempo,
            $prioridade,
            'pendente',
            $origem,
            jsonEncodeSafe($metadata),
        ]);
    }

    planoCriarEventosExerciciosSimulados($db, $uid, $planoId);

    $db->commit();
    return planoAtivo($db, $uid);
}

function planoDiaKeyFromDate(DateTimeInterface $date): string {
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

function planoValidHour($value): ?string {
    $value = sanitizeTextValue($value ?? '', 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return null;
    }
    [$h, $m] = array_map('intval', explode(':', $value));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        return null;
    }
    return sprintf('%02d:%02d', $h, $m);
}

function planoDisponibilidadeValida(array $perfil): array {
    $raw = $perfil['disponibilidade'] ?? $perfil['horarios'] ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    $aliases = [
        'seg' => 'segunda', 'segunda' => 'segunda',
        'ter' => 'terca', 'terca' => 'terca',
        'qua' => 'quarta', 'quarta' => 'quarta',
        'qui' => 'quinta', 'quinta' => 'quinta',
        'sex' => 'sexta', 'sexta' => 'sexta',
        'sab' => 'sabado', 'sabado' => 'sabado',
        'dom' => 'domingo', 'domingo' => 'domingo',
    ];
    $result = [];
    foreach ($raw as $dia => $blocos) {
        $key = $aliases[strtolower(substr((string)$dia, 0, 7))] ?? $aliases[strtolower(substr((string)$dia, 0, 3))] ?? null;
        if (!$key || !is_array($blocos)) {
            continue;
        }
        foreach ($blocos as $bloco) {
            if (!is_array($bloco)) {
                continue;
            }
            $inicio = planoValidHour($bloco['inicio'] ?? $bloco['hora_inicio'] ?? null);
            $fim = planoValidHour($bloco['fim'] ?? $bloco['hora_fim'] ?? null);
            if ($inicio && $fim && strtotime($fim) > strtotime($inicio)) {
                $result[$key][] = ['inicio' => $inicio, 'fim' => $fim];
            }
        }
    }
    return $result;
}

function planoDatasDisponiveisEntre(DateTimeImmutable $inicio, DateTimeImmutable $fim, array $disponibilidade): array {
    $datas = [];
    for ($date = $inicio; $date <= $fim; $date = $date->modify('+1 day')) {
        $key = planoDiaKeyFromDate($date);
        if (empty($disponibilidade[$key])) {
            continue;
        }
        $datas[] = [
            'data' => $date->format('Y-m-d'),
            'dia_semana' => estudaiDiaSemanaLabel($date),
            'dia_key' => $key,
            'blocos' => $disponibilidade[$key],
        ];
    }
    return $datas;
}

function planoExtrairErroValidacao(array $erros, string $codigo): array {
    foreach ($erros as $erro) {
        if (($erro['codigo'] ?? '') === $codigo) {
            return $erro;
        }
    }
    return [];
}

function planoMensagemValidacaoSemanal(array $erros): string {
    $semCobertura = planoExtrairErroValidacao($erros, 'dias_sem_tarefas');
    if (!empty($semCobertura['datas']) && is_array($semCobertura['datas'])) {
        return 'A IA deixou dias disponiveis sem tarefas: ' . implode(', ', $semCobertura['datas']) . '.';
    }
    $semTarefas = planoExtrairErroValidacao($erros, 'sem_tarefas_validas');
    if ($semTarefas) {
        return 'A IA nao retornou tarefas validas dentro da disponibilidade.';
    }
    return 'A IA retornou um plano semanal incompleto ou fora das regras de disponibilidade.';
}

function normalizarJanelaPlanoSemanal(array $perfil): array {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $hoje = new DateTimeImmutable('today', $tz);
    $disponibilidade = planoDisponibilidadeValida($perfil);
    if (!$disponibilidade) {
        jsonResponse(['erro' => 'Edite o perfil e informe pelo menos um horario valido de estudo.'], 400);
    }

    $dataLimite = null;
    if (!empty($perfil['data_prova'])) {
        $candidate = DateTimeImmutable::createFromFormat('Y-m-d', (string)$perfil['data_prova'], $tz);
        if ($candidate) {
            if ($candidate < $hoje) {
                jsonResponse(['erro' => 'A data da prova ja passou. Atualize o perfil antes de gerar um plano.'], 400);
            }
            $dataLimite = $candidate;
        }
    }

    $domingoAtual = $hoje->modify('sunday this week');
    $fimAtual = ($dataLimite && $dataLimite < $domingoAtual) ? $dataLimite : $domingoAtual;
    $domingoSemHorario = (int)$hoje->format('w') === 0 && empty($disponibilidade['domingo']);
    $datasSemana = (!$domingoSemHorario && $fimAtual >= $hoje)
        ? planoDatasDisponiveisEntre($hoje, $fimAtual, $disponibilidade)
        : [];

    if (!$datasSemana) {
        if ($dataLimite && $dataLimite <= $domingoAtual) {
            jsonResponse(['erro' => 'Nao ha dias disponiveis ate a data da prova. Atualize a disponibilidade do perfil.'], 400);
        }
        $inicioProximaSemana = $hoje->modify('monday next week');
        $domingoProximaSemana = $hoje->modify('sunday next week');
        $fimProximaSemana = ($dataLimite && $dataLimite < $domingoProximaSemana) ? $dataLimite : $domingoProximaSemana;
        $datasSemana = $fimProximaSemana >= $inicioProximaSemana
            ? planoDatasDisponiveisEntre($inicioProximaSemana, $fimProximaSemana, $disponibilidade)
            : [];
    }

    if (!$datasSemana) {
        jsonResponse(['erro' => 'Nao ha dias disponiveis para montar a proxima semana. Edite o perfil.'], 400);
    }

    $inicio = new DateTimeImmutable($datasSemana[0]['data'], $tz);
    $fim = new DateTimeImmutable($datasSemana[count($datasSemana) - 1]['data'], $tz);

    return [
        'hoje' => $hoje->format('Y-m-d'),
        'semana_inicio' => $inicio->format('Y-m-d'),
        'semana_fim' => $fim->format('Y-m-d'),
        'dias_disponiveis' => $disponibilidade,
        'datas_disponiveis' => $datasSemana,
        'dias_cobertura_obrigatoria' => array_map(static function ($dia) {
            return [
                'data' => $dia['data'],
                'dia_semana' => $dia['dia_semana'],
                'dia_key' => $dia['dia_key'],
                'blocos' => $dia['blocos'],
            ];
        }, $datasSemana),
        'data_limite' => $dataLimite ? $dataLimite->format('Y-m-d') : null,
        'timezone' => 'America/Sao_Paulo',
    ];
}

function planoMateriaPermitida(string $materia, array $materiasPermitidas): bool {
    $materiaNorm = function_exists('mb_strtolower') ? mb_strtolower($materia, 'UTF-8') : strtolower($materia);
    foreach ($materiasPermitidas as $permitida) {
        $permitidaNorm = function_exists('mb_strtolower') ? mb_strtolower((string)$permitida, 'UTF-8') : strtolower((string)$permitida);
        if ($permitidaNorm === $materiaNorm) {
            return true;
        }
    }
    return false;
}

function planoTaskFitsBlock(array $task, array $janela): bool {
    $data = planoNormalizarData($task['data'] ?? null);
    if (!$data) {
        return false;
    }
    $date = new DateTimeImmutable($data);
    $day = planoDiaKeyFromDate($date);
    $inicio = planoValidHour($task['hora_inicio'] ?? null);
    $fim = planoValidHour($task['hora_fim'] ?? null);
    if (!$inicio || !$fim || strtotime($fim) <= strtotime($inicio)) {
        return false;
    }
    foreach (($janela['dias_disponiveis'][$day] ?? []) as $block) {
        if ($inicio >= $block['inicio'] && $fim <= $block['fim']) {
            return true;
        }
    }
    return false;
}

function planoTaskDurationMinutes(string $inicio, string $fim): int {
    return max(0, (int)round((strtotime($fim) - strtotime($inicio)) / 60));
}

function validarPlanoSemanalGerado(array $plano, array $perfil, array $janela, ?array &$erros = null): array {
    $erros = [];
    $materiasPermitidas = array_values(array_filter((array)($perfil['materias_base'] ?? [])));
    if (!$materiasPermitidas) {
        jsonResponse(['erro' => 'Perfil sem materias base. Atualize o onboarding.'], 400);
    }
    $allowedTypes = ['teoria', 'questoes', 'revisao', 'simulado', 'resumo', 'misto'];
    $validas = [];
    foreach (($plano['tarefas'] ?? []) as $task) {
        if (!is_array($task)) {
            continue;
        }
        $data = planoNormalizarData($task['data'] ?? null);
        if (!$data || $data < $janela['hoje'] || $data < $janela['semana_inicio'] || $data > $janela['semana_fim']) {
            continue;
        }
        if (!empty($janela['data_limite']) && $data > $janela['data_limite']) {
            continue;
        }
        $materia = sanitizeTextValue($task['materia'] ?? '', 80);
        $conteudo = sanitizeTextValue($task['conteudo'] ?? '', 180);
        $tipo = estudaiTipoAtividade($task['tipo'] ?? 'misto');
        if ($materia === '' || $conteudo === '' || !in_array($tipo, $allowedTypes, true) || !planoMateriaPermitida($materia, $materiasPermitidas)) {
            continue;
        }
        if (!planoTaskFitsBlock($task, $janela)) {
            continue;
        }
        $horaInicio = planoValidHour($task['hora_inicio']);
        $horaFim = planoValidHour($task['hora_fim']);
        $duracao = planoTaskDurationMinutes($horaInicio, $horaFim);
        $tempoInformado = (int)($task['tempo_estimado'] ?? 0);
        $tempo = $tempoInformado > 0 ? $tempoInformado : $duracao;
        if ($duracao > 0 && abs($tempo - $duracao) > 10) {
            $tempo = $duracao;
        }
        $validas[] = [
            'data' => $data,
            'dia_semana' => sanitizeTextValue($task['dia_semana'] ?? estudaiDiaSemanaLabel(new DateTimeImmutable($data)), 30),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'materia' => $materia,
            'conteudo' => $conteudo,
            'tipo' => $tipo,
            'titulo' => sanitizeTextValue($task['titulo'] ?? ('Estudar ' . $conteudo), 160),
            'descricao' => sanitizeTextValue($task['descricao'] ?? '', 1500),
            'tempo_estimado' => max(10, min(240, $tempo)),
            'prioridade' => estudaiPrioridade($task['prioridade'] ?? 'media'),
            'objetivo' => sanitizeTextValue($task['objetivo'] ?? '', 300),
        ];
    }
    if (!$validas) {
        $erros[] = ['codigo' => 'sem_tarefas_validas'];
        return [];
    }

    $obrigatorios = array_values(array_filter(array_map(static function ($dia) {
        return is_array($dia) ? ($dia['data'] ?? null) : null;
    }, (array)($janela['dias_cobertura_obrigatoria'] ?? $janela['datas_disponiveis'] ?? []))));
    $cobertos = array_values(array_unique(array_column($validas, 'data')));
    $semCobertura = array_values(array_diff($obrigatorios, $cobertos));
    if ($semCobertura) {
        $erros[] = ['codigo' => 'dias_sem_tarefas', 'datas' => $semCobertura];
        return [];
    }

    usort($validas, static fn($a, $b) => [$a['data'], $a['hora_inicio']] <=> [$b['data'], $b['hora_inicio']]);
    return [
        'titulo' => sanitizeTextValue($plano['titulo'] ?? 'Plano semanal personalizado', 160),
        'resumo' => sanitizeTextValue($plano['resumo'] ?? '', 1500),
        'semana_inicio' => $janela['semana_inicio'],
        'semana_fim' => $janela['semana_fim'],
        'estrategia_da_semana' => sanitizeTextValue($plano['estrategia_da_semana'] ?? '', 1500),
        'tarefas' => $validas,
        'observacoes' => is_array($plano['observacoes'] ?? null) ? array_slice($plano['observacoes'], 0, 8) : [],
        'alertas' => is_array($plano['alertas'] ?? null) ? array_slice($plano['alertas'], 0, 8) : [],
    ];
}

function planoMinutesFromHour(string $time): int {
    [$hour, $minute] = array_map('intval', explode(':', $time));
    return ($hour * 60) + $minute;
}

function planoHourFromMinutes(int $minutes): string {
    $minutes = max(0, min(1439, $minutes));
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function planoFallbackTaskTitle(string $tipo, string $conteudo): string {
    return match ($tipo) {
        'questoes' => 'Resolver questoes de ' . $conteudo,
        'revisao' => 'Revisar ' . $conteudo,
        'resumo' => 'Fazer resumo de ' . $conteudo,
        default => 'Estudar ' . $conteudo,
    };
}

function planoFallbackPriority(array $perfil, string $materia): string {
    $reforcos = is_array($perfil['reforcos'] ?? null) ? $perfil['reforcos'] : [];
    $peso = (int)($reforcos[$materia] ?? $reforcos[strtolower($materia)] ?? 1);
    return $peso >= 3 ? 'alta' : 'media';
}

function planoGerarBasicoSemanal(array $perfil, array $janela, string $motivo): array {
    $materias = estudaiWeightedMaterias((array)($perfil['materias_base'] ?? []), (array)($perfil['reforcos'] ?? []));
    $tipoCiclo = ['teoria', 'questoes', 'revisao', 'questoes'];
    $tarefas = [];
    $taskIndex = 0;

    foreach (($janela['dias_cobertura_obrigatoria'] ?? []) as $diaIndex => $dia) {
        if (!is_array($dia) || empty($dia['data']) || empty($dia['blocos']) || !is_array($dia['blocos'])) {
            continue;
        }

        $blocos = $dia['blocos'];
        usort($blocos, static fn($a, $b) => strcmp((string)($a['inicio'] ?? ''), (string)($b['inicio'] ?? '')));
        $tarefasDoDia = 0;

        foreach ($blocos as $blocoIndex => $bloco) {
            $inicio = planoValidHour($bloco['inicio'] ?? null);
            $fim = planoValidHour($bloco['fim'] ?? null);
            if (!$inicio || !$fim) {
                continue;
            }

            $inicioMin = planoMinutesFromHour($inicio);
            $fimMin = planoMinutesFromHour($fim);
            $duracaoBloco = $fimMin - $inicioMin;
            if ($duracaoBloco < 10) {
                continue;
            }

            $duracoes = [];
            if ($duracaoBloco >= 150) {
                $duracoes[] = min(70, max(50, (int)floor($duracaoBloco * 0.38)));
                $restante = $duracaoBloco - $duracoes[0] - 10;
                if ($restante >= 30) {
                    $duracoes[] = min(80, $restante);
                }
            } elseif ($duracaoBloco >= 90) {
                $duracoes[] = min(80, $duracaoBloco);
            } else {
                $duracoes[] = $duracaoBloco;
            }

            $cursor = $inicioMin;
            foreach ($duracoes as $slotIndex => $duracao) {
                if ($duracao < 10 || $cursor + $duracao > $fimMin) {
                    continue;
                }
                $materia = $materias[$taskIndex % count($materias)];
                $conteudo = estudaiConteudoBase($materia, $taskIndex);
                $tipo = $tipoCiclo[$taskIndex % count($tipoCiclo)];
                $horaInicio = planoHourFromMinutes($cursor);
                $horaFim = planoHourFromMinutes($cursor + $duracao);
                $titulo = planoFallbackTaskTitle($tipo, $conteudo);

                $tarefas[] = [
                    'data' => (string)$dia['data'],
                    'dia_semana' => sanitizeTextValue($dia['dia_semana'] ?? estudaiDiaSemanaLabel(new DateTimeImmutable((string)$dia['data'])), 30),
                    'hora_inicio' => $horaInicio,
                    'hora_fim' => $horaFim,
                    'materia' => $materia,
                    'conteudo' => $conteudo,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'descricao' => 'Plano basico criado automaticamente a partir da sua disponibilidade salva.',
                    'tempo_estimado' => $duracao,
                    'prioridade' => planoFallbackPriority($perfil, $materia),
                    'objetivo' => 'Manter constancia na rotina semanal.',
                ];
                $taskIndex++;
                $tarefasDoDia++;
                $cursor += $duracao + 10;
            }

            if ($tarefasDoDia > 0) {
                break;
            }
        }
    }

    return [
        'titulo' => 'Plano semanal basico',
        'resumo' => 'A IA nao conseguiu montar um plano valido. Criamos um plano basico usando sua disponibilidade e materias do perfil.',
        'semana_inicio' => $janela['semana_inicio'],
        'semana_fim' => $janela['semana_fim'],
        'estrategia_da_semana' => 'Cumprir pelo menos uma atividade em cada dia disponivel e manter a rotina ativa.',
        'tarefas' => $tarefas,
        'observacoes' => [
            'Plano gerado localmente como fallback.',
        ],
        'alertas' => [
            'A IA falhou ou retornou um plano invalido: ' . sanitizeTextValue($motivo, 220),
        ],
    ];
}

function planoResultadoFallbackSemanal(array $perfil, array $janela, string $motivo, array $base = []): array {
    $plano = planoGerarBasicoSemanal($perfil, $janela, $motivo);
    $erros = [];
    $validado = validarPlanoSemanalGerado($plano, $perfil, $janela, $erros);
    if (!$validado) {
        return [
            'ok' => false,
            'origem' => 'erro',
            'provider' => $base['provider'] ?? 'local',
            'modelo' => $base['modelo'] ?? 'fallback-local',
            'erro' => 'Nao conseguimos gerar um plano basico com os horarios do perfil. Revise sua disponibilidade.',
            'erro_tecnico' => planoMensagemValidacaoSemanal($erros),
        ];
    }

    return [
        'ok' => true,
        'origem' => 'fallback',
        'provider' => $base['provider'] ?? 'local',
        'modelo' => $base['modelo'] ?? 'fallback-local',
        'usage' => $base['usage'] ?? [],
        'fallback_usado' => true,
        'aviso_usuario' => 'A IA caiu ou retornou um plano invalido, mas geramos um plano basico com base na sua rotina.',
        'erro_tecnico' => $motivo,
        'plano' => $plano,
        'plano_validado' => $validado,
    ];
}

function planoGerarSemanalValidado(PDO $db, int $uid, array $perfil, array $entrada, array $janela): array {
    $resultado = estudaiGerarPlanoSemanal($entrada);
    if (empty($resultado['ok'])) {
        $motivo = $resultado['erro_tecnico'] ?? ($resultado['erro'] ?? 'Falha ao chamar a IA.');
        logTechnicalError('plano_semanal_ia_falha', new RuntimeException($motivo));
        return planoResultadoFallbackSemanal($perfil, $janela, $motivo, $resultado);
    }

    $erros = [];
    $validado = validarPlanoSemanalGerado($resultado['plano'] ?? [], $perfil, $janela, $erros);
    if ($validado) {
        $resultado['plano_validado'] = $validado;
        return $resultado;
    }

    $mensagem = planoMensagemValidacaoSemanal($erros);
    logTechnicalError('plano_semanal_validacao', new RuntimeException($mensagem));

    $diasSemTarefas = planoExtrairErroValidacao($erros, 'dias_sem_tarefas');
    $entradaRetry = $entrada;
    $entradaRetry['validacao_anterior'] = [
        'problema' => $mensagem,
        'dias_sem_tarefas' => $diasSemTarefas['datas'] ?? [],
        'dias_obrigatorios' => array_map(static fn($dia) => $dia['data'] ?? null, $janela['dias_cobertura_obrigatoria'] ?? []),
        'instrucao' => 'Refaca o JSON semanal cobrindo todos os dias obrigatorios, sem criar tarefas fora dos blocos de disponibilidade.',
    ];

    $retry = estudaiGerarPlanoSemanal($entradaRetry);
    if (empty($retry['ok'])) {
        $motivo = $retry['erro_tecnico'] ?? ($retry['erro'] ?? $mensagem);
        return planoResultadoFallbackSemanal($perfil, $janela, $motivo, [
            'provider' => $retry['provider'] ?? ($resultado['provider'] ?? 'openrouter'),
            'modelo' => $retry['modelo'] ?? ($resultado['modelo'] ?? null),
            'usage' => $retry['usage'] ?? ($resultado['usage'] ?? []),
        ]);
    }

    $retryErros = [];
    $retryValidado = validarPlanoSemanalGerado($retry['plano'] ?? [], $perfil, $janela, $retryErros);
    if ($retryValidado) {
        $retry['plano_validado'] = $retryValidado;
        $retry['tentativa_corrigida'] = true;
        return $retry;
    }

    $mensagemRetry = planoMensagemValidacaoSemanal($retryErros);
    logTechnicalError('plano_semanal_validacao_retry', new RuntimeException($mensagemRetry));
    return planoResultadoFallbackSemanal($perfil, $janela, $mensagemRetry, [
        'provider' => $retry['provider'] ?? ($resultado['provider'] ?? 'openrouter'),
        'modelo' => $retry['modelo'] ?? ($resultado['modelo'] ?? null),
        'usage' => $retry['usage'] ?? ($resultado['usage'] ?? []),
    ]);
}

function planoColumnAllowsValue(PDO $db, string $table, string $column, string $value): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $db->prepare("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    $type = strtolower((string)($stmt->fetchColumn() ?: ''));
    if (!str_starts_with($type, 'enum(')) {
        return true;
    }
    return str_contains($type, "'" . strtolower($value) . "'");
}

function planoSafeOrigem(PDO $db, string $table, string $preferred, string $fallback = 'manual'): string {
    if (planoColumnAllowsValue($db, $table, 'origem', $preferred)) {
        return $preferred;
    }
    if (planoColumnAllowsValue($db, $table, 'origem', $fallback)) {
        return $fallback;
    }
    return 'manual';
}

function planoInsertWithOptionalColumns(PDO $db, string $table, array $values): int {
    $columns = [];
    $params = [];
    foreach ($values as $column => $value) {
        if (dbColumnExists($db, $table, $column)) {
            $columns[] = "`{$column}`";
            $params[] = $value;
        }
    }
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$db->lastInsertId();
}

function planoSalvarSemanal(PDO $db, int $uid, array $perfil, array $resultado, array $janela, string $motivoVersao = 'Criacao do plano semanal 0.1.0-alpha'): array {
    $planoRaw = $resultado['plano'] ?? [];
    $validado = is_array($resultado['plano_validado'] ?? null)
        ? $resultado['plano_validado']
        : validarPlanoSemanalGerado($planoRaw, $perfil, $janela);
    $origemResultado = $resultado['origem'] ?? 'erro';
    $origemPlanoPreferida = $origemResultado === 'ia' ? 'ia' : ($origemResultado === 'fallback' ? 'fallback' : 'erro');
    $origemTarefaPreferida = $origemResultado === 'ia' ? 'ia' : 'manual';
    $origemControlePreferida = $origemResultado === 'ia' ? 'ia' : 'manual';
    $origemPlano = planoSafeOrigem($db, 'planos_estudo', $origemPlanoPreferida, 'manual');
    $origemTarefa = planoSafeOrigem($db, 'tarefas_estudo', $origemTarefaPreferida, 'manual');
    $origemControle = planoSafeOrigem($db, 'planejamento_semanal_controle', $origemControlePreferida, 'manual');
    if (!$validado) {
        jsonResponse(['erro' => 'Nao foi possivel gerar um plano semanal valido agora. Tente novamente em alguns instantes.'], 503);
    }

    $db->beginTransaction();
    $db->prepare("UPDATE planos_estudo SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'")
        ->execute([$uid]);
    if (dbTableExists($db, 'planejamento_semanal_controle')) {
        $db->prepare("UPDATE planejamento_semanal_controle SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'")
            ->execute([$uid]);
    }

    $planoId = planoInsertWithOptionalColumns($db, 'planos_estudo', [
        'usuario_id' => $uid,
        'perfil_id' => $perfil['id'] ?? null,
        'titulo' => $validado['titulo'],
        'origem' => $origemPlano,
        'status' => 'ativo',
        'tipo_plano' => 'semanal',
        'escopo' => 'semanal',
        'resumo' => $validado['resumo'],
        'data_inicio' => $validado['semana_inicio'],
        'semana_inicio' => $validado['semana_inicio'],
        'semana_fim' => $validado['semana_fim'],
        'valido_de' => $validado['semana_inicio'],
        'valido_ate' => $validado['semana_fim'],
        'data_fim' => $validado['semana_fim'],
        'plano_json' => jsonEncodeSafe($validado),
    ]);

    if (dbTableExists($db, 'planejamento_semanal_controle')) {
        planoInsertWithOptionalColumns($db, 'planejamento_semanal_controle', [
            'usuario_id' => $uid,
            'plano_id' => $planoId,
            'semana_inicio' => $validado['semana_inicio'],
            'semana_fim' => $validado['semana_fim'],
            'status' => 'ativo',
            'origem' => $origemControle,
        ]);
    }

    if (dbTableExists($db, 'plano_versoes')) {
        $db->prepare('
            INSERT INTO plano_versoes (plano_id, usuario_id, versao_numero, motivo, tipo_ajuste, plano_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([$planoId, $uid, 1, $motivoVersao, 'criacao', jsonEncodeSafe($validado)]);
    }

    $ordem = 0;
    foreach ($validado['tarefas'] as $task) {
        $ordem++;
        $itemId = planoInsertWithOptionalColumns($db, 'plano_estudo_itens', [
            'plano_id' => $planoId,
            'usuario_id' => $uid,
            'dia_semana' => $task['dia_semana'],
            'data_prevista' => $task['data'],
            'hora_inicio' => $task['hora_inicio'],
            'hora_fim' => $task['hora_fim'],
            'materia' => $task['materia'],
            'conteudo' => $task['conteudo'],
            'tipo_atividade' => $task['tipo'],
            'titulo' => $task['titulo'],
            'descricao' => $task['descricao'],
            'tempo_estimado' => $task['tempo_estimado'],
            'status' => 'pendente',
            'ordem' => $ordem,
        ]);

        $metadata = [
            'conteudo' => $task['conteudo'],
            'tipo_tarefa' => $task['tipo'],
            'objetivo' => $task['objetivo'],
            'semana_inicio' => $validado['semana_inicio'],
            'semana_fim' => $validado['semana_fim'],
            'origem_geracao' => $origemResultado,
        ];
        $tarefaId = planoInsertWithOptionalColumns($db, 'tarefas_estudo', [
            'usuario_id' => $uid,
            'plano_id' => $planoId,
            'item_id' => $itemId ?: null,
            'titulo' => $task['titulo'],
            'descricao' => $task['descricao'],
            'materia' => $task['materia'],
            'conteudo' => $task['conteudo'],
            'tipo' => $task['tipo'],
            'data_prevista' => $task['data'],
            'hora_inicio' => $task['hora_inicio'],
            'hora_fim' => $task['hora_fim'],
            'tempo_estimado' => $task['tempo_estimado'],
            'prioridade' => $task['prioridade'],
            'status' => 'pendente',
            'origem' => $origemTarefa,
            'fonte_conteudo' => 'plano',
            'metadata_json' => jsonEncodeSafe($metadata),
        ]);

        if (dbTableExists($db, 'eventos_calendario_estudai')) {
            planoInsertWithOptionalColumns($db, 'eventos_calendario_estudai', [
                'usuario_id' => $uid,
                'plano_id' => $planoId,
                'tarefa_id' => $tarefaId,
                'tipo' => planoTipoEvento($task['tipo']),
                'titulo' => $task['titulo'],
                'descricao' => $task['descricao'],
                'conteudo' => $task['conteudo'],
                'data_evento' => $task['data'],
                'hora_inicio' => $task['hora_inicio'],
                'hora_fim' => $task['hora_fim'],
                'semana_inicio' => $validado['semana_inicio'],
                'semana_fim' => $validado['semana_fim'],
                'status' => $task['tipo'] === 'simulado' && $task['data'] > date('Y-m-d') ? 'bloqueado' : 'pendente',
                'metadata_json' => jsonEncodeSafe($metadata + ['materia' => $task['materia'], 'prioridade' => $task['prioridade'], 'tempo_estimado' => $task['tempo_estimado']]),
            ]);
        }
    }

    $db->commit();
    return planoAtivo($db, $uid);
}

try {
    if (
        !dbTableExists($db, 'planos_estudo') ||
        !dbTableExists($db, 'tarefas_estudo') ||
        !dbColumnExists($db, 'planos_estudo', 'perfil_id') ||
        !dbColumnExists($db, 'tarefas_estudo', 'item_id')
    ) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'carregar_ativo':
            jsonResponse([
                'ok' => true,
                'plano' => planoAtivo($db, $uid),
                'tem_perfil' => planoPerfil($db, $uid) !== null,
            ]);
            break;

        case 'arquivar':
            requirePost();
            validateCsrfToken();
            $payload = requestPayload(8000);
            $planoId = (int)($payload['plano_id'] ?? 0);
            if ($planoId <= 0) {
                jsonResponse(['erro' => 'Plano nao informado.'], 400);
            }
            $stmt = $db->prepare("UPDATE planos_estudo SET status = 'arquivado' WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$planoId, $uid]);
            jsonResponse(['ok' => true, 'plano' => planoAtivo($db, $uid)]);
            break;

        case 'gerar_semana':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('plano_gerar_semana', 6, 3600);
            planoEnsure0003($db);
            if (!dbTableExists($db, 'planejamento_semanal_controle') || !dbColumnExists($db, 'planos_estudo', 'tipo_plano')) {
                jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
            }

            $perfil = planoPerfil($db, $uid);
            if (!$perfil || empty($perfil['onboarding_completo'])) {
                jsonResponse(['erro' => 'Complete o onboarding antes de gerar o plano semanal.'], 400);
            }

            $janela = normalizarJanelaPlanoSemanal($perfil);
            $entrada = [
                'perfil' => $perfil,
                'janela' => $janela,
                'materias_base' => $perfil['materias_base'] ?? [],
                'reforcos' => $perfil['reforcos'] ?? [],
                'preferencias' => [
                    'intensidade' => $perfil['intensidade'] ?? 'ia',
                    'exercicios_dia' => $perfil['exercicios_dia'] ?? 'ia',
                    'frequencia_simulados' => $perfil['frequencia_simulados'] ?? 'ia',
                ],
            ];
            $diagnostico = planoUltimoDiagnostico($db, $uid);
            if ($diagnostico) {
                $entrada['diagnostico'] = $diagnostico;
            }

            $resultado = planoGerarSemanalValidado($db, $uid, $perfil, $entrada, $janela);
            if (empty($resultado['ok'])) {
                try {
                    planoRegistrarHistorico($db, $uid, $perfil, $resultado + ['plano' => []]);
                } catch (Throwable $ignored) {
                }
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao foi possivel gerar o plano semanal agora.'], 503);
            }
            $planoSalvo = planoSalvarSemanal($db, $uid, $perfil, $resultado, $janela);

            try {
                planoRegistrarHistorico($db, $uid, $perfil, [
                    'origem' => $resultado['origem'] ?? 'ia',
                    'provider' => $resultado['provider'] ?? 'openrouter',
                    'modelo' => $resultado['modelo'] ?? null,
                    'usage' => $resultado['usage'] ?? [],
                    'plano' => $planoSalvo,
                    'erro_tecnico' => $resultado['erro_tecnico'] ?? null,
                    'fallback_usado' => !empty($resultado['fallback_usado']),
                    'aviso_usuario' => $resultado['aviso_usuario'] ?? null,
                ]);
            } catch (Throwable $ignored) {
            }

            jsonResponse([
                'ok' => true,
                'origem' => $planoSalvo['origem'] ?? ($resultado['origem'] ?? 'ia'),
                'plano' => $planoSalvo,
                'tarefas' => $planoSalvo['tarefas'] ?? [],
                'janela' => $janela,
                'fallback_usado' => !empty($resultado['fallback_usado']),
                'aviso_usuario' => $resultado['aviso_usuario'] ?? null,
            ]);
            break;

        case 'replanejar_semana':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('plano_replanejar_semana', 5, 3600);
            planoEnsure0003($db);

            $payload = requestPayload(12000);
            $motivosPermitidos = [
                'minha_rotina_mudou',
                'o_plano_ficou_pesado',
                'o_plano_ficou_leve',
                'estou_atrasado',
                'quero_focar_em_outra_materia',
                'outro_motivo',
            ];
            $motivoCodigo = sanitizeTextValue($payload['motivo'] ?? '', 80);
            if (!in_array($motivoCodigo, $motivosPermitidos, true)) {
                jsonResponse(['erro' => 'Informe o motivo do replanejamento.'], 400);
            }
            $motivoTexto = sanitizeTextValue($payload['detalhes'] ?? '', 600);

            $perfil = planoPerfil($db, $uid);
            if (!$perfil || empty($perfil['onboarding_completo'])) {
                jsonResponse(['erro' => 'Complete o onboarding antes de replanejar a semana.'], 400);
            }
            $planoAtual = planoAtivo($db, $uid);
            if (!$planoAtual) {
                jsonResponse(['erro' => 'Gere um plano semanal antes de replanejar.'], 400);
            }

            $janela = normalizarJanelaPlanoSemanal($perfil);
            $contexto = planoReplanejamentoContexto($db, $uid);
            $entrada = [
                'perfil' => $perfil,
                'janela' => $janela,
                'materias_base' => $perfil['materias_base'] ?? [],
                'reforcos' => $perfil['reforcos'] ?? [],
                'plano_atual' => $planoAtual,
                'contexto_execucao' => $contexto,
                'replanejamento' => [
                    'motivo' => $motivoCodigo,
                    'detalhes' => $motivoTexto,
                    'instrucoes' => [
                        'considere tarefas atrasadas e disponibilidade real',
                        'mantenha apenas materias base e datas dentro da janela',
                        'nao gere questoes, alternativas, gabaritos ou simulados por IA',
                    ],
                ],
                'preferencias' => [
                    'intensidade' => $perfil['intensidade'] ?? 'ia',
                    'exercicios_dia' => $perfil['exercicios_dia'] ?? 'ia',
                    'frequencia_simulados' => $perfil['frequencia_simulados'] ?? 'ia',
                ],
            ];
            $diagnostico = planoUltimoDiagnostico($db, $uid);
            if ($diagnostico) {
                $entrada['diagnostico'] = $diagnostico;
            }

            $resultado = planoGerarSemanalValidado($db, $uid, $perfil, $entrada, $janela);
            if (empty($resultado['ok'])) {
                try {
                    planoRegistrarHistorico($db, $uid, $perfil, $resultado + ['plano' => []]);
                } catch (Throwable $ignored) {
                }
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao conseguimos replanejar sua semana agora. Tente novamente em alguns minutos.'], 503);
            }
            $motivoVersao = 'Replanejamento Launch Core: ' . $motivoCodigo . ($motivoTexto ? ' - ' . $motivoTexto : '');
            $planoSalvo = planoSalvarSemanal($db, $uid, $perfil, $resultado, $janela, $motivoVersao);

            try {
                planoRegistrarHistorico($db, $uid, $perfil, [
                    'origem' => $resultado['origem'] ?? 'ia',
                    'provider' => $resultado['provider'] ?? 'openrouter',
                    'modelo' => $resultado['modelo'] ?? null,
                    'usage' => $resultado['usage'] ?? [],
                    'plano' => $planoSalvo,
                    'erro_tecnico' => $resultado['erro_tecnico'] ?? null,
                    'fallback_usado' => !empty($resultado['fallback_usado']),
                    'aviso_usuario' => $resultado['aviso_usuario'] ?? null,
                ]);
            } catch (Throwable $ignored) {
            }

            jsonResponse([
                'ok' => true,
                'origem' => $planoSalvo['origem'] ?? ($resultado['origem'] ?? 'ia'),
                'plano' => $planoSalvo,
                'tarefas' => $planoSalvo['tarefas'] ?? [],
                'janela' => $janela,
                'motivo' => $motivoCodigo,
                'fallback_usado' => !empty($resultado['fallback_usado']),
                'aviso_usuario' => $resultado['aviso_usuario'] ?? null,
            ]);
            break;

        case 'gerar_anual':
        case 'regenerar_manual_dev':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('plano_gerar_anual', 4, 3600);
            planoEnsure0003($db);

            $perfil = planoPerfil($db, $uid);
            if (!$perfil) {
                jsonResponse(['erro' => 'Complete o perfil de estudo antes de gerar o plano anual.'], 400);
            }

            $entrada = $perfil;
            $diagnostico = planoUltimoDiagnostico($db, $uid);
            if ($diagnostico) {
                $entrada['diagnostico'] = $diagnostico;
            }

            $resultado = estudaiGerarPlanoAnual($entrada);
            if (empty($resultado['ok'])) {
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao foi possivel gerar o plano anual agora.'], 503);
            }
            $planoSalvo = planoSalvarAnual($db, $uid, $perfil, $resultado);

            try {
                planoRegistrarHistorico($db, $uid, $perfil, [
                    'origem' => $resultado['origem'] ?? 'ia',
                    'provider' => $resultado['provider'] ?? 'openrouter',
                    'modelo' => $resultado['modelo'] ?? null,
                    'usage' => $resultado['usage'] ?? [],
                    'plano' => $resultado['plano'] ?? [],
                    'erro_tecnico' => $resultado['erro_tecnico'] ?? null,
                ]);
            } catch (Throwable $ignored) {
            }

            jsonResponse([
                'ok' => true,
                'origem' => $resultado['origem'] ?? 'ia',
                'plano' => $planoSalvo,
                'tarefas' => $planoSalvo['tarefas'] ?? [],
            ]);
            break;

        case 'gerar':
        case 'regenerar':
            requirePost();
            validateCsrfToken();
            rateLimitGuard('plano_gerar_legado', 6, 3600);

            $perfil = planoPerfil($db, $uid);
            if (!$perfil) {
                jsonResponse(['erro' => 'Complete o perfil de estudo antes de gerar o plano.'], 400);
            }

            $entrada = $perfil;
            $diagnostico = planoUltimoDiagnostico($db, $uid);
            if ($diagnostico) {
                $entrada['diagnostico'] = $diagnostico;
            }

            $resultado = estudaiGerarPlanoEstudos($entrada);
            if (empty($resultado['ok'])) {
                jsonResponse(['erro' => $resultado['erro'] ?? 'Nao foi possivel gerar o plano agora.'], 503);
            }
            $plano = $resultado['plano'] ?? [];
            if (empty($plano['dias'])) {
                jsonResponse(['erro' => 'Nao foi possivel montar um plano valido.'], 500);
            }

            $origem = ($resultado['origem'] ?? 'erro') === 'ia' ? 'ia' : 'manual';
            $titulo = sanitizeTextValue($plano['titulo'] ?? 'Plano semanal personalizado', 160);
            $resumo = sanitizeTextValue($plano['resumo'] ?? '', 1500);
            $dataInicio = planoNormalizarData($plano['data_inicio'] ?? null);
            $dataFim = planoNormalizarData($plano['data_fim'] ?? null);

            $db->beginTransaction();

            $arquivar = $db->prepare("UPDATE planos_estudo SET status = 'substituido' WHERE usuario_id = ? AND status = 'ativo'");
            $arquivar->execute([$uid]);

            $insPlano = $db->prepare('
                INSERT INTO planos_estudo
                    (usuario_id, perfil_id, titulo, origem, status, resumo, data_inicio, data_fim, plano_json)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insPlano->execute([
                $uid,
                $perfil['id'],
                $titulo,
                $origem,
                'ativo',
                $resumo,
                $dataInicio,
                $dataFim,
                jsonEncodeSafe($plano),
            ]);
            $planoId = (int)$db->lastInsertId();

            $insItem = $db->prepare('
                INSERT INTO plano_estudo_itens
                    (plano_id, usuario_id, dia_semana, data_prevista, materia, tipo_atividade, titulo, descricao, tempo_estimado, status, ordem)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insTarefa = $db->prepare('
                INSERT INTO tarefas_estudo
                    (usuario_id, plano_id, item_id, titulo, descricao, materia, tipo, data_prevista, tempo_estimado, prioridade, status, origem)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $ordem = 0;
            foreach ($plano['dias'] as $dia) {
                $dataPrevista = planoNormalizarData($dia['data'] ?? null);
                $diaSemana = sanitizeTextValue($dia['dia_semana'] ?? '', 30);

                foreach (($dia['tarefas'] ?? []) as $tarefa) {
                    if (!is_array($tarefa)) {
                        continue;
                    }
                    $ordem++;
                    $tipo = estudaiTipoAtividade($tarefa['tipo'] ?? 'custom');
                    $prioridade = estudaiPrioridade($tarefa['prioridade'] ?? 'media');
                    $tituloTarefa = sanitizeTextValue($tarefa['titulo'] ?? 'Tarefa de estudo', 160);
                    $descricao = sanitizeTextValue($tarefa['descricao'] ?? '', 1500);
                    $materia = sanitizeTextValue($tarefa['materia'] ?? '', 80);
                    $tempo = max(10, min(240, (int)($tarefa['tempo_estimado'] ?? 30)));

                    $insItem->execute([
                        $planoId,
                        $uid,
                        $diaSemana,
                        $dataPrevista,
                        $materia,
                        $tipo,
                        $tituloTarefa,
                        $descricao,
                        $tempo,
                        'pendente',
                        $ordem,
                    ]);
                    $itemId = (int)$db->lastInsertId();

                    $insTarefa->execute([
                        $uid,
                        $planoId,
                        $itemId,
                        $tituloTarefa,
                        $descricao,
                        $materia,
                        $tipo,
                        $dataPrevista,
                        $tempo,
                        $prioridade,
                        'pendente',
                        $origem,
                    ]);
                }
            }

            $db->commit();

            try {
                planoRegistrarHistorico($db, $uid, $perfil, $resultado);
            } catch (Throwable $ignored) {
            }

            $planoSalvo = planoAtivo($db, $uid);
            jsonResponse([
                'ok' => true,
                'origem' => $origem,
                'plano' => $planoSalvo,
                'tarefas' => $planoSalvo['tarefas'] ?? [],
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    logTechnicalError('plano_estudos_api_error', $e);
    jsonResponse(['erro' => 'Nao foi possivel processar o plano de estudos agora.'], 500);
}
