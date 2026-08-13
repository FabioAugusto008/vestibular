<?php
// api/simulados.php — Simulados completos

require_once __DIR__ . '/../helpers/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

    // ── LISTAR SIMULADOS DISPONÍVEIS ───────────────────────
    case 'listar':
        $stmt = $db->prepare("
            SELECT s.id, s.titulo, s.descricao, s.duracao_min, s.total_questoes,
                   (SELECT COUNT(*) FROM simulado_tentativas st 
                    WHERE st.simulado_id = s.id AND st.usuario_id = ? AND st.status = 'finalizado') as tentativas,
                   (SELECT MAX(ROUND(st2.acertos * 100.0 / (st2.acertos + st2.erros), 1)) 
                    FROM simulado_tentativas st2 
                    WHERE st2.simulado_id = s.id AND st2.usuario_id = ? AND st2.status = 'finalizado') as melhor_nota
            FROM simulados s
            WHERE s.ativo = 1
            ORDER BY s.id
        ");
        $stmt->execute([$uid, $uid]);
        
        jsonResponse(['ok' => true, 'simulados' => $stmt->fetchAll()]);
        break;

    // ── INICIAR SIMULADO ───────────────────────────────────
    case 'iniciar':
        requirePost();
        validateCsrfToken();
        $simulado_id = (int)($_POST['simulado_id'] ?? 0);

        if (!$simulado_id) {
            jsonResponse(['erro' => 'Simulado inválido.'], 400);
        }

        // Verifica se existe
        $sim = $db->prepare("SELECT * FROM simulados WHERE id = ? AND ativo = 1");
        $sim->execute([$simulado_id]);
        $simulado = $sim->fetch();
        if (!$simulado) {
            jsonResponse(['erro' => 'Simulado não encontrado.'], 404);
        }

        // Verifica se já tem uma tentativa em andamento
        $emand = $db->prepare("
            SELECT id FROM simulado_tentativas 
            WHERE usuario_id = ? AND simulado_id = ? AND status = 'em_andamento'
        ");
        $emand->execute([$uid, $simulado_id]);
        $tentativa_existente = $emand->fetch();

        if ($tentativa_existente) {
            $tentativa_id = $tentativa_existente['id'];
        } else {
            // Cria nova tentativa
            $ins = $db->prepare("
                INSERT INTO simulado_tentativas (usuario_id, simulado_id)
                VALUES (?, ?)
            ");
            $ins->execute([$uid, $simulado_id]);
            $tentativa_id = $db->lastInsertId();
        }

        // Busca questões do simulado
        $quest = $db->prepare("
            SELECT q.id, q.area, q.materia, q.conteudo, q.competencia, q.habilidade,
                   q.dificuldade, q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status, sq.ordem
            FROM simulado_questoes sq
            JOIN questoes q ON q.id = sq.questao_id
            WHERE sq.simulado_id = ?
              AND " . questoesApprovedWhere('q') . "
            ORDER BY sq.ordem
        ");
        $quest->execute([$simulado_id]);
        $questoes = questoesAttachAlternativas($db, $quest->fetchAll());
        if (!$questoes) {
            jsonResponse(['erro' => 'Não encontramos questões suficientes para este simulado ainda.'], 404);
        }

        // Busca respostas já dadas nessa tentativa
        $resp = $db->prepare("
            SELECT questao_id, resposta_marcada, acertou
            FROM simulado_respostas
            WHERE tentativa_id = ?
        ");
        $resp->execute([$tentativa_id]);
        $respostas = [];
        foreach ($resp->fetchAll() as $r) {
            $respostas[$r['questao_id']] = [
                'marcada' => $r['resposta_marcada'],
                'acertou' => (bool)$r['acertou']
            ];
        }

        jsonResponse([
            'ok' => true,
            'tentativa_id' => (int)$tentativa_id,
            'simulado' => [
                'titulo' => $simulado['titulo'],
                'duracao_min' => (int)$simulado['duracao_min'],
                'total_questoes' => (int)$simulado['total_questoes']
            ],
            'questoes' => $questoes,
            'respostas' => $respostas,
            'respondidas' => count($respostas)
        ]);
        break;

    // ── RESPONDER QUESTÃO DO SIMULADO ──────────────────────
    case 'responder':
        requirePost();
        validateCsrfToken();
        $tentativa_id = (int)($_POST['tentativa_id'] ?? 0);
        $questao_id = (int)($_POST['questao_id'] ?? 0);
        $resposta = strtoupper(trim($_POST['resposta'] ?? ''));

        if (!$tentativa_id || !$questao_id || !in_array($resposta, ['A','B','C','D','E'])) {
            jsonResponse(['erro' => 'Dados inválidos.'], 400);
        }

        // Verifica se a tentativa existe e está em andamento
        $tent = $db->prepare("
            SELECT * FROM simulado_tentativas 
            WHERE id = ? AND usuario_id = ? AND status = 'em_andamento'
        ");
        $tent->execute([$tentativa_id, $uid]);
        if (!$tent->fetch()) {
            jsonResponse(['erro' => 'Tentativa inválida ou já finalizada.'], 403);
        }

        $belongs = $db->prepare("
            SELECT 1
            FROM simulado_tentativas st
            JOIN simulado_questoes sq ON sq.simulado_id = st.simulado_id AND sq.questao_id = ?
            JOIN questoes q ON q.id = sq.questao_id
            WHERE st.id = ? AND " . questoesApprovedWhere('q') . "
            LIMIT 1
        ");
        $belongs->execute([$questao_id, $tentativa_id]);
        if (!$belongs->fetch()) {
            jsonResponse(['erro' => 'Questao nao pertence a este simulado.'], 403);
        }

        $correta = questoesRespostaCorreta($db, $questao_id);
        if (!$correta) {
            jsonResponse(['erro' => 'Gabarito da questao indisponivel.'], 500);
        }
        $acertou = ($correta['letra'] === $resposta) ? 1 : 0;

        // Salva ou atualiza resposta
        $stmt = $db->prepare("
            INSERT INTO simulado_respostas (tentativa_id, questao_id, resposta_marcada, acertou)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE resposta_marcada = ?, acertou = ?, respondido_em = NOW()
        ");
        $stmt->execute([$tentativa_id, $questao_id, $resposta, $acertou, $resposta, $acertou]);

        jsonResponse(['ok' => true]);
        break;

    // ── FINALIZAR SIMULADO ─────────────────────────────────
    case 'finalizar':
        requirePost();
        validateCsrfToken();
        $tentativa_id = (int)($_POST['tentativa_id'] ?? 0);
        $tempo_seg = (int)($_POST['tempo_seg'] ?? 0);

        if (!$tentativa_id) {
            jsonResponse(['erro' => 'Tentativa inválida.'], 400);
        }

        // Verifica tentativa
        $tent = $db->prepare("
            SELECT st.*, s.total_questoes 
            FROM simulado_tentativas st
            JOIN simulados s ON s.id = st.simulado_id
            WHERE st.id = ? AND st.usuario_id = ? AND st.status = 'em_andamento'
        ");
        $tent->execute([$tentativa_id, $uid]);
        $tentativa = $tent->fetch();
        if (!$tentativa) {
            jsonResponse(['erro' => 'Tentativa inválida ou já finalizada.'], 403);
        }

        // Calcula resultado
        $calc = $db->prepare("
            SELECT 
                SUM(acertou) as acertos,
                SUM(1 - acertou) as erros
            FROM simulado_respostas
            WHERE tentativa_id = ?
        ");
        $calc->execute([$tentativa_id]);
        $resultado = $calc->fetch();
        $acertos = (int)$resultado['acertos'];
        $erros = (int)$resultado['erros'];

        // Atualiza tentativa
        $upd = $db->prepare("
            UPDATE simulado_tentativas
            SET finalizado_em = NOW(), tempo_gasto_seg = ?, acertos = ?, erros = ?, status = 'finalizado'
            WHERE id = ?
        ");
        $upd->execute([$tempo_seg, $acertos, $erros, $tentativa_id]);

        // Busca gabarito
        $gab = $db->prepare("
            SELECT q.id, q.area, q.materia, q.conteudo, q.competencia, q.habilidade,
                   q.dificuldade, q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status
            FROM simulado_questoes sq
            JOIN questoes q ON q.id = sq.questao_id
            WHERE sq.simulado_id = ?
              AND " . questoesApprovedWhere('q') . "
        ");
        $gab->execute([$tentativa['simulado_id']]);
        $gabarito = [];
        foreach (questoesAttachAlternativas($db, $gab->fetchAll()) as $g) {
            $gabarito[$g['id']] = ['correta' => $g['resposta_correta'], 'explicacao' => $g['explicacao']];
        }

        $percentual = ($acertos + $erros) > 0 ? round(($acertos / ($acertos + $erros)) * 100, 1) : 0;

        jsonResponse([
            'ok' => true,
            'acertos' => $acertos,
            'erros' => $erros,
            'total' => $acertos + $erros,
            'percentual' => $percentual,
            'tempo_seg' => $tempo_seg,
            'gabarito' => $gabarito
        ]);
        break;

    // ── HISTÓRICO DE TENTATIVAS ────────────────────────────
    case 'historico':
        $stmt = $db->prepare("
            SELECT st.*, s.titulo,
                   ROUND(st.acertos * 100.0 / (st.acertos + st.erros), 1) as percentual
            FROM simulado_tentativas st
            JOIN simulados s ON s.id = st.simulado_id
            WHERE st.usuario_id = ? AND st.status = 'finalizado'
            ORDER BY st.finalizado_em DESC
            LIMIT 20
        ");
        $stmt->execute([$uid]);

        jsonResponse(['ok' => true, 'historico' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['erro' => 'Ação inválida.'], 400);
}
