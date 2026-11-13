<?php
// Planned practice has its own lot/answer tables. Shared validation performs no daily writes.
require_once __DIR__.'/dailyQuestions.php';

function exerciciosParseJson($raw): array {
    $value=json_decode((string)$raw,true);
    if(!is_array($value))questionFlowFail('Dados do lote indisponiveis.',409);
    return $value;
}
function exerciciosWeekStart(?string $date=null): string {
    $date=$date??date('Y-m-d');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$d||$d->format('Y-m-d')!==$date)questionFlowFail('Data invalida.',400);
    return $d->modify('monday this week')->format('Y-m-d');
}
function exerciciosLoadTarefa(PDO $db,int $uid,int $id): array {
    $s=$db->prepare("SELECT t.* FROM tarefas_estudo t JOIN planos_estudo p ON p.id=t.plano_id AND p.usuario_id=t.usuario_id
        WHERE t.id=? AND t.usuario_id=? AND p.status='ativo' AND t.tipo='questoes' AND t.status<>'cancelada'");
    $s->execute([$id,$uid]);$task=$s->fetch();
    if(!$task)questionFlowFail('Tarefa de exercicios indisponivel.',404);
    if($task['item_id']) {
        $s=$db->prepare("SELECT id FROM plano_estudo_itens WHERE id=? AND usuario_id=? AND plano_id=? AND tipo_atividade='questoes'");
        $s->execute([$task['item_id'],$uid,$task['plano_id']]);
        if(!$s->fetchColumn())questionFlowFail('Vinculo da tarefa invalido.',409);
    }
    return $task;
}
function exerciciosQuantidadeFromPerfil(PDO $db,int $uid): int {
    $s=$db->prepare('SELECT perfil_json FROM estudo_perfis WHERE usuario_id=?');$s->execute([$uid]);
    $profile=exerciciosParseJson($s->fetchColumn());
    return match($profile['exercicios_dia']??$profile['perfil_avancado']['exercicios_dia']??'6_10'){
        '3_5'=>5,'11_20'=>12,'mais_20'=>15,default=>8};
}
function exerciciosSelect(PDO $db,array $task,int $requested): array {
    if($requested<1||$requested>20)questionFlowFail('Quantidade invalida.',400);
    $meta=$task['metadata_json']?exerciciosParseJson($task['metadata_json']):[];
    $level=$meta['dificuldade']??'misto';
    if(!is_string($level)||!in_array($level,['misto','facil','medio','dificil'],true))questionFlowFail('Dificuldade invalida.',409);
    $sql="SELECT q.* FROM questoes q JOIN questoes_alternativas a ON a.questao_id=q.id
        WHERE ".questoesApprovedWhere('q')." AND q.materia=? AND TRIM(q.enunciado)<>'' AND TRIM(COALESCE(q.explicacao,''))<>''";
    $params=[$task['materia']];
    if(trim((string)$task['conteudo'])!==''){$sql.=' AND q.conteudo=?';$params[]=$task['conteudo'];}
    if($level!=='misto'){$sql.=' AND q.dificuldade=?';$params[]=$level;}
    $sql.=" GROUP BY q.id HAVING COUNT(*)=5 AND SUM(a.correta)=1 AND SUM(TRIM(a.texto)='')=0
        AND COUNT(DISTINCT a.letra)=5 AND SUM(a.letra IN ('A','B','C','D','E'))=5 ORDER BY RAND() LIMIT ".$requested;
    $s=$db->prepare($sql);$s->execute($params);
    return ['questoes'=>questoesAttachAlternativas($db,$s->fetchAll()),'nivel'=>$level,'solicitada'=>$requested];
}
function exerciciosQuestionToItem(array $q): array {
    $choices=[];$ids=[];
    foreach($q['alternativas'] as $a){$choices[$a['letra']]=$a['texto'];$ids[$a['letra']]=$a['id'];}
    return ['id'=>'q'.$q['id'],'questao_id'=>(int)$q['id'],'tipo'=>'multipla_escolha','materia'=>$q['materia'],
        'conteudo'=>$q['conteudo'],'dificuldade'=>$q['dificuldade'],'fonte'=>$q['fonte'],'pergunta'=>$q['enunciado'],
        'alternativas'=>$choices,'alternativa_ids'=>$ids,'resposta_correta'=>$q['resposta_correta'],'explicacao'=>$q['explicacao']];
}
function exerciciosPayload(array $row): array {
    $p=exerciciosParseJson($row['exercicios_json']);$items=$p['exercicios']??[];
    if(!is_array($items)||!$items||count($items)!==(int)$row['quantidade']
        ||count(array_unique(array_column($items,'id')))!==count($items))questionFlowFail('Lote inconsistente.',409);
    return $p;
}
function exerciciosMapLote(PDO $db,array $row): array {
    $p=exerciciosPayload($row);$s=$db->prepare('SELECT exercise_key,resposta_usuario,resposta_marcada,acertou,avaliacao_json,motivo_erro,respondido_em
        FROM respostas_exercicios_planejados WHERE exercicio_planejado_id=? AND usuario_id=? ORDER BY id');
    $s->execute([$row['id'],$row['usuario_id']]);$answers=[];$hits=0;
    foreach($s->fetchAll() as $a) {
        $hits+=(int)$a['acertou'];
        $answers[$a['exercise_key']]=['resposta_usuario'=>$a['resposta_usuario'],'resposta_marcada'=>$a['resposta_marcada'],
            'acertou'=>(bool)$a['acertou'],'avaliacao'=>exerciciosParseJson($a['avaliacao_json']),
            'motivo_erro'=>$a['motivo_erro'],'respondido_em'=>$a['respondido_em']];
    }
    $items=$p['exercicios'];
    foreach($items as &$item){unset($item['resposta_correta']);if(!isset($answers[$item['id']]))unset($item['explicacao']);}
    unset($item);
    $s=$db->prepare('SELECT status FROM tarefas_estudo WHERE id=? AND usuario_id=? AND plano_id=?');
    $s->execute([$row['tarefa_id'],$row['usuario_id'],$row['plano_id']]);$taskStatus=$s->fetchColumn();
    $total=(int)$row['quantidade'];$answered=count($answers);
    return ['id'=>(int)$row['id'],'plano_id'=>(int)$row['plano_id'],'tarefa_id'=>(int)$row['tarefa_id'],
        'semana_inicio'=>$row['semana_inicio'],'materia'=>$row['materia'],'conteudo'=>$row['conteudo'],'nivel'=>$row['nivel'],
        'quantidade'=>$total,'quantidade_solicitada'=>(int)$p['quantidade_solicitada'],'insuficiente'=>$total<(int)$p['quantidade_solicitada'],
        'origem'=>$row['origem'],'status'=>$row['status'],'aviso'=>$p['aviso'],'exercicios'=>$items,'respostas'=>(object)$answers,
        'resumo'=>['respondidas'=>$answered,'total'=>$total,'acertos'=>$hits,'erros'=>$answered-$hits,
            'percentual'=>$answered?round(100*$hits/$answered,1):0,'concluido'=>$answered===$total,'tarefa_concluida'=>$taskStatus==='concluida']];
}
function exerciciosFindRow(PDO $db,int $uid,int $taskId): ?array {
    $s=$db->prepare("SELECT * FROM exercicios_planejados WHERE usuario_id=? AND tarefa_id=? AND status IN ('ativo','concluido') ORDER BY id DESC LIMIT 1");
    $s->execute([$uid,$taskId]);return $s->fetch()?:null;
}
function exerciciosCreateForTarefa(PDO $db,int $uid,int $taskId): array {
    $db->beginTransaction();
    try {
        questionLockUser($db,$uid);$task=exerciciosLoadTarefa($db,$uid,$taskId);
        $row=exerciciosFindRow($db,$uid,$taskId);
        if($row){$result=exerciciosMapLote($db,$row);$db->commit();return ['ok'=>true,'lote'=>$result,'reutilizado'=>true];}
        if($task['status']==='concluida')questionFlowFail('Tarefa ja concluida; nao sera criado outro lote.',409);
        $n=exerciciosQuantidadeFromPerfil($db,$uid);$selected=exerciciosSelect($db,$task,$n);$qs=$selected['questoes'];$actual=count($qs);
        if(!$actual)questionFlowFail('Nenhuma questao elegivel para os filtros desta tarefa. Nada foi gravado.',409);
        $notice=$actual<$n?"Disponiveis {$actual} de {$n} questoes solicitadas. O lote e parcial; a tarefa permanecera em andamento.":null;
        $p=['quantidade_solicitada'=>$n,'aviso'=>$notice,'escopo'=>$task['conteudo']?'conteudo':'materia','exercicios'=>array_map('exerciciosQuestionToItem',$qs)];
        $s=$db->prepare("INSERT INTO exercicios_planejados (usuario_id,plano_id,tarefa_id,semana_inicio,materia,conteudo,nivel,quantidade,exercicios_json,origem,status)
            VALUES (?,?,?,?,?,?,?,?,?,'banco','ativo')");
        $s->execute([$uid,$task['plano_id'],$taskId,exerciciosWeekStart($task['data_prevista']),$task['materia'],$task['conteudo'],$selected['nivel'],$actual,jsonEncodeSafe($p)]);
        exerciciosSyncTask($db,$uid,$task,false);
        $row=exerciciosFindRow($db,$uid,$taskId);$result=exerciciosMapLote($db,$row);
        $db->commit();
        return ['ok'=>true,'lote'=>$result,'reutilizado'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function exerciciosSyncTask(PDO $db,int $uid,array $task,bool $done): void {
    $status=$done?'concluida':'em_andamento';
    $s=$db->prepare('UPDATE tarefas_estudo SET status=?,concluida_em='.($done?'CURRENT_TIMESTAMP':'NULL').',atualizada_em=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=? AND plano_id=?');
    $s->execute([$status,$task['id'],$uid,$task['plano_id']]);
    if($task['item_id']){
        $s=$db->prepare('UPDATE plano_estudo_itens SET status=?,atualizado_em=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=? AND plano_id=?');
        $s->execute([$status,$task['item_id'],$uid,$task['plano_id']]);
    }
    if($done){
        $s=$db->prepare("UPDATE eventos_calendario_estudai SET status='concluido',atualizado_em=CURRENT_TIMESTAMP WHERE tarefa_id=? AND usuario_id=? AND plano_id=?");
        $s->execute([$task['id'],$uid,$task['plano_id']]);
    }
}
function exerciciosAnswer(PDO $db,int $uid,array $payload): array {
    $id=questionPositiveId($payload['exercicio_planejado_id']??null);
    $key=$payload['exercise_key']??'';
    if(!is_string($key)||!preg_match('/^q[1-9][0-9]{0,9}$/D',$key))questionFlowFail('Questao invalida.',400);
    $db->beginTransaction();
    try {
        questionLockUser($db,$uid);
        $s=$db->prepare('SELECT * FROM exercicios_planejados WHERE id=? AND usuario_id=?');
        $s->execute([$id,$uid]);$row=$s->fetch();
        if(!$row||!in_array($row['status'],['ativo','concluido'],true))questionFlowFail('Lote indisponivel.',404);
        $task=exerciciosLoadTarefa($db,$uid,(int)$row['tarefa_id']);
        if((int)$task['plano_id']!==(int)$row['plano_id'])questionFlowFail('Vinculo do lote invalido.',409);
        $p=exerciciosPayload($row);$item=null;
        foreach($p['exercicios'] as $q)if($q['id']===$key)$item=$q;
        if(!$item)questionFlowFail('Questao nao pertence ao lote.',404);
        if(isset($payload['questao_id'])&&questionPositiveId($payload['questao_id'])!==$item['questao_id'])questionFlowFail('Questao nao corresponde ao lote.',400);
        $letter=$payload['resposta_marcada']??null;
        if(isset($payload['alternativa_id'])){
            $alternative=questionPositiveId($payload['alternativa_id']);$mapped=array_search($alternative,$item['alternativa_ids'],true);
            if($mapped===false||($letter!==null&&$letter!==$mapped))questionFlowFail('Alternativa nao pertence a questao.',400);
            $letter=$mapped;
        }
        if(!is_string($letter)||!preg_match('/^[A-E]$/D',$letter)||!isset($item['alternativas'][$letter]))questionFlowFail('Alternativa invalida.',400);
        $s=$db->prepare('SELECT resposta_marcada,acertou,avaliacao_json FROM respostas_exercicios_planejados WHERE usuario_id=? AND exercicio_planejado_id=? AND exercise_key=?');
        $s->execute([$uid,$id,$key]);$previous=$s->fetch();
        if($previous){
            if($previous['resposta_marcada']!==$letter)questionFlowFail('Resposta ja registrada; tentativa original preservada.',409);
            $db->commit();return ['ok'=>true,'reutilizado'=>true,'acertou'=>(bool)$previous['acertou'],'avaliacao'=>exerciciosParseJson($previous['avaliacao_json'])];
        }
        if($row['status']!=='ativo'||$task['status']==='concluida')questionFlowFail('Lote ou tarefa ja concluido.',409);
        $correct=$letter===$item['resposta_correta'];
        $feedback=['tipo'=>'multipla_escolha','correta'=>$item['resposta_correta'],'explicacao'=>$item['explicacao'],'avaliado_por'=>'base_questoes'];
        $reason=$payload['motivo_erro']??null;
        if(!is_string($reason)||!in_array($reason,['nao_sabia','atencao','calculo','interpretacao','duvida','chutei'],true)||$correct)$reason=null;
        $s=$db->prepare('INSERT INTO respostas_exercicios_planejados (usuario_id,exercicio_planejado_id,exercise_key,resposta_usuario,resposta_marcada,acertou,avaliacao_json,motivo_erro) VALUES (?,?,?,?,?,?,?,?)');
        $s->execute([$uid,$id,$key,'',$letter,(int)$correct,jsonEncodeSafe($feedback),$reason]);
        $s=$db->prepare('SELECT COUNT(*) FROM respostas_exercicios_planejados WHERE exercicio_planejado_id=? AND usuario_id=?');$s->execute([$id,$uid]);
        if((int)$s->fetchColumn()===(int)$row['quantidade']){
            $s=$db->prepare("UPDATE exercicios_planejados SET status='concluido',atualizado_em=CURRENT_TIMESTAMP WHERE id=? AND usuario_id=?");
            $s->execute([$id,$uid]);
            if((int)$row['quantidade']>=(int)$p['quantidade_solicitada'])exerciciosSyncTask($db,$uid,$task,true);
        }
        $db->commit();return ['ok'=>true,'reutilizado'=>false,'acertou'=>$correct,'avaliacao'=>$feedback];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
