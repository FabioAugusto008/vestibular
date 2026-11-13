<?php
require_once __DIR__.'/plannedExercises.php';

function examTx(PDO $db,int $uid,callable $fn): array {
    $db->beginTransaction();
    try{questionLockUser($db,$uid);$out=$fn();$db->commit();return $out;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function examQuestionsValid(array $rows): bool {
    if(!$rows||count(array_unique(array_column($rows,'id')))!==count($rows))return false;
    foreach($rows as $q)if(count($q['alternativas'])!==5||count(array_filter($q['alternativas'],fn($a)=>$a['correta']))!==1||trim($q['enunciado'])==='')return false;
    return true;
}
function examClassicCatalog(PDO $db,int $id,bool $requireActive=true): array {
    $s=$db->prepare('SELECT * FROM simulados WHERE id=?'.($requireActive?' AND ativo=1':''));$s->execute([$id]);$sim=$s->fetch();
    if(!$sim)questionFlowFail('Simulado indisponivel.',404);
    $s=$db->prepare('SELECT q.* FROM simulado_questoes x JOIN questoes q ON q.id=x.questao_id WHERE x.simulado_id=? AND '.questoesApprovedWhere('q').' ORDER BY x.ordem,x.id');
    $s->execute([$id]);$rows=questoesAttachAlternativas($db,$s->fetchAll());
    if(count($rows)!==(int)$sim['total_questoes']||!examQuestionsValid($rows))questionFlowFail('Questoes insuficientes para a quantidade obrigatoria deste simulado. Nenhuma tentativa criada.',409);
    return [$sim,$rows];
}
function examClassicRow(PDO $db,int $uid,int $id): array {
    $s=$db->prepare('SELECT * FROM simulado_tentativas WHERE id=? AND usuario_id=?');$s->execute([$id,$uid]);$row=$s->fetch();
    if(!$row)questionFlowFail('Tentativa indisponivel.',404);
    if(!in_array($row['status'],['em_andamento','finalizado'],true))questionFlowFail('Tentativa encerrada.',409);
    return $row;
}
function examClassicAnswers(PDO $db,int $id): array {
    $s=$db->prepare('SELECT questao_id,resposta_marcada,acertou FROM simulado_respostas WHERE tentativa_id=? ORDER BY id');$s->execute([$id]);$out=[];
    foreach($s->fetchAll() as $a)$out[$a['questao_id']]=$a;
    return $out;
}
function examScore(array $questions,array $answers): array {
    $hits=0;$key=[];$subjects=[];
    foreach($questions as $q){
        $a=$answers[$q['id']]??null;$correct=$a&&(bool)$a['acertou'];$hits+=(int)$correct;
        $key[$q['id']]=['correta'=>$q['resposta_correta'],'explicacao'=>$q['explicacao']];
        $subject=$q['materia'];$subjects[$subject]??=['total'=>0,'acertos'=>0,'erros'=>0];
        $subjects[$subject]['total']++;$subjects[$subject]['acertos']+=(int)$correct;$subjects[$subject]['erros']+=(int)!$correct;
    }
    $total=count($questions);
    return ['total'=>$total,'respondidas'=>count($answers),'acertos'=>$hits,'erros'=>$total-$hits,'percentual'=>$total?round(100*$hits/$total,1):0,'gabarito'=>(object)$key,'por_materia'=>$subjects];
}
function examClassicMap(PDO $db,array $row): array {
    $final=$row['status']==='finalizado';[$sim,$qs]=examClassicCatalog($db,(int)$row['simulado_id'],!$final);$a=examClassicAnswers($db,(int)$row['id']);$public=[];
    foreach($a as $id=>$answer){$public[$id]=['marcada'=>$answer['resposta_marcada']];if($final)$public[$id]['acertou']=(bool)$answer['acertou'];}
    $score=$final?examScore($qs,$a):null;
    return ['ok'=>true,'tentativa_id'=>(int)$row['id'],'status'=>$row['status'],'finalizado'=>$final,
        'simulado'=>['id'=>(int)$sim['id'],'titulo'=>$sim['titulo'],'duracao_min'=>(int)$sim['duracao_min'],'total_questoes'=>count($qs)],
        'questoes'=>questionPublicRows($qs,$final?array_fill_keys(array_column($qs,'id'),true):[]),
        'respostas'=>(object)$public,'respondidas'=>count($a),'gabarito'=>$final?$score['gabarito']:null,
        'resultado'=>$score,'tempo_seg'=>(int)$row['tempo_gasto_seg']];
}
function examClassicStart(PDO $db,int $uid,int $id): array {
    return examTx($db,$uid,function()use($db,$uid,$id){
        examClassicCatalog($db,$id); // Validate before inserting any attempt.
        $s=$db->prepare("SELECT * FROM simulado_tentativas WHERE usuario_id=? AND simulado_id=? AND status IN ('em_andamento','finalizado') ORDER BY id DESC LIMIT 1");
        $s->execute([$uid,$id]);$row=$s->fetch();$reused=(bool)$row;
        if(!$row){$s=$db->prepare('INSERT INTO simulado_tentativas (usuario_id,simulado_id) VALUES (?,?)');$s->execute([$uid,$id]);$row=examClassicRow($db,$uid,(int)$db->lastInsertId());}
        return examClassicMap($db,$row)+['reutilizado'=>$reused];
    });
}
function examClassicAnswer(PDO $db,int $uid,array $p): array {
    $id=questionPositiveId($p['tentativa_id']??null);$qid=questionPositiveId($p['questao_id']??null);
    return examTx($db,$uid,function()use($db,$uid,$id,$qid,$p){
        $row=examClassicRow($db,$uid,$id);if($row['status']!=='em_andamento')questionFlowFail('Tentativa finalizada; resposta bloqueada.',409);
        [, $qs]=examClassicCatalog($db,(int)$row['simulado_id']);$q=null;foreach($qs as $item)if((int)$item['id']===$qid)$q=$item;
        if(!$q)questionFlowFail('Questao fora do simulado.',404);
        $chosen=questionChosenAlternative($q,$p);$right=$chosen['letra']===$q['resposta_correta'];
        $existing=examClassicAnswers($db,$id)[$qid]??null;
        if($existing&&$existing['resposta_marcada']===$chosen['letra'])return ['ok'=>true,'reutilizado'=>true];
        if($existing){
            $s=$db->prepare('UPDATE simulado_respostas SET resposta_marcada=?,acertou=?,respondido_em=CURRENT_TIMESTAMP WHERE tentativa_id=? AND questao_id=?');
            $s->execute([$chosen['letra'],(int)$right,$id,$qid]);
        }else{$s=$db->prepare('INSERT INTO simulado_respostas (tentativa_id,questao_id,resposta_marcada,acertou) VALUES (?,?,?,?)');$s->execute([$id,$qid,$chosen['letra'],(int)$right]);}
        return ['ok'=>true,'reutilizado'=>false]; // Never disclose correction during an exam.
    });
}
function examClassicFinish(PDO $db,int $uid,array $p): array {
    $id=questionPositiveId($p['tentativa_id']??null);$time=$p['tempo_seg']??0;
    if(filter_var($time,FILTER_VALIDATE_INT)===false||$time<0||$time>86400)questionFlowFail('Tempo invalido.',400);
    return examTx($db,$uid,function()use($db,$uid,$id,$time){
        $row=examClassicRow($db,$uid,$id);$data=examClassicMap($db,$row);$reused=$data['finalizado'];
        if(!$reused){
            if($data['respondidas']!==count($data['questoes']))questionFlowFail('Responda todas as questoes antes de finalizar.',409);
            [, $qs]=examClassicCatalog($db,(int)$row['simulado_id']);$score=examScore($qs,examClassicAnswers($db,$id));
            $s=$db->prepare("UPDATE simulado_tentativas SET status='finalizado',finalizado_em=CURRENT_TIMESTAMP,tempo_gasto_seg=?,acertos=?,erros=? WHERE id=? AND usuario_id=?");
            $s->execute([$time,$score['acertos'],$score['erros'],$id,$uid]);$data=examClassicMap($db,examClassicRow($db,$uid,$id));
        }
        return ['ok'=>true,'reutilizado'=>$reused,'tempo_seg'=>$data['tempo_seg']]+$data['resultado'];
    });
}
function examClassicList(PDO $db,int $uid): array {
    $s=$db->prepare("SELECT s.id,s.titulo,s.descricao,s.duracao_min,s.total_questoes,
        (SELECT COUNT(*) FROM simulado_tentativas t WHERE t.usuario_id=? AND t.simulado_id=s.id AND t.status='finalizado') tentativas,
        (SELECT MAX(ROUND(100*t.acertos/NULLIF(t.acertos+t.erros,0),1)) FROM simulado_tentativas t WHERE t.usuario_id=? AND t.simulado_id=s.id AND t.status='finalizado') melhor_nota
        FROM simulados s WHERE s.ativo=1 ORDER BY s.id");$s->execute([$uid,$uid]);$rows=$s->fetchAll();
    foreach($rows as &$row){
        $count=$db->prepare('SELECT COUNT(*) FROM simulado_questoes x JOIN questoes q ON q.id=x.questao_id WHERE x.simulado_id=? AND '.questoesApprovedWhere('q'));
        $count->execute([$row['id']]);$row['questoes_disponiveis']=(int)$count->fetchColumn();
        $row['disponivel']=$row['questoes_disponiveis']===(int)$row['total_questoes']&&$row['questoes_disponiveis']>0;
    }unset($row);return $rows;
}

function examPlannedTask(PDO $db,int $uid,int $id,bool $requireActive=true): array {
    $s=$db->prepare("SELECT t.* FROM tarefas_estudo t JOIN planos_estudo p ON p.id=t.plano_id AND p.usuario_id=t.usuario_id
        WHERE t.id=? AND t.usuario_id=? AND t.tipo='simulado'".($requireActive?" AND t.status<>'cancelada' AND p.status='ativo'":""));
    $s->execute([$id,$uid]);$task=$s->fetch();if(!$task)questionFlowFail('Tarefa de simulado indisponivel.',404);
    if($task['item_id']){$s=$db->prepare("SELECT id FROM plano_estudo_itens WHERE id=? AND usuario_id=? AND plano_id=? AND tipo_atividade='simulado'");$s->execute([$task['item_id'],$uid,$task['plano_id']]);if(!$s->fetchColumn())questionFlowFail('Vinculo invalido.',409);}
    return $task;
}
function examSelect(PDO $db,array $task,int $quantity=10): array {
    if($quantity<1||$quantity>40)questionFlowFail('Quantidade invalida.',400);
    $sql='SELECT q.* FROM questoes q WHERE '.questoesApprovedWhere('q');$params=[];
    if(trim((string)$task['materia'])!==''){$sql.=' AND q.materia=?';$params[]=$task['materia'];}
    if(trim((string)$task['conteudo'])!==''){$sql.=' AND q.conteudo=?';$params[]=$task['conteudo'];}
    $meta=$task['metadata_json']?exerciciosParseJson($task['metadata_json']):[];$level=$meta['dificuldade']??'misto';
    if(!in_array($level,['misto','facil','medio','dificil'],true))questionFlowFail('Dificuldade invalida.',409);
    if($level!=='misto'){$sql.=' AND q.dificuldade=?';$params[]=$level;}
    $sql.=' ORDER BY q.id';$s=$db->prepare($sql);$s->execute($params);
    $rows=questoesAttachAlternativas($db,$s->fetchAll());
    return array_slice(array_values(array_filter($rows,fn($q)=>examQuestionsValid([$q]))),0,$quantity);
}
function examPlannedRow(PDO $db,int $uid,int $id): array {
    $s=$db->prepare('SELECT * FROM simulados_planejados WHERE id=? AND usuario_id=?');$s->execute([$id,$uid]);$r=$s->fetch();
    if(!$r)questionFlowFail('Simulado planejado indisponivel.',404);
    if(!in_array($r['status'],['bloqueado','liberado','iniciado','finalizado'],true))questionFlowFail('Simulado encerrado.',409);
    $task=examPlannedTask($db,$uid,(int)$r['tarefa_id'],$r['status']!=='finalizado');if((int)$task['plano_id']!==(int)$r['plano_id'])questionFlowFail('Vinculo do simulado invalido.',409);
    return $r;
}
function examPlannedPayload(array $row): array {
    $p=exerciciosParseJson($row['questoes_json']);$qs=$p['questoes']??[];
    if(!$qs||count($qs)!==count(array_unique(array_column($qs,'id'))))questionFlowFail('Simulado inconsistente.',409);
    return $p;
}
function examPlannedAnswers(PDO $db,int $uid,int $id): array {
    $s=$db->prepare('SELECT question_key,resposta_marcada,acertou,avaliacao_json FROM simulados_planejados_respostas WHERE usuario_id=? AND simulado_planejado_id=? ORDER BY id');
    $s->execute([$uid,$id]);$out=[];foreach($s->fetchAll() as $a)$out[$a['question_key']]=$a;return $out;
}
function examPlannedMap(PDO $db,array $r): array {
    $p=examPlannedPayload($r);$qs=$p['questoes'];$a=examPlannedAnswers($db,(int)$r['usuario_id'],(int)$r['id']);$final=$r['status']==='finalizado';$answers=[];
    foreach($a as $k=>$v){$answers[$k]=['resposta_marcada'=>$v['resposta_marcada']];if($final)$answers[$k]+=['acertou'=>(bool)$v['acertou'],'avaliacao'=>exerciciosParseJson($v['avaliacao_json'])];}
    $score=$final?examScore($qs,$a):null;
    foreach($qs as &$q){unset($q['resposta_correta']);if(!$final)unset($q['explicacao']);}unset($q);
    $status=$r['status']==='bloqueado'&&$r['data_liberacao']<=date('Y-m-d')?'liberado':$r['status'];
    return ['id'=>(int)$r['id'],'plano_id'=>(int)$r['plano_id'],'tarefa_id'=>(int)$r['tarefa_id'],'titulo'=>$r['titulo'],'descricao'=>$r['descricao'],
        'materia'=>$r['materia'],'conteudos'=>exerciciosParseJson($r['conteudos_json']??'[]'),'data_liberacao'=>$r['data_liberacao'],'status'=>$status,'origem'=>$r['origem'],
        'criado_em'=>$r['criado_em'],'atualizado_em'=>$r['atualizado_em'],
        'questoes'=>$qs,'respostas'=>(object)$answers,'resultado'=>$score,'aviso'=>$p['aviso'],'quantidade_solicitada'=>$p['quantidade_solicitada']];
}
function examPlannedCreate(PDO $db,int $uid,int $taskId): array {
    return examTx($db,$uid,function()use($db,$uid,$taskId){
        $task=examPlannedTask($db,$uid,$taskId);
        $s=$db->prepare("SELECT id FROM simulados_planejados WHERE usuario_id=? AND tarefa_id=? AND status<>'arquivado' ORDER BY id DESC LIMIT 1");$s->execute([$uid,$taskId]);$id=$s->fetchColumn();
        if($id)return ['ok'=>true,'reutilizado'=>true,'simulado'=>examPlannedMap($db,examPlannedRow($db,$uid,(int)$id))];
        if($task['status']==='concluida')questionFlowFail('Tarefa concluida sem simulado recuperavel.',409);
        $qs=examSelect($db,$task,10);if(count($qs)<5)questionFlowFail('Sao necessarias ao menos cinco questoes elegiveis; nenhum simulado criado.',409);
        $notice=count($qs)<10?'Simulado reduzido: '.count($qs).' de 10 questoes. A tarefa permanecera em andamento.':null;
        $p=['quantidade_solicitada'=>10,'aviso'=>$notice,'questoes'=>array_map('exerciciosQuestionToItem',$qs)];
        $s=$db->prepare("INSERT INTO simulados_planejados (usuario_id,plano_id,tarefa_id,titulo,descricao,materia,conteudos_json,questoes_json,data_liberacao,status,origem)
            VALUES (?,?,?,'Simulado planejado','Base local aprovada',?,? ,?,?,?,'banco')");
        $s->execute([$uid,$task['plano_id'],$taskId,$task['materia'],jsonEncodeSafe([$task['conteudo']]),jsonEncodeSafe($p),$task['data_prevista'],$task['data_prevista']<=date('Y-m-d')?'liberado':'bloqueado']);
        return ['ok'=>true,'reutilizado'=>false,'simulado'=>examPlannedMap($db,examPlannedRow($db,$uid,(int)$db->lastInsertId()))];
    });
}
function examPlannedStart(PDO $db,int $uid,int $id): array {
    return examTx($db,$uid,function()use($db,$uid,$id){
        $r=examPlannedRow($db,$uid,$id);$m=examPlannedMap($db,$r);
        if($m['status']==='bloqueado')questionFlowFail('Simulado ainda nao liberado.',403);
        if($m['status']==='liberado'){
            $s=$db->prepare("UPDATE simulados_planejados SET status='iniciado',atualizado_em=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=?");$s->execute([$id,$uid]);
            exerciciosSyncTask($db,$uid,examPlannedTask($db,$uid,(int)$r['tarefa_id']),false);
        }
        return ['ok'=>true,'simulado'=>examPlannedMap($db,examPlannedRow($db,$uid,$id))];
    });
}
function examPlannedAnswer(PDO $db,int $uid,array $p): array {
    $id=questionPositiveId($p['simulado_planejado_id']??null);$key=$p['question_key']??'';
    if(!is_string($key)||!preg_match('/^q[1-9][0-9]{0,9}$/D',$key))questionFlowFail('Questao invalida.',400);
    return examTx($db,$uid,function()use($db,$uid,$id,$key,$p){
        $r=examPlannedRow($db,$uid,$id);if($r['status']!=='iniciado')questionFlowFail('Simulado nao iniciado ou ja finalizado.',409);
        $payload=examPlannedPayload($r);$q=null;foreach($payload['questoes'] as $item)if($item['id']===$key)$q=$item;
        if(!$q)questionFlowFail('Questao fora do simulado.',404);
        $letter=$p['resposta_marcada']??null;
        if(isset($p['alternativa_id'])){$mapped=array_search(questionPositiveId($p['alternativa_id']),$q['alternativa_ids'],true);if($mapped===false||($letter!==null&&$letter!==$mapped))questionFlowFail('Alternativa de outra questao.',400);$letter=$mapped;}
        if(!is_string($letter)||!preg_match('/^[A-E]$/D',$letter))questionFlowFail('Alternativa invalida.',400);
        $right=$letter===$q['resposta_correta'];$feedback=['correta'=>$q['resposta_correta'],'explicacao'=>$q['explicacao']];
        $previous=examPlannedAnswers($db,$uid,$id)[$key]??null;
        if($previous&&$previous['resposta_marcada']===$letter)return ['ok'=>true,'reutilizado'=>true];
        if($previous){$s=$db->prepare('UPDATE simulados_planejados_respostas SET resposta_marcada=?,acertou=?,avaliacao_json=?,respondido_em=CURRENT_TIMESTAMP WHERE usuario_id=? AND simulado_planejado_id=? AND question_key=?');$s->execute([$letter,(int)$right,jsonEncodeSafe($feedback),$uid,$id,$key]);}
        else{$s=$db->prepare('INSERT INTO simulados_planejados_respostas (usuario_id,simulado_planejado_id,question_key,resposta_usuario,resposta_marcada,acertou,avaliacao_json) VALUES (?,?,?,\'\',?,?,?)');$s->execute([$uid,$id,$key,$letter,(int)$right,jsonEncodeSafe($feedback)]);}
        return ['ok'=>true,'reutilizado'=>false];
    });
}
function examPlannedFinish(PDO $db,int $uid,int $id): array {
    return examTx($db,$uid,function()use($db,$uid,$id){
        $r=examPlannedRow($db,$uid,$id);$reused=$r['status']==='finalizado';
        if(!$reused){
            if($r['status']!=='iniciado')questionFlowFail('Simulado nao iniciado.',409);
            $p=examPlannedPayload($r);$answers=examPlannedAnswers($db,$uid,$id);
            if(count($answers)!==count($p['questoes']))questionFlowFail('Responda todas as questoes antes de finalizar.',409);
            $s=$db->prepare("UPDATE simulados_planejados SET status='finalizado',atualizado_em=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=?");$s->execute([$id,$uid]);
            if(count($p['questoes'])>=$p['quantidade_solicitada'])exerciciosSyncTask($db,$uid,examPlannedTask($db,$uid,(int)$r['tarefa_id']),true);
        }
        $mapped=examPlannedMap($db,examPlannedRow($db,$uid,$id));
        return ['ok'=>true,'reutilizado'=>$reused,'simulado'=>$mapped,'resultado'=>$mapped['resultado']];
    });
}
