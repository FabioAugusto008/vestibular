<?php
// api/estatisticas.php — Estatísticas avançadas

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

    // ── ESTATÍSTICAS GERAIS ────────────────────────────────
    case 'geral':
        // Total de questões e acertos
        $total = $db->prepare("
            SELECT 
                COUNT(*) as total_questoes,
                SUM(acertou) as total_acertos,
                SUM(1 - acertou) as total_erros
            FROM respostas_usuario
            WHERE usuario_id = ?
        ");
        $total->execute([$uid]);
        $geral = $total->fetch();

        // Por matéria
        $materias = $db->prepare("
            SELECT 
                q.materia,
                COUNT(*) as total,
                SUM(r.acertou) as acertos,
                SUM(1 - r.acertou) as erros
            FROM respostas_usuario r
            JOIN questoes q ON q.id = r.questao_id
            WHERE r.usuario_id = ?
            GROUP BY q.materia
        ");
        $materias->execute([$uid]);
        $por_materia = [];
        foreach ($materias->fetchAll() as $m) {
            $por_materia[$m['materia']] = [
                'total' => (int)$m['total'],
                'acertos' => (int)$m['acertos'],
                'erros' => (int)$m['erros'],
                'percentual' => $m['total'] > 0 ? round(($m['acertos'] / $m['total']) * 100, 1) : 0
            ];
        }

        // Por dificuldade
        $dificuldades = $db->prepare("
            SELECT 
                q.dificuldade,
                COUNT(*) as total,
                SUM(r.acertou) as acertos
            FROM respostas_usuario r
            JOIN questoes q ON q.id = r.questao_id
            WHERE r.usuario_id = ?
            GROUP BY q.dificuldade
        ");
        $dificuldades->execute([$uid]);
        $por_dificuldade = [];
        foreach ($dificuldades->fetchAll() as $d) {
            $por_dificuldade[$d['dificuldade']] = [
                'total' => (int)$d['total'],
                'acertos' => (int)$d['acertos'],
                'percentual' => $d['total'] > 0 ? round(($d['acertos'] / $d['total']) * 100, 1) : 0
            ];
        }

        // Dias estudados
        $dias = $db->prepare("
            SELECT COUNT(*) as total FROM desempenho_diario 
            WHERE usuario_id = ? AND finalizado = 1
        ");
        $dias->execute([$uid]);
        $dias_estudados = (int)$dias->fetch()['total'];

        // Streak atual
        $streak = 0;
        $dia = new DateTime();
        foreach (range(0, 100) as $offset) {
            $dataStr = $dia->format('Y-m-d');
            $chk = $db->prepare("SELECT 1 FROM desempenho_diario WHERE usuario_id = ? AND data = ? AND finalizado = 1");
            $chk->execute([$uid, $dataStr]);
            if ($chk->fetch()) {
                $streak++;
                $dia->modify('-1 day');
            } else {
                if ($offset === 0) {
                    $dia->modify('-1 day');
                    continue;
                }
                break;
            }
        }

        // Maior streak
        $maior_streak = 0;
        $streak_temp = 0;
        $hist = $db->prepare("
            SELECT data FROM desempenho_diario 
            WHERE usuario_id = ? AND finalizado = 1
            ORDER BY data ASC
        ");
        $hist->execute([$uid]);
        $datas = $hist->fetchAll(PDO::FETCH_COLUMN);
        $prev = null;
        foreach ($datas as $d) {
            $curr = new DateTime($d);
            if ($prev && $prev->diff($curr)->days == 1) {
                $streak_temp++;
            } else {
                $streak_temp = 1;
            }
            $maior_streak = max($maior_streak, $streak_temp);
            $prev = $curr;
        }

        $tarefas = ['concluidas' => 0, 'atrasadas' => 0, 'pendentes' => 0, 'tempo_planejado' => 0, 'tempo_realizado' => 0, 'progresso_semanal' => 0];
        if (dbTableExists($db, 'tarefas_estudo')) {
            $today = new DateTimeImmutable('today');
            $weekStart = $today->modify('monday this week')->format('Y-m-d');
            $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
            $tempoExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
                ? 'COALESCE(tempo_estimado, tempo_estimado_min)'
                : 'tempo_estimado';
            $realExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_real_min') ? 'tempo_real_min' : '0';
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
                    SUM(CASE WHEN status NOT IN ('concluida','cancelada') THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$tempoExpr} ELSE 0 END) AS planejado,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$realExpr} ELSE 0 END) AS realizado,
                    SUM(CASE WHEN status <> 'cancelada' THEN 1 ELSE 0 END) AS total
                FROM tarefas_estudo
                WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
            ");
            $stmt->execute([$uid, $weekStart, $weekEnd]);
            $row = $stmt->fetch() ?: [];
            $late = $db->prepare("
                SELECT COUNT(*)
                FROM tarefas_estudo
                WHERE usuario_id = ?
                  AND data_prevista < ?
                  AND status IN ('pendente','em_andamento','adiada','remarcada')
            ");
            $late->execute([$uid, $today->format('Y-m-d')]);
            $totalSemana = (int)($row['total'] ?? 0);
            $concluidasSemana = (int)($row['concluidas'] ?? 0);
            $tarefas = [
                'concluidas' => $concluidasSemana,
                'atrasadas' => (int)$late->fetchColumn(),
                'pendentes' => (int)($row['pendentes'] ?? 0),
                'tempo_planejado' => (int)($row['planejado'] ?? 0),
                'tempo_realizado' => (int)($row['realizado'] ?? 0),
                'progresso_semanal' => $totalSemana > 0 ? (int)round(($concluidasSemana / $totalSemana) * 100) : 0,
            ];
        }

        $simuladosFinalizados = 0;
        if (dbTableExists($db, 'simulado_tentativas')) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM simulado_tentativas WHERE usuario_id = ? AND status = 'finalizado'");
            $stmt->execute([$uid]);
            $simuladosFinalizados += (int)$stmt->fetchColumn();
        }
        if (dbTableExists($db, 'simulados_planejados')) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM simulados_planejados WHERE usuario_id = ? AND status = 'finalizado'");
            $stmt->execute([$uid]);
            $simuladosFinalizados += (int)$stmt->fetchColumn();
        }

        jsonResponse([
            'ok' => true,
            'geral' => [
                'total_questoes' => (int)$geral['total_questoes'],
                'total_acertos' => (int)$geral['total_acertos'],
                'total_erros' => (int)$geral['total_erros'],
                'percentual' => $geral['total_questoes'] > 0 
                    ? round(($geral['total_acertos'] / $geral['total_questoes']) * 100, 1) 
                    : 0
            ],
            'por_materia' => $por_materia,
            'por_dificuldade' => $por_dificuldade,
            'dias_estudados' => $dias_estudados,
            'streak_atual' => $streak,
            'maior_streak' => $maior_streak,
            'tarefas' => $tarefas,
            'simulados_finalizados' => $simuladosFinalizados
        ]);
        break;

    // ── EVOLUÇÃO DIÁRIA (GRÁFICO) ──────────────────────────
    case 'evolucao':
        $dias = min(90, max(7, (int)($_GET['dias'] ?? 30)));
        
        $stmt = $db->prepare("
            SELECT 
                data,
                acertos,
                erros,
                ROUND(acertos * 100.0 / (acertos + erros), 1) as percentual
            FROM desempenho_diario
            WHERE usuario_id = ? AND finalizado = 1
            ORDER BY data DESC
            LIMIT ?
        ");
        $stmt->execute([$uid, $dias]);
        $evolucao = array_reverse($stmt->fetchAll());

        jsonResponse([
            'ok' => true,
            'evolucao' => $evolucao
        ]);
        break;

    // ── EVOLUÇÃO POR MATÉRIA ───────────────────────────────
    case 'evolucao_materia':
        $dias = min(90, max(7, (int)($_GET['dias'] ?? 30)));
        $data_inicio = (new DateTime())->modify("-{$dias} days")->format('Y-m-d');

        $stmt = $db->prepare("
            SELECT 
                r.data,
                q.materia,
                COUNT(*) as total,
                SUM(r.acertou) as acertos
            FROM respostas_usuario r
            JOIN questoes q ON q.id = r.questao_id
            WHERE r.usuario_id = ? AND r.data >= ?
            GROUP BY r.data, q.materia
            ORDER BY r.data ASC
        ");
        $stmt->execute([$uid, $data_inicio]);
        
        $evolucao = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!isset($evolucao[$row['data']])) {
                $evolucao[$row['data']] = ['data' => $row['data'], 'matematica' => 0, 'portugues' => 0];
            }
            $percentual = $row['total'] > 0 ? round(($row['acertos'] / $row['total']) * 100, 1) : 0;
            $evolucao[$row['data']][$row['materia']] = $percentual;
        }

        jsonResponse([
            'ok' => true,
            'evolucao' => array_values($evolucao)
        ]);
        break;

    // ── TEMPO MÉDIO ────────────────────────────────────────
    case 'tempo':
        $stmt = $db->prepare("
            SELECT 
                AVG(tempo_seg) as tempo_medio,
                MIN(tempo_seg) as tempo_min,
                MAX(tempo_seg) as tempo_max
            FROM desempenho_diario
            WHERE usuario_id = ? AND finalizado = 1 AND tempo_seg > 0
        ");
        $stmt->execute([$uid]);
        $tempos = $stmt->fetch();

        jsonResponse([
            'ok' => true,
            'tempo_medio_seg' => round($tempos['tempo_medio'] ?? 0),
            'tempo_min_seg' => (int)($tempos['tempo_min'] ?? 0),
            'tempo_max_seg' => (int)($tempos['tempo_max'] ?? 0)
        ]);
        break;

    case 'estudai_geral':
        date_default_timezone_set('America/Sao_Paulo');
        $today = new DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week')->format('Y-m-d');
        $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
        $monthStart = $today->modify('first day of this month')->format('Y-m-d');
        $monthEnd = $today->modify('last day of this month')->format('Y-m-d');
        $yearStart = $today->format('Y') . '-01-01';
        $yearEnd = $today->format('Y') . '-12-31';

        $tarefas = [
            'total_semana' => 0,
            'concluidas_semana' => 0,
            'pendentes_semana' => 0,
            'atrasadas' => 0,
            'percentual_conclusao' => 0,
        ];
        $tempo = [
            'minutos_planejados_semana' => 0,
            'minutos_concluidos_semana' => 0,
            'minutos_planejados_mes' => 0,
            'minutos_concluidos_mes' => 0,
            'minutos_planejados_ano' => 0,
            'minutos_concluidos_ano' => 0,
        ];
        $progresso = [
            'semanal' => ['total' => 0, 'concluidas' => 0, 'percentual' => 0],
            'mensal' => ['total' => 0, 'concluidas' => 0, 'percentual' => 0],
            'anual' => ['total' => 0, 'concluidas' => 0, 'percentual' => 0],
        ];
        $materias = [
            'maior_atraso' => [],
            'melhor_desempenho' => [],
        ];

        if (dbTableExists($db, 'tarefas_estudo')) {
            $tempoExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_estimado_min')
                ? 'COALESCE(tempo_estimado, tempo_estimado_min)'
                : 'tempo_estimado';
            $tempoRealExpr = dbColumnExists($db, 'tarefas_estudo', 'tempo_real_min') ? 'tempo_real_min' : '0';

            $stmt = $db->prepare("
                SELECT
                    COUNT(*) AS total_semana,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas_semana,
                    SUM(CASE WHEN status <> 'concluida' AND status <> 'cancelada' THEN 1 ELSE 0 END) AS pendentes_semana,
                    SUM(CASE WHEN status = 'concluida' THEN {$tempoExpr} ELSE 0 END) AS minutos_concluidos_semana,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$tempoExpr} ELSE 0 END) AS minutos_planejados_semana,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$tempoRealExpr} ELSE 0 END) AS minutos_realizados_semana
                FROM tarefas_estudo
                WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
            ");
            $stmt->execute([$uid, $weekStart, $weekEnd]);
            $row = $stmt->fetch() ?: [];

            $atrasadasStmt = $db->prepare("
                SELECT COUNT(*)
                FROM tarefas_estudo
                WHERE usuario_id = ?
                  AND data_prevista < ?
                  AND status IN ('pendente','em_andamento','adiada','remarcada')
            ");
            $atrasadasStmt->execute([$uid, $today->format('Y-m-d')]);

            $totalSemana = (int)($row['total_semana'] ?? 0);
            $concluidasSemana = (int)($row['concluidas_semana'] ?? 0);
            $tarefas = [
                'total_semana' => $totalSemana,
                'concluidas_semana' => $concluidasSemana,
                'pendentes_semana' => (int)($row['pendentes_semana'] ?? 0),
                'atrasadas' => (int)$atrasadasStmt->fetchColumn(),
                'percentual_conclusao' => $totalSemana > 0 ? (int)round(($concluidasSemana / $totalSemana) * 100) : 0,
            ];
            $tempo = [
                'minutos_planejados_semana' => (int)($row['minutos_planejados_semana'] ?? 0),
                'minutos_concluidos_semana' => (int)($row['minutos_concluidos_semana'] ?? 0),
                'minutos_realizados_semana' => (int)($row['minutos_realizados_semana'] ?? 0),
            ];

            $periodStmt = $db->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$tempoExpr} ELSE 0 END) AS planejado,
                    SUM(CASE WHEN status = 'concluida' THEN {$tempoExpr} ELSE 0 END) AS concluido,
                    SUM(CASE WHEN status <> 'cancelada' THEN {$tempoRealExpr} ELSE 0 END) AS realizado
                FROM tarefas_estudo
                WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
            ");
            $periods = [
                'mensal' => [$monthStart, $monthEnd],
                'anual' => [$yearStart, $yearEnd],
            ];
            $progresso['semanal'] = [
                'total' => $tarefas['total_semana'],
                'concluidas' => $tarefas['concluidas_semana'],
                'percentual' => $tarefas['percentual_conclusao'],
            ];
            foreach ($periods as $key => $range) {
                $periodStmt->execute([$uid, $range[0], $range[1]]);
                $periodRow = $periodStmt->fetch() ?: [];
                $totalPeriodo = (int)($periodRow['total'] ?? 0);
                $concluidasPeriodo = (int)($periodRow['concluidas'] ?? 0);
                $progresso[$key] = [
                    'total' => $totalPeriodo,
                    'concluidas' => $concluidasPeriodo,
                    'percentual' => $totalPeriodo > 0 ? (int)round(($concluidasPeriodo / $totalPeriodo) * 100) : 0,
                ];
                $suffix = $key === 'mensal' ? 'mes' : 'ano';
                $tempo['minutos_planejados_' . $suffix] = (int)($periodRow['planejado'] ?? 0);
                $tempo['minutos_concluidos_' . $suffix] = (int)($periodRow['concluido'] ?? 0);
                $tempo['minutos_realizados_' . $suffix] = (int)($periodRow['realizado'] ?? 0);
            }

            $materiasAtraso = $db->prepare("
                SELECT COALESCE(NULLIF(materia, ''), 'Geral') AS materia, COUNT(*) AS total
                FROM tarefas_estudo
                WHERE usuario_id = ?
                  AND data_prevista < ?
                  AND status IN ('pendente','em_andamento','adiada','remarcada')
                GROUP BY COALESCE(NULLIF(materia, ''), 'Geral')
                ORDER BY total DESC
                LIMIT 5
            ");
            $materiasAtraso->execute([$uid, $today->format('Y-m-d')]);
            $materias['maior_atraso'] = $materiasAtraso->fetchAll();

            $materiasBoas = $db->prepare("
                SELECT COALESCE(NULLIF(materia, ''), 'Geral') AS materia,
                       COUNT(*) AS total,
                       SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas
                FROM tarefas_estudo
                WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ?
                GROUP BY COALESCE(NULLIF(materia, ''), 'Geral')
                HAVING total > 0
                ORDER BY (concluidas / total) DESC, concluidas DESC
                LIMIT 5
            ");
            $materiasBoas->execute([$uid, $monthStart, $monthEnd]);
            $materias['melhor_desempenho'] = array_map(static function ($row) {
                $total = (int)($row['total'] ?? 0);
                $concluidas = (int)($row['concluidas'] ?? 0);
                return [
                    'materia' => $row['materia'],
                    'total' => $total,
                    'concluidas' => $concluidas,
                    'percentual' => $total > 0 ? (int)round(($concluidas / $total) * 100) : 0,
                ];
            }, $materiasBoas->fetchAll());
        }

        $plano = [
            'tem_plano_ativo' => false,
            'titulo' => null,
            'criado_em' => null,
        ];
        if (dbTableExists($db, 'planos_estudo')) {
            $tipoFilter = dbColumnExists($db, 'planos_estudo', 'tipo_plano') ? "AND tipo_plano = 'semanal'" : '';
            $stmt = $db->prepare("
                SELECT titulo, criado_em
                FROM planos_estudo
                WHERE usuario_id = ? AND status = 'ativo' {$tipoFilter}
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row) {
                $plano = [
                    'tem_plano_ativo' => true,
                    'titulo' => $row['titulo'],
                    'criado_em' => $row['criado_em'],
                ];
            }
        }

        $perfil = ['tem_perfil' => false];
        if (dbTableExists($db, 'estudo_perfis')) {
            $where = dbColumnExists($db, 'estudo_perfis', 'onboarding_completo') ? 'AND onboarding_completo = 1' : '';
            $stmt = $db->prepare("SELECT 1 FROM estudo_perfis WHERE usuario_id = ? {$where} LIMIT 1");
            $stmt->execute([$uid]);
            $perfil['tem_perfil'] = (bool)$stmt->fetchColumn();
        }

        $exercicios = [
            'respondidos' => 0,
            'acertos' => 0,
            'erros' => 0,
            'taxa_acerto' => 0,
        ];
        if (dbTableExists($db, 'respostas_exercicios_planejados')) {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS respondidos,
                       SUM(CASE WHEN acertou = 1 THEN 1 ELSE 0 END) AS acertos,
                       SUM(CASE WHEN acertou = 0 THEN 1 ELSE 0 END) AS erros
                FROM respostas_exercicios_planejados
                WHERE usuario_id = ? AND DATE(respondido_em) BETWEEN ? AND ?
            ");
            $stmt->execute([$uid, $monthStart, $monthEnd]);
            $row = $stmt->fetch() ?: [];
            $respondidos = (int)($row['respondidos'] ?? 0);
            $acertos = (int)($row['acertos'] ?? 0);
            $exercicios = [
                'respondidos' => $respondidos,
                'acertos' => $acertos,
                'erros' => (int)($row['erros'] ?? 0),
                'taxa_acerto' => $respondidos > 0 ? (int)round(($acertos / $respondidos) * 100) : 0,
            ];
        }

        $simuladosPlanejados = [
            'liberados' => 0,
            'finalizados' => 0,
            'bloqueados' => 0,
        ];
        if (dbTableExists($db, 'simulados_planejados')) {
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN status IN ('liberado','iniciado') OR (status = 'bloqueado' AND data_liberacao <= CURDATE()) THEN 1 ELSE 0 END) AS liberados,
                    SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
                    SUM(CASE WHEN status = 'bloqueado' AND data_liberacao > CURDATE() THEN 1 ELSE 0 END) AS bloqueados
                FROM simulados_planejados
                WHERE usuario_id = ?
            ");
            $stmt->execute([$uid]);
            $row = $stmt->fetch() ?: [];
            $simuladosPlanejados = [
                'liberados' => (int)($row['liberados'] ?? 0),
                'finalizados' => (int)($row['finalizados'] ?? 0),
                'bloqueados' => (int)($row['bloqueados'] ?? 0),
            ];
        }

        jsonResponse([
            'ok' => true,
            'tarefas' => $tarefas,
            'tempo' => $tempo,
            'progresso' => $progresso,
            'plano' => $plano,
            'perfil' => $perfil,
            'exercicios' => $exercicios,
            'simulados_planejados' => $simuladosPlanejados,
            'materias' => $materias,
        ]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
