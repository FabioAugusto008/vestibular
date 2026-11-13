<?php
require_once __DIR__.'/../server/services/dailyQuestions.php';
$ai=require __DIR__.'/../server/config/openrouter.php';
if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)throw new RuntimeException('Unsafe test configuration');
class Stage10FailCommitPDO extends PDO {
    public function commit(): bool { throw new RuntimeException('Controlled failure immediately before commit'); }
}
$db=new Stage10FailCommitPDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($db->query('SELECT DATABASE()')->fetchColumn()!=='estudai_teste_2026'||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')throw new RuntimeException('Wrong actual identity');
$checks=[];
function check10($ok,$name){if(!$ok)throw new RuntimeException('Failed: '.$name);$GLOBALS['checks'][]=['test'=>$name,'result'=>'PASS'];}
$day=date('Y-m-d');
$mode=$argv[1]??'prepare';
$uid=(int)$db->query('SELECT id FROM usuarios ORDER BY id LIMIT 1')->fetchColumn();
$before=[];foreach(['questoes_do_dia','respostas_usuario','desempenho_diario','questoes_respostas_usuario'] as $t)$before[$t]=(int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();
try {
 if($mode==='prepare') {
  if($before['questoes_do_dia']!==0)throw new RuntimeException('Rollback preparation requires initial empty daily table');
  questionDailyPrepare($db,$day,2);
 } elseif($mode==='answer') {
  $qs=questionDailyRows($db,$day);$q=$qs[0];
  questionDailyAnswer($db,$uid,$day,['questao_id'=>$q['id'],'resposta'=>'A']);
 } elseif($mode==='review') {
  $qs=questionReviewRows($db,$uid);if(!$qs)throw new RuntimeException('No pending review fixture');
  questionReviewAnswer($db,$uid,$day,['questao_id'=>$qs[0]['id'],'resposta'=>$qs[0]['resposta_correta']]);
 } else throw new RuntimeException('Invalid test mode');
 throw new RuntimeException('Failure injection did not run');
} catch(RuntimeException $e) {
 if($e->getMessage()!=='Controlled failure immediately before commit')throw $e;
 check10(!$db->inTransaction(),'transaction_closed_after_'.$mode.'_failure');
}
foreach($before as $t=>$count)check10((int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()===$count,$t.'_unchanged_after_'.$mode.'_rollback');
echo json_encode(['result'=>'PASS','checks'=>$checks,'mode'=>$mode,'persisted_rows'=>0]);
