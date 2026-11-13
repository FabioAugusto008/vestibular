<?php
require_once __DIR__.'/../services/simulationRecovery.php';
requireLogin();$db=getDB();$uid=currentUserId();
try {
    [$action,$p]=questionFlowAction(['GET'=>['listar','carregar'],'POST'=>['gerar_para_tarefa','iniciar','responder','finalizar']]);
    beginDashboardReadOnlyGet($db,['listar','carregar']);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')validateCsrfToken();
    switch($action){
        case 'listar':
            $s=$db->prepare("SELECT e.id FROM simulados_planejados e WHERE e.usuario_id=? AND e.tarefa_id IS NOT NULL AND e.status<>'arquivado'
                AND (e.status='finalizado' OR EXISTS (SELECT 1 FROM planos_estudo p WHERE p.id=e.plano_id AND p.usuario_id=e.usuario_id AND p.status='ativo')) ORDER BY e.id DESC LIMIT 80");$s->execute([$uid]);
            jsonResponse(['ok'=>true,'simulados'=>array_map(fn($id)=>examPlannedMap($db,examPlannedRow($db,$uid,(int)$id)),$s->fetchAll(PDO::FETCH_COLUMN))]);
        case 'carregar':jsonResponse(['ok'=>true,'simulado'=>examPlannedMap($db,examPlannedRow($db,$uid,questionPositiveId($_GET['id']??null)))]);
        case 'gerar_para_tarefa':jsonResponse(examPlannedCreate($db,$uid,questionPositiveId($p['tarefa_id']??null)));
        case 'iniciar':jsonResponse(examPlannedStart($db,$uid,questionPositiveId($p['simulado_id']??null)));
        case 'responder':jsonResponse(examPlannedAnswer($db,$uid,$p));
        case 'finalizar':jsonResponse(examPlannedFinish($db,$uid,questionPositiveId($p['simulado_id']??null)));
    }
}catch(QuestionFlowError $e){if($db->inTransaction())$db->rollBack();jsonResponse(['erro'=>$e->getMessage()],$e->getCode());}
catch(Throwable $e){if($db->inTransaction())$db->rollBack();logTechnicalError('simulados',$e);jsonResponse(['erro'=>'Nao foi possivel processar o simulado agora.'],500);}
