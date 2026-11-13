<?php
require_once __DIR__.'/../server/services/simulationRecovery.php';
$ai=require __DIR__.'/../server/config/openrouter.php';
if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)throw new RuntimeException('Unsafe configuration');
$mode=$argv[1]??'';$p=realpath($argv[2]??'');$base=realpath(getenv('LOCALAPPDATA').'/EstudAI/Recovery');
if(!$p||!str_starts_with($p,$base.DIRECTORY_SEPARATOR))throw new RuntimeException('Private evidence directory required');
$path=$p.'/simulation_fixture.private.json';
$admin=in_array($mode,['fixture','release-fixture'],true);
$db=$admin?new PDO('mysql:host=localhost;port=3306;dbname=estudai_teste_2026;charset=utf8mb4','root',getenv('ESTUDAI_STAGE12_ADMIN_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]):getDB();
if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!==($admin?'root@localhost':'estudai_teste_app@localhost'))throw new RuntimeException('Wrong actual identity');
if($mode==='fixture'){
 if(is_file($path))throw new RuntimeException('Fixture exists; do not recreate');
 $db->beginTransaction();
 try{
  $db->exec("INSERT INTO simulados (titulo,descricao,duracao_min,total_questoes,ativo) VALUES ('Simulado teste12 - 2 questoes','Catalogo ficticio de validacao',10,2,1)");$classic=(int)$db->lastInsertId();
  $s=$db->prepare('INSERT INTO simulado_questoes (simulado_id,questao_id,ordem) VALUES (?,?,?)');foreach([1,2] as $q)$s->execute([$classic,$q,$q]);
  $db->exec("INSERT INTO simulados (titulo,descricao,duracao_min,total_questoes,ativo) VALUES ('Teste12 indisponivel - catalogo insuficiente','Sem questoes vinculadas',60,40,1)");$invalid=(int)$db->lastInsertId();
  $plan=$db->query("SELECT id,usuario_id,semana_inicio FROM planos_estudo WHERE id=2 AND status='ativo'")->fetch();if(!$plan)throw new RuntimeException('Expected plan missing');
  $s=$db->prepare("INSERT INTO plano_estudo_itens (plano_id,usuario_id,dia_semana,data_prevista,tipo_atividade,titulo,tempo_estimado,status,ordem) VALUES (?,?,'segunda',?,'simulado','Simulado planejado (teste12)',30,'pendente',4)");$s->execute([$plan['id'],$plan['usuario_id'],$plan['semana_inicio']]);$item=(int)$db->lastInsertId();
  $s=$db->prepare("INSERT INTO tarefas_estudo (usuario_id,plano_id,item_id,titulo,tipo,data_prevista,tempo_estimado,status,origem,metadata_json) VALUES (?,?,?,'Simulado planejado (teste12)','simulado',?,30,'pendente','manual',?)");$s->execute([$plan['usuario_id'],$plan['id'],$item,$plan['semana_inicio'],json_encode(['fixture'=>'stage12','dificuldade'=>'misto'])]);$task=(int)$db->lastInsertId();
  $s=$db->prepare("INSERT INTO eventos_calendario_estudai (usuario_id,plano_id,tarefa_id,tipo,titulo,data_evento,status) VALUES (?,?,?,'simulado','Simulado planejado (teste12)',?,'pendente')");$s->execute([$plan['usuario_id'],$plan['id'],$task,$plan['semana_inicio']]);$event=(int)$db->lastInsertId();
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 $f=['classic_id'=>$classic,'insufficient_id'=>$invalid,'user_id'=>(int)$plan['usuario_id'],'plan_id'=>2,'task_id'=>$task,'item_id'=>$item,'event_id'=>$event,'date'=>$plan['semana_inicio']];
 $h=fopen($path,'x');fwrite($h,json_encode($f,JSON_PRETTY_PRINT));fclose($h);echo json_encode(['result'=>'PASS','fixture_rows'=>7,'new_questions'=>0]);exit;
}
$f=json_decode(file_get_contents($path),true);
if($mode==='release-fixture'){
 $s=$db->prepare("SELECT id,data_liberacao FROM simulados_planejados WHERE usuario_id=? AND tarefa_id=? AND status='bloqueado'");$s->execute([$f['user_id'],$f['task_id']]);$r=$s->fetch();if(!$r)throw new RuntimeException('Only the newly created blocked fixture may be released');
 $s=$db->prepare('UPDATE simulados_planejados SET data_liberacao=? WHERE id=? AND usuario_id=? AND tarefa_id=?');$s->execute([date('Y-m-d'),$r['id'],$f['user_id'],$f['task_id']]);
 $h=fopen($p.'/release_fixture.private.json','x');fwrite($h,json_encode(['id'=>(int)$r['id'],'before'=>$r['data_liberacao'],'after'=>date('Y-m-d')]));fclose($h);echo json_encode(['result'=>'PASS','only_new_fixture_release_changed'=>true]);exit;
}
$checks=[];function s12check($ok,$name){if(!$ok)throw new RuntimeException('Failed: '.$name);$GLOBALS['checks'][]=['test'=>$name,'result'=>'PASS'];}
function s12snap(PDO $db):array{$o=[];foreach(['simulados','simulado_questoes','simulado_tentativas','simulado_respostas','simulados_planejados','simulados_planejados_respostas','tarefas_estudo','plano_estudo_itens','eventos_calendario_estudai','respostas_usuario','desempenho_diario','questoes_respostas_usuario','respostas_exercicios_planejados'] as $t)$o[$t]=hash('sha256',json_encode($db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll()));return $o;}
class FailSimulationPDO extends PDO{public function commit():bool{throw new RuntimeException('Controlled commit failure');}}
if($mode==='selection'){
 $before=s12snap($db);$task=examPlannedTask($db,$f['user_id'],$f['task_id']);
 foreach([5,12,40] as $n){$qs=examSelect($db,$task,$n);s12check(count($qs)===min($n,32),'available_'.$n);s12check(count($qs)===count(array_unique(array_column($qs,'id'))),'unique_'.$n);}
 $task['materia']=$db->query('SELECT materia FROM questoes GROUP BY materia HAVING COUNT(*)=8')->fetchColumn();s12check(count(examSelect($db,$task,12))===8,'strict_subject_insufficient');
 $task['conteudo']='unavailable-content';s12check(examSelect($db,$task,12)===[],'no_silent_content_relaxation');
 s12check(s12snap($db)===$before,'selection_has_no_writes');
}else{
 $db=new FailSimulationPDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $before=s12snap($db);$uid=$f['user_id'];
 try{
  if($mode==='classic-start')examClassicStart($db,$uid,$f['classic_id']);
  elseif($mode==='planned-create')examPlannedCreate($db,$uid,$f['task_id']);
  elseif(str_starts_with($mode,'classic-')){
   $s=$db->prepare('SELECT id FROM simulado_tentativas WHERE usuario_id=? AND simulado_id=? ORDER BY id DESC LIMIT 1');$s->execute([$uid,$f['classic_id']]);$id=(int)$s->fetchColumn();
   if($mode==='classic-finish')examClassicFinish($db,$uid,['tentativa_id'=>$id,'tempo_seg'=>22]);
   else examClassicAnswer($db,$uid,['tentativa_id'=>$id,'questao_id'=>1,'resposta'=>'A']);
  }else{
   $s=$db->prepare('SELECT id FROM simulados_planejados WHERE usuario_id=? AND tarefa_id=? ORDER BY id DESC LIMIT 1');$s->execute([$uid,$f['task_id']]);$id=(int)$s->fetchColumn();
   if($mode==='planned-finish')examPlannedFinish($db,$uid,$id);
   else{$r=examPlannedRow($db,$uid,$id);$q=examPlannedPayload($r)['questoes'][0];examPlannedAnswer($db,$uid,['simulado_planejado_id'=>$id,'question_key'=>$q['id'],'resposta_marcada'=>'A']);}
  }
  throw new RuntimeException('Failure injection did not run');
 }catch(RuntimeException $e){if($e->getMessage()!=='Controlled commit failure')throw $e;}
 s12check(!$db->inTransaction(),'transaction_closed_'.$mode);s12check(s12snap($db)===$before,'all_state_rolled_back_'.$mode);
}
$result=['result'=>'PASS','mode'=>$mode,'checks'=>$checks];$h=fopen($p.'/'.$mode.'_'.date('His').'.json','x');fwrite($h,json_encode($result));fclose($h);echo json_encode($result);
