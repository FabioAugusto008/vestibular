<?php
// api/onboarding.php - Onboarding obrigatorio e perfil semanal 0.0.4-alpha

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$db = getDB();
$uid = currentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

function onboardingEnsureUsuarioValido(PDO $db, int $uid): void {
    $stmt = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE id = ?');
    $stmt->execute([$uid]);
    if ((int)$stmt->fetchColumn() < 1) {
        initSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(['erro' => 'Sua sessao expirou. Entre novamente para salvar o onboarding.'], 401);
    }
}

function onboardingJson($value): array {
    if (!$value) {
        return [];
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function onboardingDiaKey($value): ?string {
    $clean = strtolower(trim((string)$value));
    $clean = str_replace(['ç', 'á', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'ú'], ['c', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'u'], $clean);
    $map = [
        'seg' => 'segunda',
        'segunda' => 'segunda',
        'segunda-feira' => 'segunda',
        'ter' => 'terca',
        'terca' => 'terca',
        'terça' => 'terca',
        'terca-feira' => 'terca',
        'terça-feira' => 'terca',
        'qua' => 'quarta',
        'quarta' => 'quarta',
        'quarta-feira' => 'quarta',
        'qui' => 'quinta',
        'quinta' => 'quinta',
        'quinta-feira' => 'quinta',
        'sex' => 'sexta',
        'sexta' => 'sexta',
        'sexta-feira' => 'sexta',
        'sab' => 'sabado',
        'sabado' => 'sabado',
        'sábado' => 'sabado',
        'sabado-feira' => 'sabado',
        'dom' => 'domingo',
        'domingo' => 'domingo',
    ];
    if (isset($map[$clean])) {
        return $map[$clean];
    }
    $prefix = substr($clean, 0, 3);
    return $map[$prefix] ?? null;
}

function onboardingValidTime($value, string $field): string {
    $value = sanitizeTextValue($value, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        jsonResponse(['erro' => "Horario invalido em {$field}. Use HH:MM."], 400);
    }
    [$h, $m] = array_map('intval', explode(':', $value));
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        jsonResponse(['erro' => "Horario invalido em {$field}."], 400);
    }
    return sprintf('%02d:%02d', $h, $m);
}

function onboardingDateOrNull($value): ?string {
    $value = sanitizeTextValue($value ?? '', 20);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        jsonResponse(['erro' => 'Data da prova invalida. Use YYYY-MM-DD.'], 400);
    }
    return $value;
}

function onboardingModo($value): string {
    $value = strtolower(sanitizeTextValue($value, 40));
    $allowed = ['enem', 'vestibular', 'prova_escolar', 'concurso', 'faculdade', 'curso_tecnico', 'habilidade', 'outro'];
    return in_array($value, $allowed, true) ? $value : 'outro';
}

function onboardingMateriasBasePorModo(string $modo, ?string $lingua, array $materiasManuais = []): array {
    if ($modo === 'enem') {
        if (!in_array($lingua, ['ingles', 'espanhol'], true)) {
            jsonResponse(['erro' => 'Escolha Ingles ou Espanhol para o modo ENEM.'], 400);
        }
        return [
            'Redacao',
            'Linguagens',
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
            $lingua === 'espanhol' ? 'Espanhol' : 'Ingles',
        ];
    }

    $materias = normalizeStringList($materiasManuais, 30, 80);
    if (!$materias) {
        jsonResponse(['erro' => 'Informe pelo menos uma materia ou conteudo para este objetivo.'], 400);
    }
    return $materias;
}

function onboardingNormalizeDisponibilidade($value): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        jsonResponse(['erro' => 'Disponibilidade deve ser enviada em formato estruturado.'], 400);
    }

    $result = [];
    foreach ($value as $dia => $blocos) {
        $diaKey = onboardingDiaKey($dia);
        if (!$diaKey || !is_array($blocos)) {
            continue;
        }
        $normalizados = [];
        foreach ($blocos as $bloco) {
            if (!is_array($bloco)) {
                continue;
            }
            $inicio = onboardingValidTime($bloco['inicio'] ?? $bloco['hora_inicio'] ?? '', "{$diaKey}.inicio");
            $fim = onboardingValidTime($bloco['fim'] ?? $bloco['hora_fim'] ?? '', "{$diaKey}.fim");
            if (strtotime($fim) <= strtotime($inicio)) {
                jsonResponse(['erro' => 'Hora final deve ser maior que hora inicial.'], 400);
            }
            $normalizados[] = ['inicio' => $inicio, 'fim' => $fim];
            if (count($normalizados) >= 5) {
                break;
            }
        }
        usort($normalizados, static fn($a, $b) => strcmp($a['inicio'], $b['inicio']));
        for ($i = 1; $i < count($normalizados); $i++) {
            if ($normalizados[$i]['inicio'] < $normalizados[$i - 1]['fim']) {
                jsonResponse(['erro' => "Ha sobreposicao de horarios em {$diaKey}."], 400);
            }
        }
        if ($normalizados) {
            $result[$diaKey] = $normalizados;
        }
    }

    if (!$result) {
        jsonResponse(['erro' => 'Informe pelo menos um dia com horario valido.'], 400);
    }
    return $result;
}

function onboardingNormalizeReforcos($value, array $materiasBase): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        $value = [];
    }

    $result = [];
    foreach ($materiasBase as $materia) {
        $raw = $value[$materia] ?? $value[strtolower($materia)] ?? 'normal';
        if (is_numeric($raw)) {
            $peso = max(1, min(3, (int)$raw));
        } else {
            $raw = strtolower(sanitizeTextValue($raw, 20));
            $peso = match ($raw) {
                'alto', 'alta', '3' => 3,
                'medio', 'media', '2' => 2,
                default => 1,
            };
        }
        $result[$materia] = $peso;
    }
    return $result;
}

function onboardingPerfilFromRow(?array $row): ?array {
    if (!$row) {
        return null;
    }

    $perfilJson = onboardingJson($row['perfil_json'] ?? '');
    $disponibilidade = onboardingJson($row['disponibilidade_json'] ?? '');
    $reforcos = onboardingJson($row['reforcos_json'] ?? '');
    $materiasBase = onboardingJson($row['materias_base_json'] ?? '');
    if (!$disponibilidade) {
        $disponibilidade = $perfilJson['disponibilidade'] ?? $perfilJson['horarios'] ?? [];
    }
    if (!$reforcos) {
        $reforcos = $perfilJson['reforcos'] ?? [];
    }
    $dias = array_keys($disponibilidade);
    if (!$dias) {
        $dias = onboardingJson($row['dias_semana_json'] ?? '');
    }

    return array_merge($perfilJson, [
        'id' => (int)$row['id'],
        'usuario_id' => (int)$row['usuario_id'],
        'onboarding_completo' => !empty($row['onboarding_completo']),
        'objetivo' => (string)$row['objetivo'],
        'modo_estudo' => $row['modo_estudo'] ?: ($perfilJson['modo_estudo'] ?? $row['objetivo']),
        'data_prova' => $row['data_prova'] ?: null,
        'horas_dia' => isset($row['horas_dia']) ? (float)$row['horas_dia'] : null,
        'lingua_estrangeira' => $row['lingua_estrangeira'] ?: ($perfilJson['lingua_estrangeira'] ?? null),
        'dias_semana' => $dias,
        'dias' => $dias,
        'disponibilidade' => $disponibilidade,
        'disponibilidade_json' => $disponibilidade,
        'horarios' => $disponibilidade,
        'reforcos' => $reforcos,
        'reforcos_json' => $reforcos,
        'materias_base' => $materiasBase ?: ($perfilJson['materias_base'] ?? []),
        'materias_base_json' => $materiasBase ?: ($perfilJson['materias_base'] ?? []),
        'dificuldades' => onboardingJson($row['dificuldades_json'] ?? ''),
        'prioridades' => onboardingJson($row['prioridades_json'] ?? ''),
        'preferencia_estudo' => $row['preferencia_estudo'] ?? null,
        'preferencia' => $row['preferencia_estudo'] ?? null,
        'meta_semanal' => $row['meta_semanal'] ?? null,
        'notificacoes' => !empty($row['notificacoes']),
        'criado_em' => $row['criado_em'] ?? null,
        'atualizado_em' => $row['atualizado_em'] ?? null,
    ]);
}

function onboardingLoadPerfil(PDO $db, int $uid): ?array {
    $stmt = $db->prepare('SELECT * FROM estudo_perfis WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    return onboardingPerfilFromRow($stmt->fetch() ?: null);
}

function onboardingLoadRespostas(PDO $db, int $uid): ?array {
    $stmt = $db->prepare('
        SELECT respostas_json
        FROM onboarding_respostas
        WHERE usuario_id = ?
        ORDER BY concluido_em DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute([$uid]);
    $raw = $stmt->fetchColumn();
    return $raw ? onboardingJson($raw) : null;
}

try {
    onboardingEnsureUsuarioValido($db, $uid);

    if (
        !dbTableExists($db, 'estudo_perfis') ||
        !dbTableExists($db, 'onboarding_respostas') ||
        !dbColumnExists($db, 'estudo_perfis', 'onboarding_completo') ||
        !dbColumnExists($db, 'estudo_perfis', 'modo_estudo') ||
        !dbColumnExists($db, 'estudo_perfis', 'lingua_estrangeira') ||
        !dbColumnExists($db, 'estudo_perfis', 'disponibilidade_json') ||
        !dbColumnExists($db, 'estudo_perfis', 'reforcos_json') ||
        !dbColumnExists($db, 'estudo_perfis', 'materias_base_json')
    ) {
        jsonResponse(['erro' => 'Banco incompleto. Aplique database/schema.sql.'], 503);
    }

    switch ($action) {
        case 'status':
            $perfil = onboardingLoadPerfil($db, $uid);
            $completo = $perfil ? !empty($perfil['onboarding_completo']) : false;
            jsonResponse([
                'ok' => true,
                'onboarding_completo' => $completo,
                'concluido' => $completo,
                'perfil' => $completo ? $perfil : null,
            ]);
            break;

        case 'carregar':
            $perfil = onboardingLoadPerfil($db, $uid);
            jsonResponse([
                'ok' => true,
                'onboarding_completo' => $perfil ? !empty($perfil['onboarding_completo']) : false,
                'perfil' => $perfil,
                'respostas' => onboardingLoadRespostas($db, $uid),
            ]);
            break;

        case 'salvar':
            requirePost();
            validateCsrfToken();

            $payload = requestPayload(30000);
            $modo = onboardingModo($payload['modo_estudo'] ?? $payload['objetivo'] ?? '');
            $objetivo = $modo;
            $dataProva = onboardingDateOrNull($payload['data_prova'] ?? null);
            $lingua = $modo === 'enem' ? strtolower(sanitizeTextValue($payload['lingua_estrangeira'] ?? '', 20)) : null;
            if ($lingua !== null && !in_array($lingua, ['ingles', 'espanhol'], true)) {
                jsonResponse(['erro' => 'Escolha Ingles ou Espanhol para o modo ENEM.'], 400);
            }

            $materiasManuais = $payload['materias_base'] ?? $payload['materias'] ?? $payload['materias_manuais'] ?? [];
            $materiasBase = onboardingMateriasBasePorModo($modo, $lingua, is_array($materiasManuais) ? $materiasManuais : normalizeStringList($materiasManuais, 30, 80));
            $disponibilidade = onboardingNormalizeDisponibilidade($payload['disponibilidade'] ?? $payload['disponibilidade_json'] ?? $payload['horarios'] ?? []);
            $reforcos = onboardingNormalizeReforcos($payload['reforcos'] ?? $payload['reforcos_json'] ?? [], $materiasBase);

            $dias = array_keys($disponibilidade);
            $horasDia = 0.0;
            foreach ($disponibilidade as $blocos) {
                $minDia = 0;
                foreach ($blocos as $bloco) {
                    $minDia += max(0, (int)((strtotime($bloco['fim']) - strtotime($bloco['inicio'])) / 60));
                }
                $horasDia = max($horasDia, round($minDia / 60, 2));
            }
            $horasDia = max(0.25, min(12, $horasDia));

            $intensidade = sanitizeTextValue($payload['intensidade'] ?? 'ia', 40);
            if (!in_array($intensidade, ['leve', 'moderada', 'intensa', 'ia'], true)) {
                $intensidade = 'ia';
            }
            $exerciciosDia = sanitizeTextValue($payload['exercicios_dia'] ?? 'ia', 40);
            if (!in_array($exerciciosDia, ['3_5', '6_10', '11_20', 'ia'], true)) {
                $exerciciosDia = 'ia';
            }
            $frequenciaSimulados = sanitizeTextValue($payload['frequencia_simulados'] ?? 'ia', 40);
            if (!in_array($frequenciaSimulados, ['semanal', 'quinzenal', 'mensal', 'quando_liberar', 'ia'], true)) {
                $frequenciaSimulados = 'ia';
            }

            $obstaculos = normalizeStringList($payload['obstaculos'] ?? [], 12, 80);
            $informacaoLivre = sanitizeTextValue($payload['informacao_livre'] ?? '', 1000);
            $metaSemanal = sanitizeTextValue($payload['meta_semanal'] ?? '', 120);
            $notificacoes = !empty($payload['notificacoes']) && $payload['notificacoes'] !== 'false' ? 1 : 0;
            $conteudosReforco = normalizeStringList($payload['conteudos_reforco'] ?? [], 20, 120);
            $preferencia = sanitizeTextValue($payload['preferencia_estudo'] ?? $payload['preferencia'] ?? 'misto', 80);

            $perfil = [
                'objetivo' => $objetivo,
                'modo_estudo' => $modo,
                'data_prova' => $dataProva,
                'horas_dia' => $horasDia,
                'dias_semana' => $dias,
                'dias' => $dias,
                'lingua_estrangeira' => $lingua,
                'disponibilidade' => $disponibilidade,
                'horarios' => $disponibilidade,
                'materias_base' => $materiasBase,
                'reforcos' => $reforcos,
                'conteudos_reforco' => $conteudosReforco,
                'preferencia_estudo' => $preferencia,
                'preferencia' => $preferencia,
                'intensidade' => $intensidade,
                'exercicios_dia' => $exerciciosDia,
                'frequencia_simulados' => $frequenciaSimulados,
                'obstaculos' => $obstaculos,
                'informacao_livre' => $informacaoLivre,
                'meta_semanal' => $metaSemanal,
                'notificacoes' => (bool)$notificacoes,
                'onboarding_completo' => true,
                'nivel_materias_info' => 'A IA identificara o nivel pelo desempenho real.',
                'versao' => ESTUDAI_VERSION,
            ];

            $dificuldades = array_keys(array_filter($reforcos, static fn($peso) => (int)$peso >= 2));
            $prioridades = array_keys(array_filter($reforcos, static fn($peso) => (int)$peso >= 3));

            $respostas = $payload['respostas'] ?? $payload;
            if (is_string($respostas)) {
                $decoded = json_decode($respostas, true);
                $respostas = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($respostas)) {
                $respostas = [];
            }

            $db->beginTransaction();

            $stmt = $db->prepare('
                INSERT INTO estudo_perfis
                    (usuario_id, onboarding_completo, objetivo, modo_estudo, lingua_estrangeira, data_prova, horas_dia,
                     dias_semana_json, dificuldades_json, prioridades_json, preferencia_estudo, meta_semanal, notificacoes,
                     disponibilidade_json, reforcos_json, materias_base_json, perfil_json)
                VALUES
                    (:usuario_id, 1, :objetivo, :modo_estudo, :lingua_estrangeira, :data_prova, :horas_dia,
                     :dias_semana_json, :dificuldades_json, :prioridades_json, :preferencia_estudo, :meta_semanal, :notificacoes,
                     :disponibilidade_json, :reforcos_json, :materias_base_json, :perfil_json)
                ON DUPLICATE KEY UPDATE
                    onboarding_completo = 1,
                    objetivo = VALUES(objetivo),
                    modo_estudo = VALUES(modo_estudo),
                    lingua_estrangeira = VALUES(lingua_estrangeira),
                    data_prova = VALUES(data_prova),
                    horas_dia = VALUES(horas_dia),
                    dias_semana_json = VALUES(dias_semana_json),
                    dificuldades_json = VALUES(dificuldades_json),
                    prioridades_json = VALUES(prioridades_json),
                    preferencia_estudo = VALUES(preferencia_estudo),
                    meta_semanal = VALUES(meta_semanal),
                    notificacoes = VALUES(notificacoes),
                    disponibilidade_json = VALUES(disponibilidade_json),
                    reforcos_json = VALUES(reforcos_json),
                    materias_base_json = VALUES(materias_base_json),
                    perfil_json = VALUES(perfil_json),
                    atualizado_em = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                ':usuario_id' => $uid,
                ':objetivo' => $objetivo,
                ':modo_estudo' => $modo,
                ':lingua_estrangeira' => $lingua,
                ':data_prova' => $dataProva,
                ':horas_dia' => $horasDia,
                ':dias_semana_json' => jsonEncodeSafe($dias),
                ':dificuldades_json' => jsonEncodeSafe($dificuldades),
                ':prioridades_json' => jsonEncodeSafe($prioridades),
                ':preferencia_estudo' => $preferencia,
                ':meta_semanal' => $metaSemanal,
                ':notificacoes' => $notificacoes,
                ':disponibilidade_json' => jsonEncodeSafe($disponibilidade),
                ':reforcos_json' => jsonEncodeSafe($reforcos),
                ':materias_base_json' => jsonEncodeSafe($materiasBase),
                ':perfil_json' => jsonEncodeSafe($perfil),
            ]);

            $snapshot = [
                'perfil' => $perfil,
                'respostas' => $respostas,
                'salvo_em' => date('c'),
            ];
            $snap = $db->prepare('
                INSERT INTO onboarding_respostas (usuario_id, versao, respostas_json)
                VALUES (?, ?, ?)
            ');
            $snap->execute([$uid, ESTUDAI_VERSION, jsonEncodeSafe($snapshot)]);

            $db->commit();

            jsonResponse([
                'ok' => true,
                'onboarding_completo' => true,
                'perfil' => onboardingLoadPerfil($db, $uid),
            ]);
            break;

        default:
            jsonResponse(['erro' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    logTechnicalError('onboarding_api_error', $e);
    jsonResponse(['erro' => 'Nao foi possivel processar o onboarding agora.'], 500);
}
