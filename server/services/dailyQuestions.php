<?php
// Daily answers and review attempts deliberately use separate existing tables.
require_once __DIR__ . '/../helpers/helpers.php';
date_default_timezone_set('America/Sao_Paulo');

class QuestionFlowError extends RuntimeException {}

function questionFlowFail(string $message, int $status): void {
    throw new QuestionFlowError($message, $status);
}

function questionFlowAction(array $allowed, int $maxBytes = 8000): array {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $payload = $method === 'POST' ? requestPayload($maxBytes) : [];
    $action = $_GET['action'] ?? '';
    if (!is_string($action) || !in_array($action, $allowed[$method] ?? [], true)
        || (isset($payload['action']) && $payload['action'] !== $action)) {
        questionFlowFail('Metodo ou acao nao autorizados.', 403);
    }
    return [$action, $payload];
}

function questionPositiveId($value): int {
    if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value)) {
        questionFlowFail('ID invalido.', 400);
    }
    return (int)$value;
}

function questionLockUser(PDO $db, int $uid): void {
    $s = $db->prepare('SELECT usuario_id FROM estudo_perfis WHERE usuario_id = ? FOR UPDATE');
    $s->execute([$uid]);
    if (!$s->fetchColumn()) questionFlowFail('Conclua seu perfil antes de responder.', 409);
}

function questionDailyRows(PDO $db, string $day): array {
    $s = $db->prepare('SELECT q.id, q.area, q.materia, q.conteudo, q.dificuldade,
        q.fonte, q.ano, q.prova, q.enunciado, q.explicacao, q.status
        FROM questoes_do_dia d JOIN questoes q ON q.id=d.questao_id
        WHERE d.data=? AND ' . questoesApprovedWhere('q') . ' ORDER BY q.materia,q.conteudo,q.id');
    $s->execute([$day]);
    return questoesAttachAlternativas($db, $s->fetchAll());
}

function questionPublicRows(array $rows, array $answers = []): array {
    foreach ($rows as &$q) {
        $answered = isset($answers[$q['id']]);
        unset($q['alternativa_correta_id'], $q['resposta_correta']);
        if (!$answered) unset($q['explicacao']);
        foreach ($q['alternativas'] as &$alt) unset($alt['correta']);
        unset($alt);
    }
    unset($q);
    return $rows;
}

function questionFeedback(array $q, array $answer): array {
    return ['marcada' => $answer['resposta_marcada'], 'acertou' => (bool)$answer['acertou'],
        'correta' => $q['resposta_correta'], 'explicacao' => $q['explicacao']];
}

function questionDailyLoad(PDO $db, int $uid, string $day): array {
    $rows = questionDailyRows($db, $day);
    $s = $db->prepare('SELECT r.questao_id,r.resposta_marcada,r.acertou FROM respostas_usuario r
        JOIN questoes_do_dia d ON d.questao_id=r.questao_id AND d.data=r.data
        WHERE r.usuario_id=? AND r.data=?');
    $s->execute([$uid,$day]);
    $stored = [];
    foreach ($s->fetchAll() as $a) $stored[$a['questao_id']]=$a;
    $answers = []; $feedback = [];
    foreach ($rows as $q) if (isset($stored[$q['id']])) {
        $a = questionFeedback($q, $stored[$q['id']]);
        $answers[$q['id']] = $a; $feedback[$q['id']] = ['correta'=>$a['correta'],'explicacao'=>$a['explicacao']];
    }
    $s=$db->prepare('SELECT finalizado,acertos,erros,tempo_seg FROM desempenho_diario WHERE usuario_id=? AND data=?');
    $s->execute([$uid,$day]); $performance=$s->fetch() ?: null;
    return ['ok'=>true,'questoes'=>questionPublicRows($rows,$answers),'respostas'=>(object)$answers,
        'gabarito'=>(object)$feedback,'finalizado'=>(bool)($performance['finalizado']??false),
        'total'=>count($rows),'respondidas'=>count($answers),'desempenho'=>$performance,
        'aviso'=>!$rows?'O treino de hoje ainda nao foi preparado.':(count($rows)<20?'Treino com '.count($rows).' questoes disponiveis.':null)];
}

function questionDailyPrepare(PDO $db, string $day, int $quantity = 20): array {
    if ($quantity<1 || $quantity>20) questionFlowFail('Quantidade deve estar entre 1 e 20.',400);
    $lock='estudai.daily.'.DB_NAME.'.'.$day;
    $s=$db->prepare('SELECT GET_LOCK(?,5)');$s->execute([$lock]);
    if((int)$s->fetchColumn()!==1)questionFlowFail('Treino sendo preparado. Tente novamente.',409);
    try {
        $db->beginTransaction();
        // Serialize the shared batch without granting UPDATE on the catalog.
        $s=$db->prepare('SELECT COUNT(*) FROM questoes_do_dia WHERE data=?');$s->execute([$day]);
        $reused=(int)$s->fetchColumn()>0;
        if (!$reused) {
            $ids=$db->query("SELECT q.id FROM questoes q JOIN questoes_alternativas a ON a.questao_id=q.id
                WHERE ".questoesApprovedWhere('q')." AND TRIM(q.enunciado)<>'' AND TRIM(COALESCE(q.explicacao,''))<>''
                GROUP BY q.id HAVING COUNT(*)=5 AND SUM(a.correta)=1
                AND SUM(TRIM(a.texto)='')=0 AND COUNT(DISTINCT a.letra)=5
                AND SUM(a.letra IN ('A','B','C','D','E'))=5
                ORDER BY RAND() LIMIT ".$quantity)->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) questionFlowFail('Nao ha questoes aprovadas completas disponiveis.',409);
            $insert=$db->prepare('INSERT INTO questoes_do_dia (data,questao_id) VALUES (?,?)');
            foreach ($ids as $id) $insert->execute([$day,$id]);
        }
        $db->commit();
        return ['ok'=>true,'reutilizado'=>$reused];
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
    finally { $release=$db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lock]); }
}

function questionChosenAlternative(array $q, array $payload): array {
    $letter=$payload['resposta']??null;
    if ($letter!==null && (!is_string($letter)||!preg_match('/^[A-E]$/D',$letter))) questionFlowFail('Alternativa invalida.',400);
    $id=isset($payload['alternativa_id'])?questionPositiveId($payload['alternativa_id']):null;
    foreach ($q['alternativas'] as $a) {
        if (($id!==null && $a['id']===$id)||($id===null && $a['letra']===$letter)) {
            if ($letter!==null && $a['letra']!==$letter) questionFlowFail('Alternativa nao corresponde a letra.',400);
            return $a;
        }
    }
    questionFlowFail('Alternativa nao pertence a esta questao.',400);
}

function questionDailyAnswer(PDO $db, int $uid, string $day, array $payload): array {
    $id=questionPositiveId($payload['questao_id']??null);
    $db->beginTransaction();
    try {
        questionLockUser($db,$uid);
        $rows=questionDailyRows($db,$day);$q=null;
        foreach($rows as $row)if((int)$row['id']===$id)$q=$row;
        if(!$q)questionFlowFail('Questao indisponivel no treino de hoje.',404);
        $chosen=questionChosenAlternative($q,$payload);
        $s=$db->prepare('SELECT resposta_marcada,acertou FROM respostas_usuario WHERE usuario_id=? AND questao_id=? AND data=?');
        $s->execute([$uid,$id,$day]);$existing=$s->fetch();
        if($existing) {
            if($existing['resposta_marcada']!==$chosen['letra'])questionFlowFail('Questao ja respondida; a resposta original foi preservada.',409);
            $db->commit();
            return ['ok'=>true,'reutilizado'=>true]+questionFeedback($q,$existing);
        }
        $s=$db->prepare('SELECT finalizado FROM desempenho_diario WHERE usuario_id=? AND data=?');
        $s->execute([$uid,$day]);
        if($s->fetchColumn())questionFlowFail('Dia ja finalizado.',409);
        if(count(array_filter($q['alternativas'],fn($a)=>$a['correta']))!==1)questionFlowFail('Gabarito indisponivel.',409);
        $correct=$chosen['letra']===$q['resposta_correta'];
        $s=$db->prepare('INSERT INTO respostas_usuario (usuario_id,questao_id,alternativa_id,data,resposta_marcada,correta,acertou)
            VALUES (?,?,?,?,?,?,?)');
        $s->execute([$uid,$id,$chosen['id'],$day,$chosen['letra'],(int)$correct,(int)$correct]);
        $db->commit();
        return ['ok'=>true,'reutilizado'=>false]+questionFeedback($q,['resposta_marcada'=>$chosen['letra'],'acertou'=>$correct]);
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}

function questionDailyFinish(PDO $db, int $uid, string $day, int $seconds): array {
    if($seconds<0 || $seconds>86400)questionFlowFail('Tempo invalido.',400);
    $db->beginTransaction();
    try {
        questionLockUser($db,$uid);
        $data=questionDailyLoad($db,$uid,$day);
        if(!$data['total'] || $data['respondidas']!==$data['total'])questionFlowFail('Responda todas as questoes antes de finalizar.',409);
        $reused=$data['finalizado'];
        $answers=(array)$data['respostas'];
        $hits=count(array_filter($answers,fn($a)=>$a['acertou']));
        if(!$reused) {
            $s=$db->prepare('INSERT INTO desempenho_diario (usuario_id,data,acertos,erros,tempo_seg,finalizado) VALUES (?,?,?,?,?,1)');
            $s->execute([$uid,$day,$hits,$data['total']-$hits,$seconds]);
        }
        $db->commit();
        return ['ok'=>true,'reutilizado'=>$reused,'acertos'=>$hits,'erros'=>$data['total']-$hits,
            'total'=>$data['total'],'tempo_seg'=>$reused?(int)$data['desempenho']['tempo_seg']:$seconds,'gabarito'=>$data['gabarito']];
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}

function questionReviewRows(PDO $db, int $uid): array {
    // Latest daily error only. Reviews never overwrite that educational evidence.
    $s=$db->prepare('SELECT q.id,q.materia,q.conteudo,q.dificuldade,q.enunciado,q.explicacao,
        r.resposta_marcada AS resposta_anterior,r.respondido_em AS ultima_errada
        FROM respostas_usuario r JOIN questoes q ON q.id=r.questao_id
        WHERE r.usuario_id=? AND r.acertou=0 AND '.questoesApprovedWhere('q').'
        AND NOT EXISTS (SELECT 1 FROM respostas_usuario n WHERE n.usuario_id=r.usuario_id AND n.questao_id=r.questao_id AND n.id>r.id)
        AND NOT EXISTS (SELECT 1 FROM questoes_respostas_usuario v
            WHERE v.usuario_id=r.usuario_id AND v.questao_id=r.questao_id AND v.correta=1 AND v.created_at>=r.respondido_em)
        ORDER BY r.id DESC');
    $s->execute([$uid]);
    return questoesAttachAlternativas($db,$s->fetchAll());
}

function questionReviewAnswer(PDO $db,int $uid,string $day,array $payload): array {
    $id=questionPositiveId($payload['questao_id']??null);
    $db->beginTransaction();
    try {
        questionLockUser($db,$uid);
        // Repeated attempts of the same day are stable, including after a correct review leaves the queue.
        $s=$db->prepare('SELECT a.letra AS resposta_marcada,v.correta AS acertou
            FROM questoes_respostas_usuario v JOIN questoes_alternativas a ON a.id=v.alternativa_id AND a.questao_id=v.questao_id
            WHERE v.usuario_id=? AND v.questao_id=? AND v.created_at>=? AND v.created_at<DATE_ADD(?,INTERVAL 1 DAY)
            ORDER BY v.id DESC LIMIT 1');
        $s->execute([$uid,$id,$day,$day]);$existing=$s->fetch();
        $q=null;
        if($existing) {
            $s=$db->prepare('SELECT id,materia,conteudo,enunciado,explicacao FROM questoes WHERE id=? AND '.questoesApprovedWhere('questoes'));
            $s->execute([$id]);$rows=questoesAttachAlternativas($db,$s->fetchAll());$q=$rows[0]??null;
        } else foreach(questionReviewRows($db,$uid) as $row)if((int)$row['id']===$id)$q=$row;
        if(!$q)questionFlowFail('Questao nao esta disponivel para sua revisao.',404);
        $chosen=questionChosenAlternative($q,$payload);
        if($existing) {
            if($existing['resposta_marcada']!==$chosen['letra'])questionFlowFail('Revisao ja respondida hoje; tentativa original preservada.',409);
        } else {
            $correct=$chosen['letra']===$q['resposta_correta'];
            $s=$db->prepare('INSERT INTO questoes_respostas_usuario (usuario_id,questao_id,alternativa_id,correta) VALUES (?,?,?,?)');
            $s->execute([$uid,$id,$chosen['id'],(int)$correct]);
            $existing=['resposta_marcada'=>$chosen['letra'],'acertou'=>$correct];
        }
        $db->commit();
        return ['ok'=>true]+questionFeedback($q,$existing);
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}

function questionReviewFeedback(PDO $db, int $uid, string $day, array $rows): array {
    $s=$db->prepare('SELECT v.questao_id,a.letra AS resposta_marcada,v.correta AS acertou
        FROM questoes_respostas_usuario v JOIN questoes_alternativas a ON a.id=v.alternativa_id AND a.questao_id=v.questao_id
        WHERE v.usuario_id=? AND v.created_at>=? AND v.created_at<DATE_ADD(?,INTERVAL 1 DAY) ORDER BY v.id');
    $s->execute([$uid,$day,$day]);$stored=[];$result=[];
    foreach($s->fetchAll() as $a)$stored[$a['questao_id']]=$a;
    foreach($rows as $q)if(isset($stored[$q['id']]))$result[$q['id']]=questionFeedback($q,$stored[$q['id']]);
    return $result;
}
