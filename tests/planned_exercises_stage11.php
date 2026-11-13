<?php
require_once __DIR__.'/../server/services/plannedExercises.php';
$ai=require __DIR__.'/../server/config/openrouter.php';
if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)throw new RuntimeException('Unsafe configuration');
$db=getDB();
if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')throw new RuntimeException('Unsafe identity');
$p=realpath($argv[2]??'');$allowed=realpath(getenv('LOCALAPPDATA').'/EstudAI/Recovery');
if(!$p||!str_starts_with($p,$allowed.DIRECTORY_SEPARATOR))throw new RuntimeException('Private directory required');
$fixturePath=$p.'/planned_fixture.private.json';$mode=$argv[1]??'selection';$checks=[];
function check11($ok,$name){if(!$ok)throw new RuntimeException('Check failed: '.$name);$GLOBALS['checks'][]=['test'=>$name,'result'=>'PASS'];}
function snap11(PDO $db): array{
 $result=[];foreach(['exercicios_planejados','respostas_exercicios_planejados','tarefas_estudo','plano_estudo_itens','eventos_calendario_estudai','respostas_usuario','desempenho_diario','questoes_respostas_usuario'] as $t)$result[$t]=hash('sha256',json_encode($db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll()));
 return $result;
}
class Stage11FailPDO extends PDO {
 public function commit(): bool{throw new RuntimeException('Controlled failure before commit');}
}
if($mode==='fixture'){
 if(is_file($fixturePath))throw new RuntimeException('Fixture already recorded; do not recreate');
 $plan=$db->query("SELECT id,usuario_id,semana_inicio FROM planos_estudo WHERE id=2 AND status='ativo'")->fetch();if(!$plan)throw new RuntimeException('Expected existing plan absent');
 $uid=(int)$plan['usuario_id'];$math=$db->query('SELECT materia FROM questoes GROUP BY materia HAVING COUNT(*)=8')->fetchColumn();
 $q=$db->query('SELECT materia,conteudo FROM questoes WHERE id=1')->fetch();
 $db->beginTransaction();$tasks=[];
 try{
  foreach([['main',$math,null],['partial',$q['materia'],$q['conteudo']]] as [$kind,$subject,$content]){
   $title='Pratica planejada '.$kind.' (teste11)';
   $s=$db->prepare("INSERT INTO plano_estudo_itens (plano_id,usuario_id,dia_semana,data_prevista,materia,conteudo,tipo_atividade,titulo,tempo_estimado,status,ordem) VALUES (?,?,'segunda',?,?,?,'questoes',?,30,'pendente',?)");
   $s->execute([$plan['id'],$uid,$plan['semana_inicio'],$subject,$content,$title,$kind==='main'?2:3]);$item=(int)$db->lastInsertId();
   $s=$db->prepare("INSERT INTO tarefas_estudo (usuario_id,plano_id,item_id,titulo,materia,conteudo,tipo,data_prevista,tempo_estimado,status,origem,fonte_conteudo,metadata_json) VALUES (?,?,?,?,?,?,'questoes',?,30,'pendente','manual','banco',?)");
   $s->execute([$uid,$plan['id'],$item,$title,$subject,$content,$plan['semana_inicio'],json_encode(['fixture'=>'stage11','dificuldade'=>'misto'])]);$task=(int)$db->lastInsertId();
   $s=$db->prepare("INSERT INTO eventos_calendario_estudai (usuario_id,plano_id,tarefa_id,tipo,titulo,conteudo,data_evento,semana_inicio,semana_fim,status) VALUES (?,?,?,'exercicio',?,?,?,?,?,'pendente')");
   $s->execute([$uid,$plan['id'],$task,$title,$content,$plan['semana_inicio'],$plan['semana_inicio'],$plan['semana_inicio']]);
   $tasks[$kind]=['task_id'=>$task,'item_id'=>$item,'event_id'=>(int)$db->lastInsertId()];
  }
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 $fixture=['user_id'=>$uid,'plan_id'=>(int)$plan['id'],'date'=>$plan['semana_inicio'],'tasks'=>$tasks];
 if(file_put_contents($fixturePath,json_encode($fixture,JSON_PRETTY_PRINT),LOCK_EX)===false)throw new RuntimeException('Fixture evidence not saved');
 echo json_encode(['result'=>'PASS','fixture_rows'=>6,'new_plans'=>0,'tasks'=>$tasks]);exit;
}
$f=json_decode(file_get_contents($fixturePath),true);$uid=$f['user_id'];
if($mode==='selection'){
 $before=snap11($db);$main=exerciciosLoadTarefa($db,$uid,$f['tasks']['main']['task_id']);$partial=exerciciosLoadTarefa($db,$uid,$f['tasks']['partial']['task_id']);
 foreach([5,8,12] as $n){
  $selection=exerciciosSelect($db,$main,$n);$rows=$selection['questoes'];
  check11(count($rows)===min($n,8),'math_available_'.$n);
  check11(count(array_unique(array_column($rows,'id')))===count($rows),'no_repeated_question_'.$n);
  check11(count(exerciciosSelect($db,$partial,$n)['questoes'])===1,'strict_content_available_'.$n);
 }
 $missing=$main;$missing['conteudo']='not-an-existing-content';
 check11(exerciciosSelect($db,$missing,5)['questoes']===[],'no_silent_content_relaxation');
 $hard=$main;$hard['metadata_json']=json_encode(['dificuldade'=>'dificil']);
 $r=exerciciosSelect($db,$hard,12)['questoes'];check11(count($r)===1&&$r[0]['dificuldade']==='dificil','difficulty_filter_kept');
 check11(snap11($db)===$before,'selection_read_only');
}else{
 $db=new Stage11FailPDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $before=snap11($db);
 try{
  if($mode==='rollback-create')exerciciosCreateForTarefa($db,$uid,$f['tasks']['main']['task_id']);
  elseif(in_array($mode,['rollback-answer','rollback-complete'],true)){
   $row=exerciciosFindRow($db,$uid,$f['tasks']['main']['task_id']);$data=exerciciosMapLote($db,$row);$answers=(array)$data['respostas'];
   $pending=array_values(array_filter(exerciciosPayload($row)['exercicios'],fn($q)=>!isset($answers[$q['id']])));
   if(!$pending)throw new RuntimeException('No pending fixture; do not repeat rollback');
   if($mode==='rollback-complete'&&count($pending)!==1)throw new RuntimeException('Final rollback requires exactly one pending');
   exerciciosAnswer($db,$uid,['exercicio_planejado_id'=>$row['id'],'exercise_key'=>$pending[0]['id'],'resposta_marcada'=>$pending[0]['resposta_correta']]);
  }else throw new RuntimeException('Unknown mode');
  throw new RuntimeException('Failure injection not reached');
 }catch(RuntimeException $e){if($e->getMessage()!=='Controlled failure before commit')throw $e;}
 check11(!$db->inTransaction(),'transaction_closed_'.$mode);
 check11(snap11($db)===$before,'all_rows_and_statuses_unchanged_'.$mode);
}
$result=['result'=>'PASS','mode'=>$mode,'checks'=>$checks,'persisted_test_failure_rows'=>0];
$name=$p.'/'.$mode.'_'.date('His').'.json';$h=fopen($name,'x');fwrite($h,json_encode($result,JSON_PRETTY_PRINT));fclose($h);
echo json_encode($result);
