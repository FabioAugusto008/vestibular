"""Controlled planned-practice integration. No seeds, cleanup DML or external AI."""
import argparse,concurrent.futures,datetime,json,os,subprocess
from pathlib import Path
import questions_stage10 as core
ROOT=Path(__file__).resolve().parents[1]
check=core.check;checks=core.checks;Client=core.Client
GUARD=core.GUARD+"require_once 'server/services/plannedExercises.php';"
def snap():
 return core.php(GUARD+"$r=[];foreach(['exercicios_planejados','respostas_exercicios_planejados','tarefas_estudo','plano_estudo_itens','eventos_calendario_estudai','respostas_usuario','desempenho_diario','questoes_respostas_usuario','questoes_do_dia','metas_semanais','conquistas_usuario'] as $t)$r[$t]=hash('sha256',json_encode($db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll()));echo json_encode($r);")
def raw_lot(lot):
 code=GUARD+"$s=$db->prepare('SELECT exercicios_json FROM exercicios_planejados WHERE id=?');$s->execute(["+str(lot)+"]);echo $s->fetchColumn();"
 r=subprocess.run([core.PHP,'-d','display_errors=0','-d','log_errors=0','-r',code],cwd=ROOT,capture_output=True,encoding='utf-8')
 check(r.returncode==0,'read private lot snapshot')
 return json.loads(r.stdout)
def privacy(lot):
 for q in lot['exercicios']:
  check('resposta_correta' not in q,'no planned answer key in items')
  if q['id'] not in lot['respostas']:check('explicacao' not in q,'no explanation before planned attempt')
 check('exercicios_json' not in lot,'no internal JSON exposed')
def rollback(p,mode):
 r=subprocess.run([core.PHP,'tests/planned_exercises_stage11.php',mode,str(p)],cwd=ROOT,capture_output=True,text=True)
 check(r.returncode==0,'controlled '+mode)
 result=json.loads(r.stdout);checks.extend(result['checks'])
def verify(p,f,A,B):
 before=snap()
 main=A.call('exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(f['tasks']['main']['task_id']))['lote']
 partial=A.call('exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(f['tasks']['partial']['task_id']))['lote']
 check(main['status']=='concluido' and main['resumo']['respondidas']==5 and main['resumo']['tarefa_concluida'],'full batch and task complete')
 check(main['resumo']['acertos']==4 and main['resumo']['erros']==1,'main planned correction totals')
 check(partial['status']=='concluido' and partial['insuficiente'] and not partial['resumo']['tarefa_concluida'],'partial lot answered without false task completion')
 for c in [A,B]:
  check(c.call('estatisticas.php?action=geral')['geral']==dict(total_questoes=2,total_acertos=1,total_erros=1,percentual=50),'planned results do not contaminate daily')
  check(c.call('metas.php?action=carregar')['meta']['questoes_feitas']==2,'weekly daily goal unaffected')
 plan=A.call('plano-estudos.php?action=carregar_ativo')['plano']
 statuses={t['id']:t['status'] for t in plan['tarefas']}
 check(statuses[f['tasks']['main']['task_id']]=='concluida' and statuses[f['tasks']['partial']['task_id']]=='em_andamento','task states linked to existing plan')
 stats=A.call('estatisticas.php?action=estudai_geral')
 check(stats['exercicios']['respondidos']==6 and stats['exercicios']['acertos']==5 and stats['exercicios']['erros']==1,'planned statistics count six own attempts once')
 check(B.call('estatisticas.php?action=estudai_geral')['exercicios']['respondidos']==0,'other account has no planned results')
 links=core.php(GUARD+"echo json_encode($db->query('SELECT t.id,t.status task_status,i.status item_status,e.status event_status FROM tarefas_estudo t JOIN plano_estudo_itens i ON i.id=t.item_id AND i.usuario_id=t.usuario_id AND i.plano_id=t.plano_id JOIN eventos_calendario_estudai e ON e.tarefa_id=t.id AND e.usuario_id=t.usuario_id AND e.plano_id=t.plano_id WHERE t.id IN ("+str(f['tasks']['main']['task_id'])+","+str(f['tasks']['partial']['task_id'])+") ORDER BY t.id')->fetchAll());")
 check(len(links)==2 and links[0]['task_status']=='concluida' and links[0]['item_status']=='concluida' and links[0]['event_status']=='concluido' and links[1]['task_status']=='em_andamento' and links[1]['item_status']=='em_andamento' and links[1]['event_status']=='pendente','task item event completion consistent')
 orphans=core.php(GUARD+"echo json_encode((int)$db->query(\"SELECT COUNT(*) FROM respostas_exercicios_planejados r LEFT JOIN exercicios_planejados e ON e.id=r.exercicio_planejado_id AND e.usuario_id=r.usuario_id LEFT JOIN JSON_TABLE(e.exercicios_json,'$.exercicios[*]' COLUMNS(exercise_key VARCHAR(80) PATH '$.id',questao_id INT PATH '$.questao_id')) j ON CAST(j.exercise_key AS BINARY)=CAST(r.exercise_key AS BINARY) LEFT JOIN questoes q ON q.id=j.questao_id WHERE e.id IS NULL OR j.questao_id IS NULL OR q.id IS NULL\")->fetchColumn());")
 check(orphans==0,'no orphan planned answers or question associations')
 payload=raw_lot(main['id'])['exercicios'][0]
 selected_ids=[q['questao_id'] for q in raw_lot(main['id'])['exercicios']]
 outside=core.php(GUARD+'echo json_encode((int)$db->query("SELECT id FROM questoes WHERE id NOT IN ('+','.join(map(str,selected_ids))+') ORDER BY id LIMIT 1")->fetchColumn());')
 A.call('exercicios-ia.php?action=responder','POST',{'exercicio_planejado_id':main['id'],'exercise_key':'q'+str(outside),'resposta_marcada':'A'},status=404)
 request={'exercicio_planejado_id':main['id'],'exercise_key':payload['id'],'resposta_marcada':next(a['resposta_marcada'] for k,a in main['respostas'].items() if k==payload['id'])}
 C=Client();C.login(core.json.loads(ACCOUNTS.read_text())[0])
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  results=list(pool.map(lambda c:c.call('exercicios-ia.php?action=responder','POST',request),[A,C]))
 check(all(r['reutilizado'] for r in results),'completed lot concurrent retries stable')
 A.call('exercicios-ia.php?action=responder','POST',{**request,'resposta_marcada':next(x for x in 'ABCDE' if x!=request['resposta_marcada'])},status=409)
 B.call('exercicios-ia.php?action=responder','POST',request,status=404)
 check(B.call('exercicios-ia.php?action=carregar_semana&semana_inicio='+f['date'])['lotes']==[],'other account weekly lots empty')
 for c in [A,B]:
  for path in ['questoes.php?action=carregar','historico.php','revisao.php?action=carregar','revisao.php?action=estatisticas','estatisticas.php?action=geral','estatisticas.php?action=estudai_geral','metas.php?action=carregar','conquistas.php?action=listar','calendario-estudai.php?action=mes&mes=2026-11']:
   c.call(path)
 check(snap()==before,'GETs and repeated completed POSTs leave every snapshot unchanged')
 core.private_write(p,'planned_verify_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',dict(result='PASS',checks=checks,snapshots=before))
 print(json.dumps(dict(result='PASS',mode='verify-only',checks=len(checks),new_answers=0)))
def main():
 global ACCOUNTS
 pa=argparse.ArgumentParser();pa.add_argument('--private-dir',required=True);pa.add_argument('--accounts',required=True);pa.add_argument('--verify-only',action='store_true');pa.add_argument('--resume',action='store_true');args=pa.parse_args()
 p=Path(args.private_dir).resolve();ACCOUNTS=Path(args.accounts).resolve();allowed=(Path(os.environ['LOCALAPPDATA'])/'EstudAI/Recovery').resolve()
 check(p.is_relative_to(allowed) and ACCOUNTS.is_relative_to(allowed),'private evidence paths')
 check(core.php(GUARD+"echo json_encode(true);") is True,'real database identity and AI guard before writes')
 accounts=json.loads(ACCOUNTS.read_text());check(len(accounts)==2 and all(a['email'].endswith('@estudai.invalid') for a in accounts),'only existing fictional accounts')
 f=json.loads((p/'planned_fixture.private.json').read_text());tasks=f['tasks'];A,B,A2=Client(),Client(),Client()
 A.login(accounts[0]);B.login(accounts[1]);A2.login(accounts[0])
 if args.verify_only:return verify(p,f,A,B)
 initial=snap();anonymous=Client()
 for path in ['exercicios-ia.php?action=carregar_semana','exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(tasks['main']['task_id'])]:
  anonymous.call(path,status=401)
 for action in ['gerar_por_tarefa','responder']:anonymous.call('exercicios-ia.php?action='+action,'POST',{},status=401)
 expired=Client();expired.login(accounts[0]);expired.call('auth.php?action=logout');expired.call('exercicios-ia.php?action=responder','POST',{},status=401)
 for action in ['gerar_por_tarefa','responder']:
  path='exercicios-ia.php?action='+action
  A.call(path,'POST',{},status=419,csrf=False);saved=A.token;A.token='invalid';A.call(path,'POST',{},status=419);A.token=saved
  A.call(path,'GET',status=403)
 for path,method in [('exercicios-ia.php','GET'),('exercicios-ia.php?action=avaliar_aberta','POST'),('exercicios-ia.php?action=carregar_por_tarefa','POST'),('exercicios-ia.php?action=gerar_por_tarefa','PUT'),('simulados-planejados.php?action=gerar','POST'),('cron_gerar.php','GET'),('ia.php','POST'),('tarefas-estudo.php?action=concluir','POST')]:
  A.call(path,method,{} if method=='POST' else None,status=403)
 A.call('exercicios-ia.php?action=gerar_por_tarefa','POST',{'action':'responder'},status=403)
 A.call('exercicios-ia.php?action=gerar_por_tarefa&action[]=gerar_por_tarefa','POST',{},status=403)
 A.call('exercicios-ia.php?action=gerar_por_tarefa','POST',raw=b'{',status=400)
 A.call('exercicios-ia.php?action=gerar_por_tarefa','POST',raw=b'x'*8001,status=413)
 for value in [None,0,[],999999999]:
  A.call('exercicios-ia.php?action=gerar_por_tarefa','POST',{'tarefa_id':value},status=404 if value==999999999 else 400)
 for task in tasks.values():
  path='exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(task['task_id'])
  d=A.call(path)
  if not args.resume:check(d['lote'] is None,'GET never creates a planned batch')
  B.call(path,status=404)
  B.call('exercicios-ia.php?action=gerar_por_tarefa','POST',{'tarefa_id':task['task_id']},status=404)
 check(snap()==initial,'negative requests and empty GETs cause no writes')
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  created=list(pool.map(lambda c:c.call('exercicios-ia.php?action=gerar_por_tarefa','POST',{'tarefa_id':tasks['main']['task_id'],'usuario_id':999999}),[A,A2]))
 check(created[0]['lote']['id']==created[1]['lote']['id'],'concurrent creation returns same lot')
 mainlot=created[0]['lote'];privacy(mainlot);check(mainlot['quantidade']==5 and not mainlot['insuficiente'],'full five-question lot')
 partial=A.call('exercicios-ia.php?action=gerar_por_tarefa','POST',{'tarefa_id':tasks['partial']['task_id']})['lote']
 privacy(partial);check(partial['quantidade']==1 and partial['quantidade_solicitada']==5 and partial['insuficiente'] and partial['aviso'],'insufficient strict content is explicit')
 core.private_write(p,'planned_lots_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',{'main':mainlot['id'],'partial':partial['id']})
 if not args.resume:rollback(p,'rollback-answer')
 items=raw_lot(mainlot['id'])['exercicios'];q0,q1=items[:2]
 for data,status in [
  ({},400),({'exercicio_planejado_id':999999999,'exercise_key':q0['id'],'resposta_marcada':'A'},404),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':'bad','resposta_marcada':'A'},400),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':'q99999999','resposta_marcada':'A'},404),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':q0['id'],'resposta_marcada':'Z'},400),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':q0['id'],'alternativa_id':999999999},400),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':q0['id'],'alternativa_id':next(iter(q1['alternativa_ids'].values()))},400),
  ({'exercicio_planejado_id':mainlot['id'],'exercise_key':q0['id'],'questao_id':q1['questao_id'],'resposta_marcada':'A'},400)]:
  A.call('exercicios-ia.php?action=responder','POST',data,status=status)
 daily_before={k:initial[k] for k in ['respostas_usuario','desempenho_diario','questoes_respostas_usuario','questoes_do_dia','metas_semanais','conquistas_usuario']}
 for i,q in enumerate(items[:4]):
  letter=next(x for x in q['alternativas'] if x!=q['resposta_correta']) if i==0 else q['resposta_correta']
  body={'exercicio_planejado_id':mainlot['id'],'exercise_key':q['id'],'resposta_marcada':letter,'usuario_id':999999}
  if i==0:
   with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:replies=list(pool.map(lambda c:c.call('exercicios-ia.php?action=responder','POST',body),[A,A2]))
   check(all(not r['acertou'] for r in replies),'concurrent wrong answer stable correction')
  else:
   r=A.call('exercicios-ia.php?action=responder','POST',body);check(r['acertou'],'correct answer evaluated on server')
  r=A.call('exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(tasks['main']['task_id']))['lote']
  check(r['resumo']['respondidas']==(4 if args.resume else i+1) and not r['resumo']['tarefa_concluida'],'no premature completion')
 pitem=raw_lot(partial['id'])['exercicios'][0]
 A.call('exercicios-ia.php?action=responder','POST',{'exercicio_planejado_id':partial['id'],'exercise_key':pitem['id'],'resposta_marcada':pitem['resposta_correta']})
 pstate=A.call('exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(tasks['partial']['task_id']))['lote']
 check(pstate['status']=='concluido' and not pstate['resumo']['tarefa_concluida'],'answering reduced lot does not complete full task')
 rollback(p,'rollback-complete')
 after=snap();check(all(after[k]==v for k,v in daily_before.items()),'planned practice leaves daily and review evidence untouched')
 fresh=Client();fresh.login(accounts[0]);r=fresh.call('exercicios-ia.php?action=carregar_por_tarefa&tarefa_id='+str(tasks['main']['task_id']))['lote']
 check(r['resumo']['respondidas']==4,'new authenticated session recovers four attempts')
 before=snap()
 for c in [A,B]:
  c.call('exercicios-ia.php?action=carregar_semana&semana_inicio='+f['date'])
 check(snap()==before,'populated GETs read only')
 core.private_write(p,'planned_http_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',dict(result='PASS',checks=checks,snapshots=after,main_answers=4,partial_answers=1))
 print(json.dumps(dict(result='PASS',checks=len(checks),main_answers=4,partial_answers=1,remaining_main_answers=1)))
if __name__=='__main__':main()
