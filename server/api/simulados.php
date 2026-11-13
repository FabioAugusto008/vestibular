<?php
require_once __DIR__.'/../services/simulationRecovery.php';
requireLogin();$db=getDB();$uid=currentUserId();
try {
    [$action,$p]=questionFlowAction(['GET'=>['listar','carregar','historico'],'POST'=>['iniciar','responder','finalizar']]);
    beginDashboardReadOnlyGet($db,['listar','carregar','historico']);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')validateCsrfToken();
    switch($action){
        case 'listar':jsonResponse(['ok'=>true,'simulados'=>examClassicList($db,$uid)]);
        case 'iniciar':jsonResponse(examClassicStart($db,$uid,questionPositiveId($p['simulado_id']??null)));
        case 'carregar':jsonResponse(examClassicMap($db,examClassicRow($db,$uid,questionPositiveId($_GET['tentativa_id']??null))));
        case 'responder':jsonResponse(examClassicAnswer($db,$uid,$p));
        case 'finalizar':jsonResponse(examClassicFinish($db,$uid,$p));
        case 'historico':
            $s=$db->prepare("SELECT t.id,t.simulado_id,t.iniciado_em,t.finalizado_em,t.tempo_gasto_seg,t.acertos,t.erros,t.status,s.titulo,
                ROUND(100*t.acertos/NULLIF(t.acertos+t.erros,0),1) percentual FROM simulado_tentativas t JOIN simulados s ON s.id=t.simulado_id
                WHERE t.usuario_id=? AND t.status='finalizado' ORDER BY t.id DESC LIMIT 20");$s->execute([$uid]);
            jsonResponse(['ok'=>true,'historico'=>$s->fetchAll()]);
    }
}catch(QuestionFlowError $e){if($db->inTransaction())$db->rollBack();jsonResponse(['erro'=>$e->getMessage()],$e->getCode());}
catch(Throwable $e){if($db->inTransaction())$db->rollBack();logTechnicalError('simulados',$e);jsonResponse(['erro'=>'Nao foi possivel processar o simulado agora.'],500);}
