<?php
require_once __DIR__ . '/../services/dailyQuestions.php';
requireLogin();
$db=getDB();$uid=currentUserId();
try {
    [$action,$payload]=questionFlowAction(['GET'=>['carregar','estatisticas'],'POST'=>['responder']]);
    beginDashboardReadOnlyGet($db,['carregar','estatisticas']);
    if($action==='responder') {
        validateCsrfToken();
        jsonResponse(questionReviewAnswer($db,$uid,date('Y-m-d'),$payload));
    }
    $rows=questionReviewRows($db,$uid);
    if($action==='estatisticas') {
        $subjects=[];
        foreach($rows as $q)$subjects[$q['materia']]=($subjects[$q['materia']]??0)+1;
        jsonResponse(['ok'=>true,'pendentes'=>$subjects,'total'=>count($rows)]);
    }
    $subject=$_GET['materia']??'todas';
    if(!is_string($subject))questionFlowFail('Materia invalida.',400);
    $subjectKey=fn($value)=>strtr(mb_strtolower($value,'UTF-8'),['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    $filtered=array_values(array_filter($rows,fn($q)=>$subject==='todas'||$subject===''||$subjectKey($q['materia'])===$subjectKey($subject)));
    $limit=$_GET['limite']??20;
    if(filter_var($limit,FILTER_VALIDATE_INT)===false)questionFlowFail('Limite invalido.',400);
    $page=array_slice($filtered,0,max(1,min(50,(int)$limit)));
    $answers=questionReviewFeedback($db,$uid,date('Y-m-d'),$page);
    jsonResponse(['ok'=>true,'questoes'=>questionPublicRows($page,$answers),'respostas'=>(object)$answers,'total_disponiveis'=>count($filtered)]);
} catch(QuestionFlowError $e) {
    if($db->inTransaction())$db->rollBack();
    jsonResponse(['erro'=>$e->getMessage()],$e->getCode());
} catch(Throwable $e) {
    if($db->inTransaction())$db->rollBack();
    logTechnicalError('revisao',$e);
    jsonResponse(['erro'=>'Nao foi possivel processar a revisao agora.'],500);
}
