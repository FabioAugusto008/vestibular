<?php
// api/conquistas.php — Sistema de conquistas/badges

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

    // ── LISTAR TODAS CONQUISTAS ────────────────────────────
    case 'listar':
        // Busca todas conquistas
        $stmt = $db->prepare("
            SELECT c.id, c.codigo, c.nome, c.descricao, c.icone, c.categoria, c.requisito,
                   cu.desbloqueada_em
            FROM conquistas c
            LEFT JOIN conquistas_usuario cu ON cu.conquista_id = c.id AND cu.usuario_id = ?
            ORDER BY c.categoria, c.requisito
        ");
        $stmt->execute([$uid]);
        $conquistas = $stmt->fetchAll();

        // Separa por categoria
        $resultado = [
            'streak' => [],
            'questoes' => [],
            'desempenho' => [],
            'especial' => []
        ];

        foreach ($conquistas as $c) {
            $resultado[$c['categoria']][] = [
                'id' => (int)$c['id'],
                'codigo' => $c['codigo'],
                'nome' => $c['nome'],
                'descricao' => $c['descricao'],
                'icone' => $c['icone'],
                'requisito' => (int)$c['requisito'],
                'desbloqueada' => !is_null($c['desbloqueada_em']),
                'desbloqueada_em' => $c['desbloqueada_em']
            ];
        }

        // Conta total
        $total = count($conquistas);
        $desbloqueadas = 0;
        foreach ($conquistas as $c) {
            if ($c['desbloqueada_em']) $desbloqueadas++;
        }
        $xp = $desbloqueadas * 120;
        $nivel = max(1, (int)floor($xp / 500) + 1);
        $proximoNivel = $nivel * 500;

        jsonResponse([
            'ok' => true,
            'conquistas' => $resultado,
            'total' => $total,
            'desbloqueadas' => $desbloqueadas,
            'gamificacao' => [
                'xp' => $xp,
                'nivel' => $nivel,
                'proximo_nivel_xp' => $proximoNivel,
            ],
        ]);
        break;

    // ── VERIFICAR E DESBLOQUEAR CONQUISTAS ─────────────────
    case 'verificar':
        requirePost();
        validateCsrfToken();
        $novas = [];

        // Busca estatísticas do usuário
        $stats = $db->prepare("
            SELECT 
                COUNT(*) as total_questoes,
                SUM(acertou) as acertos
            FROM respostas_usuario
            WHERE usuario_id = ?
        ");
        $stats->execute([$uid]);
        $estatisticas = $stats->fetch();
        $total_questoes = (int)$estatisticas['total_questoes'];
        $total_acertos = (int)$estatisticas['acertos'];

        // Busca streak
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

        // Dias estudados
        $dias = $db->prepare("SELECT COUNT(*) as total FROM desempenho_diario WHERE usuario_id = ? AND finalizado = 1");
        $dias->execute([$uid]);
        $dias_estudados = (int)$dias->fetch()['total'];

        // Verifica dia perfeito
        $hoje = date('Y-m-d');
        $perf = $db->prepare("
            SELECT acertos, erros FROM desempenho_diario 
            WHERE usuario_id = ? AND data = ? AND finalizado = 1
        ");
        $perf->execute([$uid, $hoje]);
        $dia_hoje = $perf->fetch();
        $dia_perfeito = $dia_hoje && $dia_hoje['acertos'] == 20 && $dia_hoje['erros'] == 0;

        // Verifica metas concluídas
        $metas = $db->prepare("SELECT COUNT(*) as total FROM metas_semanais WHERE usuario_id = ? AND concluida = 1");
        $metas->execute([$uid]);
        $metas_concluidas = (int)$metas->fetch()['total'];

        // Verifica simulados com 80%+
        $sim = $db->prepare("
            SELECT COUNT(*) as total FROM simulado_tentativas 
            WHERE usuario_id = ? AND status = 'finalizado' AND acertos * 100 / (acertos + erros) >= 80
        ");
        $sim->execute([$uid]);
        $simulados_80 = (int)$sim->fetch()['total'];

        // Verifica anotações
        $anot = $db->prepare("SELECT COUNT(*) as total FROM anotacoes WHERE usuario_id = ?");
        $anot->execute([$uid]);
        $tem_anotacao = (int)$anot->fetch()['total'] > 0;

        // Verifica revisões
        $rev = $db->prepare("
            SELECT COUNT(*) as total FROM respostas_usuario r1
            WHERE r1.usuario_id = ? 
            AND EXISTS (
                SELECT 1 FROM respostas_usuario r2 
                WHERE r2.usuario_id = r1.usuario_id 
                AND r2.questao_id = r1.questao_id 
                AND r2.data < r1.data 
                AND r2.acertou = 0
            )
        ");
        $rev->execute([$uid]);
        $revisoes_count = (int)$rev->fetch()['total'];
        $fez_revisao = $revisoes_count > 0;

        $primeira_redacao = false;
        if (dbTableExists($db, 'redacoes_enem')) {
            $red = $db->prepare("SELECT COUNT(*) FROM redacoes_enem WHERE usuario_id = ?");
            $red->execute([$uid]);
            $primeira_redacao = (int)$red->fetchColumn() > 0;
        }

        $semana_sem_atrasos = false;
        $plano_semanal_concluido = false;
        if (dbTableExists($db, 'tarefas_estudo')) {
            $today = new DateTimeImmutable('today');
            $weekStart = $today->modify('monday this week')->format('Y-m-d');
            $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
            $week = $db->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
                    SUM(CASE WHEN data_prevista < CURDATE() AND status IN ('pendente','em_andamento','adiada','remarcada') THEN 1 ELSE 0 END) AS atrasadas
                FROM tarefas_estudo
                WHERE usuario_id = ? AND data_prevista BETWEEN ? AND ? AND status <> 'cancelada'
            ");
            $week->execute([$uid, $weekStart, $weekEnd]);
            $row = $week->fetch() ?: [];
            $totalSemana = (int)($row['total'] ?? 0);
            $concluidasSemana = (int)($row['concluidas'] ?? 0);
            $atrasadasSemana = (int)($row['atrasadas'] ?? 0);
            $semana_sem_atrasos = $totalSemana > 0 && $atrasadasSemana === 0;
            $plano_semanal_concluido = $totalSemana > 0 && $concluidasSemana === $totalSemana;
        }

        // Busca conquistas não desbloqueadas
        $pendentes = $db->prepare("
            SELECT c.* FROM conquistas c
            WHERE c.id NOT IN (SELECT conquista_id FROM conquistas_usuario WHERE usuario_id = ?)
        ");
        $pendentes->execute([$uid]);

        foreach ($pendentes->fetchAll() as $c) {
            $desbloquear = false;

            switch ($c['codigo']) {
                case 'streak_3': $desbloquear = $streak >= 3; break;
                case 'streak_7': $desbloquear = $streak >= 7; break;
                case 'streak_30': $desbloquear = $streak >= 30; break;
                case 'streak_100': $desbloquear = $streak >= 100; break;
                case 'questoes_50': $desbloquear = $total_questoes >= 50; break;
                case 'questoes_100': $desbloquear = $total_questoes >= 100; break;
                case 'questoes_200': $desbloquear = $total_questoes >= 200; break;
                case 'questoes_500': $desbloquear = $total_questoes >= 500; break;
                case 'questoes_1000': $desbloquear = $total_questoes >= 1000; break;
                case 'acertos_100': $desbloquear = $total_acertos >= 100; break;
                case 'perfecto_dia': $desbloquear = $dia_perfeito; break;
                case 'simulado_80': $desbloquear = $simulados_80 > 0; break;
                case 'meta_semana': $desbloquear = $metas_concluidas > 0; break;
                case 'primeiro_dia': $desbloquear = $dias_estudados >= 1; break;
                case 'primeiro_simulado': 
                    $sim_count = $db->prepare("SELECT COUNT(*) as total FROM simulado_tentativas WHERE usuario_id = ? AND status = 'finalizado'");
                    $sim_count->execute([$uid]);
                    $legados = (int)$sim_count->fetch()['total'];
                    $planejados = 0;
                    if (dbTableExists($db, 'simulados_planejados')) {
                        $sim_plan = $db->prepare("SELECT COUNT(*) FROM simulados_planejados WHERE usuario_id = ? AND status = 'finalizado'");
                        $sim_plan->execute([$uid]);
                        $planejados = (int)$sim_plan->fetchColumn();
                    }
                    $desbloquear = ($legados + $planejados) > 0;
                    break;
                case 'anotador': $desbloquear = $tem_anotacao; break;
                case 'revisador': $desbloquear = $fez_revisao; break;
                case 'primeira_redacao': $desbloquear = $primeira_redacao; break;
                case 'semana_sem_atrasos': $desbloquear = $semana_sem_atrasos; break;
                case 'revisou_10_erros': $desbloquear = $revisoes_count >= 10; break;
                case 'plano_semanal_concluido':
                case 'primeira_semana_completa':
                    $desbloquear = $plano_semanal_concluido;
                    break;
            }

            if ($desbloquear) {
                $ins = $db->prepare("INSERT IGNORE INTO conquistas_usuario (usuario_id, conquista_id) VALUES (?, ?)");
                $ins->execute([$uid, $c['id']]);
                $novas[] = [
                    'codigo' => $c['codigo'],
                    'nome' => $c['nome'],
                    'descricao' => $c['descricao'],
                    'icone' => $c['icone']
                ];
            }
        }

        jsonResponse([
            'ok' => true,
            'novas_conquistas' => $novas
        ]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
