<?php
// Load real function declarations without executing an API's request dispatcher.
// Only trusted repository sources are evaluated; fixtures cannot supply code.
function stage08LoadFunctions(string $path, array $skip = []): void {
    $tokens = token_get_all(file_get_contents($path));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) continue;
        $name = $tokens[$j][1];
        $source = ''; $depth = 0; $opened = false;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            $source .= $text;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++; $opened = true;
            } elseif ($token === '}' && --$depth === 0 && $opened) break;
        }
        if (!in_array($name, $skip, true)) eval($source);
    }
}

require_once __DIR__ . '/../server/helpers/helpers.php';
stage08LoadFunctions(__DIR__ . '/../server/services/ai/estudaiService.php', ['estudaiGerarPlanoSemanal']);
stage08LoadFunctions(__DIR__ . '/../server/api/plano-estudos.php');
stage08LoadFunctions(__DIR__ . '/../server/api/calendario-estudai.php');

$remoteCalls = 0;
function estudaiGerarPlanoSemanal(array $entrada): array {
    $GLOBALS['remoteCalls']++;
    return ['ok' => false, 'provider' => 'openrouter', 'erro_tecnico' => 'Remote generator must not be reached in local mode'];
}
class Stage08NoDatabase extends PDO { public function __construct() {} }
class Stage08FailingPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'INSERT INTO `eventos_calendario_estudai`')) {
            throw new RuntimeException('Controlled persistence failure before event insert');
        }
        return parent::prepare($query, $options);
    }
}
putenv('ESTUDAI_AI_DISABLED=true');
date_default_timezone_set('America/Sao_Paulo');
$checks = [];
function stage08Check(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException('Check failed: ' . $name);
    $GLOBALS['checks'][] = ['test' => $name, 'result' => 'PASS'];
}

function stage08Profile(): array {
    return ['materias_base' => ['Matematica', 'Portugues'], 'reforcos' => ['Matematica' => 3],
        'disponibilidade' => ['segunda' => [['inicio' => '18:00', 'fim' => '20:30']],
            'quarta' => [['inicio' => '23:00', 'fim' => '23:50']]]];
}
function stage08Window(array $profile): array {
    $dates = planoDatasDisponiveisEntre(new DateTimeImmutable('2026-11-16'), new DateTimeImmutable('2026-11-22'), planoDisponibilidadeValida($profile));
    return ['hoje' => '2026-11-11', 'semana_inicio' => '2026-11-16', 'semana_fim' => '2026-11-18',
        'dias_disponiveis' => planoDisponibilidadeValida($profile), 'datas_disponiveis' => $dates,
        'dias_cobertura_obrigatoria' => $dates, 'data_limite' => null, 'timezone' => 'America/Sao_Paulo'];
}

try {
    $mode = $argv[1] ?? 'pure';
    if ($mode === 'invalid-month') { calendarioValidMonth('2026-13'); throw new RuntimeException('Invalid month accepted'); }
    if ($mode === 'no-subjects') {
        $p = stage08Profile(); $p['materias_base'] = [];
        planoResultadoFallbackSemanal($p, stage08Window($p), 'Local mode');
        throw new RuntimeException('Missing subjects accepted');
    }
    if ($mode === 'no-availability') {
        normalizarJanelaPlanoSemanal(['disponibilidade' => []]);
        throw new RuntimeException('Missing availability accepted');
    }
    $profile = stage08Profile(); $window = stage08Window($profile);
    if (in_array($mode, ['transaction', 'hold-lock'], true)) {
        stage08Check(DB_NAME === 'estudai_teste_2026' && DB_USER === 'estudai_teste_app', 'test_database_guard');
        $db = new Stage08FailingPDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        stage08Check($db->query('SELECT DATABASE()')->fetchColumn() === 'estudai_teste_2026', 'actual_database_guard');
        $uid = (int)($argv[2] ?? 0);
        $s = $db->prepare('SELECT email FROM usuarios WHERE id=?'); $s->execute([$uid]);
        stage08Check(str_ends_with((string)$s->fetchColumn(), '@estudai.invalid'), 'fictional_user_guard');
        if ($mode === 'hold-lock') {
            $db->beginTransaction();
            $s = $db->prepare('SELECT id FROM estudo_perfis WHERE usuario_id=? FOR UPDATE');
            $s->execute([$uid]);
            stage08Check((bool)$s->fetchColumn(), 'known_profile_locked');
            echo "LOCKED\n"; fflush(STDOUT);
            stream_set_timeout(STDIN, 5); fgets(STDIN);
            $db->rollBack(); exit(0);
        }
        $actual = planoPerfil($db, $uid);
        $window = normalizarJanelaPlanoSemanal($actual);
        $result = planoResultadoFallbackSemanal($actual, $window, 'Local mode');
        $failed = false;
        try { planoSalvarSemanal($db, $uid, $actual, $result, $window); }
        catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'Controlled persistence'); }
        stage08Check($failed, 'failure_after_plan_version_item_task_inserts');
        stage08Check(!$db->inTransaction(), 'persistence_rolls_back_its_transaction');
        foreach (['planos_estudo','plano_versoes','plano_estudo_itens','tarefas_estudo','eventos_calendario_estudai','planejamento_semanal_controle'] as $table) {
            $s = $db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE usuario_id=?'); $s->execute([$uid]);
            stage08Check((int)$s->fetchColumn() === 0, 'no_partial_rows_'.$table);
        }
    } else {
        $result = planoGerarSemanalValidado(new Stage08NoDatabase(), 1, $profile, ['perfil' => $profile], $window);
        stage08Check($remoteCalls === 0, 'disabled_AI_skips_remote_generator_entirely');
        stage08Check($result['ok'] && $result['origem'] === 'fallback' && $result['provider'] === 'local', 'honest_local_origin');
        stage08Check(!str_contains($result['aviso_usuario'], 'caiu'), 'disabled_AI_is_not_reported_as_remote_outage');
        stage08Check(planoNormalizarData('2026-02-31') === null, 'invalid_calendar_date_rejected');
        $tasks = $result['plano_validado']['tarefas'];
        stage08Check(count($tasks) === 3, 'existing_fallback_slots_preserved');
        $seen = [];
        foreach ($tasks as $t) {
            stage08Check(planoTaskFitsBlock($t, $window), 'task_within_saved_availability');
            stage08Check(in_array($t['materia'], $profile['materias_base'], true), 'selected_subject_only');
            stage08Check($t['tempo_estimado'] === planoTaskDurationMinutes($t['hora_inicio'], $t['hora_fim']), 'duration_matches_hours');
            $key = $t['data'].'|'.$t['hora_inicio'].'|'.$t['hora_fim'];
            stage08Check(!isset($seen[$key]), 'no_duplicate_slot'); $seen[$key] = true;
        }
        $dates = planoDatasDisponiveisEntre(new DateTimeImmutable('2026-12-28'), new DateTimeImmutable('2027-01-04'), planoDisponibilidadeValida($profile));
        stage08Check(array_column($dates, 'data') === ['2026-12-28','2026-12-30','2027-01-04'], 'year_boundary_preserves_local_dates');
        stage08Check(planoValidHour('24:00') === null && planoValidHour('23:50') === '23:50', 'hour_boundaries');
        $bad = $result['plano_validado']; $bad['tarefas'][0]['materia'] = 'Unselected test subject';
        $bad['tarefas'] = [$bad['tarefas'][0]];
        stage08Check(validarPlanoSemanalGerado($bad, $profile, $window) === [], 'foreign_subject_is_not_persistable');
    }
    echo json_encode(['result' => 'PASS', 'mode' => $mode, 'checks' => count($checks), 'tests' => $checks, 'remote_generator_calls' => $remoteCalls]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1);
}
