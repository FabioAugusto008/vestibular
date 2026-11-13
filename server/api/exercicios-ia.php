<?php
require_once __DIR__.'/../services/plannedExercises.php';
requireLogin();$db=getDB();$uid=currentUserId();
try {
    [$action,$payload]=questionFlowAction(['GET'=>['carregar_por_tarefa','carregar_semana'],'POST'=>['gerar_por_tarefa','responder']]);
    beginDashboardReadOnlyGet($db,['carregar_por_tarefa','carregar_semana']);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')validateCsrfToken();
    switch($action){
        case 'carregar_por_tarefa':
            $taskId=questionPositiveId($_GET['tarefa_id']??null);exerciciosLoadTarefa($db,$uid,$taskId);
            $row=exerciciosFindRow($db,$uid,$taskId);
            jsonResponse(['ok'=>true,'lote'=>$row?exerciciosMapLote($db,$row):null]);
        case 'gerar_por_tarefa':jsonResponse(exerciciosCreateForTarefa($db,$uid,questionPositiveId($payload['tarefa_id']??null)));
        case 'responder':jsonResponse(exerciciosAnswer($db,$uid,$payload));
        case 'carregar_semana':
            $date=$_GET['semana_inicio']??null;
            if($date!==null&&!is_string($date))questionFlowFail('Data invalida.',400);
            $week=exerciciosWeekStart($date);
            $s=$db->prepare("SELECT e.* FROM exercicios_planejados e
                JOIN tarefas_estudo t ON t.id=e.tarefa_id AND t.usuario_id=e.usuario_id AND t.plano_id=e.plano_id
                JOIN planos_estudo p ON p.id=e.plano_id AND p.usuario_id=e.usuario_id
                WHERE e.usuario_id=? AND e.semana_inicio=? AND e.status IN ('ativo','concluido') AND p.status='ativo' AND t.status<>'cancelada' ORDER BY e.id");
            $s->execute([$uid,$week]);
            jsonResponse(['ok'=>true,'semana_inicio'=>$week,'lotes'=>array_map(fn($r)=>exerciciosMapLote($db,$r),$s->fetchAll())]);
    }
}catch(QuestionFlowError $e){
    if($db->inTransaction())$db->rollBack();jsonResponse(['erro'=>$e->getMessage()],$e->getCode());
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();logTechnicalError('exercicios',$e);jsonResponse(['erro'=>'Nao foi possivel processar exercicios agora.'],500);
}
