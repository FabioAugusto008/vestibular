<?php
require_once __DIR__ . '/../services/dailyQuestions.php';
requireLogin();
$db=getDB();$uid=currentUserId();$today=date('Y-m-d');
try {
    [$action,$payload]=questionFlowAction(['GET'=>['carregar'],'POST'=>['preparar','responder','finalizar']]);
    beginDashboardReadOnlyGet($db,['carregar']);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')validateCsrfToken();
    switch($action) {
        case 'carregar': jsonResponse(questionDailyLoad($db,$uid,$today));
        case 'preparar':
            $quantity=$payload['quantidade']??20;
            if(filter_var($quantity,FILTER_VALIDATE_INT)===false)questionFlowFail('Quantidade invalida.',400);
            jsonResponse(questionDailyPrepare($db,$today,(int)$quantity));
        case 'responder': jsonResponse(questionDailyAnswer($db,$uid,$today,$payload));
        case 'finalizar':
            $seconds=$payload['tempo_seg']??0;
            if(filter_var($seconds,FILTER_VALIDATE_INT)===false)questionFlowFail('Tempo invalido.',400);
            jsonResponse(questionDailyFinish($db,$uid,$today,(int)$seconds));
    }
} catch(QuestionFlowError $e) {
    if($db->inTransaction())$db->rollBack();
    jsonResponse(['erro'=>$e->getMessage()],$e->getCode());
} catch(Throwable $e) {
    if($db->inTransaction())$db->rollBack();
    logTechnicalError('questoes',$e);
    jsonResponse(['erro'=>'Nao foi possivel processar as questoes agora.'],500);
}
