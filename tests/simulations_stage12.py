"""Stage 12 controlled simulations; one attempt per mode and existing fictional accounts."""
import argparse,concurrent.futures,datetime,json,os,subprocess
from pathlib import Path
import questions_stage10 as core
R=Path(__file__).resolve().parents[1];check=core.check;checks=core.checks
G=core.GUARD+"require_once 'server/services/simulationRecovery.php';"
def sql(code):
 p=subprocess.run([core.PHP,'-d','display_errors=0','-d','log_errors=0','-r',G+code],cwd=R,capture_output=True,encoding='utf-8')
 if p.returncode:raise RuntimeError('Guarded simulation inspection failed')
 return json.loads(p.stdout)
def snap():
 return sql("$o=[];foreach(['simulados','simulado_questoes','simulado_tentativas','simulado_respostas','simulados_planejados','simulados_planejados_respostas','tarefas_estudo','plano_estudo_itens','eventos_calendario_estudai','respostas_usuario','desempenho_diario','questoes_respostas_usuario','respostas_exercicios_planejados','metas_semanais','conquistas_usuario'] as $t)$o[$t]=hash('sha256',json_encode($db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll()));echo json_encode($o);")
def private_questions(id):
 return sql("$s=$db->prepare('SELECT questoes_json FROM simulados_planejados WHERE id=?');$s->execute(["+str(id)+"]);echo $s->fetchColumn();")['questoes']
def private_classic(id):
 return sql("echo json_encode(examClassicCatalog($db,"+str(id)+")[1]);")
def privacy(d):
 def walk(v):
  if isinstance(v,dict):
   for k,x in v.items():
    check(not(k in ['acertou','correta','resposta_correta','explicacao','avaliacao','alternativa_correta_id']),'no grading key before finalization '+k)
    if k=='gabarito':check(x is None,'no answer key before finalization')
    walk(x)
  elif isinstance(v,list):
   for x in v:walk(x)
 walk(d)
def rollback(p,mode):
 r=subprocess.run([core.PHP,'tests/simulations_stage12.php',mode,str(p)],cwd=R,capture_output=True,encoding='utf-8')
 check(r.returncode==0,'rollback '+mode);checks.extend(json.loads(r.stdout)['checks'])
def parallel(clients,fn):
 with concurrent.futures.ThreadPoolExecutor(max_workers=len(clients)) as pool:return list(pool.map(fn,clients))
def main():
 pa=argparse.ArgumentParser();pa.add_argument('phase',choices=['prepare','answer','verify']);pa.add_argument('--private-dir',required=True);pa.add_argument('--accounts',required=True);args=pa.parse_args()
 p=Path(args.private_dir).resolve();ap=Path(args.accounts).resolve();allowed=(Path(os.environ['LOCALAPPDATA'])/'EstudAI/Recovery').resolve()
 check(p.is_relative_to(allowed) and ap.is_relative_to(allowed),'private paths');check(sql('echo json_encode(true);') is True,'database identity and AI guard')
 accounts=json.loads(ap.read_text());check(len(accounts)==2 and all(x['email'].endswith('@estudai.invalid') for x in accounts),'fictitious accounts only')
 f=json.loads((p/'simulation_fixture.private.json').read_text());A,B,A2=core.Client(),core.Client(),core.Client()
 A.login(accounts[0]);B.login(accounts[1]);A2.login(accounts[0]);anon=core.Client()
 idsfile=p/'simulation_attempts.private.json'
 if args.phase=='prepare':
  before=snap()
  for path in ['simulados.php?action=listar','simulados.php?action=historico','simulados.php?action=carregar&tentativa_id=999999','simulados-planejados.php?action=listar','simulados-planejados.php?action=carregar&id=999999']:
   anon.call(path,status=401)
  for endpoint,actions in [('simulados',['iniciar','responder','finalizar']),('simulados-planejados',['gerar_para_tarefa','iniciar','responder','finalizar'])]:
   for action in actions:
    path=endpoint+'.php?action='+action
    anon.call(path,'POST',{},status=401);A.call(path,'POST',{},status=419,csrf=False)
    saved=A.token;A.token='invalid';A.call(path,'POST',{},status=419);A.token=saved
    A.call(path,status=403)
  expired=core.Client();expired.login(accounts[0]);expired.call('auth.php?action=logout');expired.call('simulados.php?action=iniciar','POST',{'simulado_id':f['classic_id']},status=401)
  for path,method in [('simulados.php?action=listar','POST'),('simulados-planejados.php?action=gerar_para_semana','POST'),('simulados.php?action=apagar','POST'),('simulados.php?action=iniciar','PUT'),('cron_gerar.php','GET'),('ia.php','POST')]:
   A.call(path,method,{} if method=='POST' else None,status=403)
  A.call('simulados.php?action=iniciar','POST',raw=b'{',status=400);A.call('simulados.php?action=iniciar','POST',raw=b'x'*8001,status=413)
  A.call('simulados.php?action=iniciar','POST',{'action':'finalizar'},status=403)
  A.call('simulados.php?action=iniciar&action[]=iniciar','POST',{},status=403)
  A.call('simulados.php?action=iniciar','POST',{'simulado_id':9999999},status=404)
  A.call('simulados.php?action=iniciar','POST',{'simulado_id':f['insufficient_id']},status=409)
  B.call('simulados-planejados.php?action=gerar_para_tarefa','POST',{'tarefa_id':f['task_id']},status=404)
  check(snap()==before,'all negative reads and writes leave data unchanged')
  starts=parallel([A,A2],lambda c:c.call('simulados.php?action=iniciar','POST',{'simulado_id':f['classic_id'],'usuario_id':999999}))
  check(starts[0]['tentativa_id']==starts[1]['tentativa_id'],'concurrent conventional starts create one attempt')
  classic=starts[0]['tentativa_id'];privacy(starts[0]);rollback(p,'classic-answer')
  A.call('simulados.php?action=finalizar','POST',{'tentativa_id':classic},status=409)
  B.call('simulados.php?action=carregar&tentativa_id='+str(classic),status=404)
  rows=private_classic(f['classic_id']);q=rows[0];wrong=next(a['letra'] for a in q['alternativas'] if not a['correta'])
  for data,status in [
   ({'tentativa_id':999999,'questao_id':q['id'],'resposta':'A'},404),
   ({'tentativa_id':classic,'questao_id':999999,'resposta':'A'},404),
   ({'tentativa_id':classic,'questao_id':3,'resposta':'A'},404),
   ({'tentativa_id':classic,'questao_id':q['id'],'resposta':'Z'},400),
   ({'tentativa_id':classic,'questao_id':q['id'],'alternativa_id':rows[1]['alternativas'][0]['id']},400),
   ({'tentativa_id':classic,'questao_id':[],'resposta':'A'},400)]:
   A.call('simulados.php?action=responder','POST',data,status=status)
  body={'tentativa_id':classic,'questao_id':q['id'],'resposta':wrong,'acertos':999}
  replies=parallel([A,A2],lambda c:c.call('simulados.php?action=responder','POST',body))
  for x in replies:privacy(x)
  privacy(A.call('simulados.php?action=carregar&tentativa_id='+str(classic)))
  creates=parallel([A,A2],lambda c:c.call('simulados-planejados.php?action=gerar_para_tarefa','POST',{'tarefa_id':f['task_id']}))
  check(creates[0]['simulado']['id']==creates[1]['simulado']['id'],'concurrent planned creation returns same record')
  planned=creates[0]['simulado']['id'];privacy(creates[0]);check(creates[0]['simulado']['status']=='bloqueado','future planned exam blocked')
  A.call('simulados-planejados.php?action=iniciar','POST',{'simulado_id':planned},status=403)
  A.call('simulados-planejados.php?action=responder','POST',{'simulado_planejado_id':planned,'question_key':'q1','resposta_marcada':'A'},status=409)
  A.call('simulados-planejados.php?action=finalizar','POST',{'simulado_id':planned},status=409)
  core.private_write(p,'simulation_attempts.private.json',dict(classic=classic,planned=planned))
 elif args.phase=='answer':
  ids=json.loads(idsfile.read_text());planned=ids['planned'];classic=ids['classic']
  before=snap();d=A.call('simulados-planejados.php?action=carregar&id='+str(planned));check(d['simulado']['status']=='liberado','due release computed without GET write');check(snap()==before,'due GET read only')
  parallel([A,A2],lambda c:c.call('simulados-planejados.php?action=iniciar','POST',{'simulado_id':planned}))
  rollback(p,'planned-answer')
  B.call('simulados-planejados.php?action=carregar&id='+str(planned),status=404)
  items=private_questions(planned);q=items[0]
  for data,status in [
   ({'simulado_planejado_id':999999,'question_key':q['id'],'resposta_marcada':'A'},404),
   ({'simulado_planejado_id':planned,'question_key':'q999999','resposta_marcada':'A'},404),
   ({'simulado_planejado_id':planned,'question_key':'q32','resposta_marcada':'A'},404),
   ({'simulado_planejado_id':planned,'question_key':q['id'],'resposta_marcada':'Z'},400),
   ({'simulado_planejado_id':planned,'question_key':q['id'],'alternativa_id':next(iter(items[1]['alternativa_ids'].values()))},400)]:
   A.call('simulados-planejados.php?action=responder','POST',data,status=status)
  for i,q in enumerate(items[:9]):
   letter=next(x for x in 'ABCDE' if x!=q['resposta_correta']) if i==0 else q['resposta_correta']
   body={'simulado_planejado_id':planned,'question_key':q['id'],'resposta_marcada':letter,'usuario_id':999999,'percentual':100}
   data=A.call('simulados-planejados.php?action=responder','POST',body);privacy(data)
  A.call('simulados-planejados.php?action=finalizar','POST',{'simulado_id':planned},status=409)
  privacy(A.call('simulados-planejados.php?action=carregar&id='+str(planned)))
  fresh=core.Client();fresh.login(accounts[0]);check(len(fresh.call('simulados-planejados.php?action=carregar&id='+str(planned))['simulado']['respostas'])==9,'new session resumes nine saved answers')
 else:
  ids=json.loads(idsfile.read_text());classic=ids['classic'];planned=ids['planned'];before=snap()
  c=A.call('simulados.php?action=carregar&tentativa_id='+str(classic));s=A.call('simulados-planejados.php?action=carregar&id='+str(planned))['simulado']
  check(c['finalizado'] and c['resultado']['acertos']==1 and c['resultado']['erros']==1 and c['resultado']['percentual']==50,'conventional persisted score 1 of 2')
  check(s['status']=='finalizado' and s['resultado']['acertos']==9 and s['resultado']['erros']==1 and s['resultado']['percentual']==90,'planned persisted score 9 of 10')
  check(all(q.get('explicacao') for q in c['questoes']) and all(q.get('explicacao') for q in s['questoes']),'explanations available after closure')
  for result in [c['resultado'],s['resultado']]:
   check(result['respondidas']==result['total'] and result['acertos']+result['erros']==result['total'],'final result accounts for every real question')
   check(all(sum(x[k] for x in result['por_materia'].values())==result[k] for k in ['total','acertos','erros']),'subject breakdown matches persisted result')
  for endpoint,payload in [('simulados',{'tentativa_id':classic,'questao_id':1,'resposta':'A'}),('simulados-planejados',{'simulado_planejado_id':planned,'question_key':'q1','resposta_marcada':'A'})]:
   A.call(endpoint+'.php?action=responder','POST',payload,status=409)
   B.call(endpoint+'.php?action=responder','POST',payload,status=404)
  finals=parallel([A,A2],lambda x:x.call('simulados.php?action=finalizar','POST',{'tentativa_id':classic,'tempo_seg':999}))
  check(all(x['reutilizado'] and x['percentual']==50 for x in finals),'conventional final replay stable')
  finals=parallel([A,A2],lambda x:x.call('simulados-planejados.php?action=finalizar','POST',{'simulado_id':planned,'acertos':999}))
  check(all(x['reutilizado'] and x['resultado']['percentual']==90 for x in finals),'planned final replay stable')
  A.call('simulados.php?action=iniciar','POST',{'simulado_id':f['classic_id']})
  A.call('simulados-planejados.php?action=gerar_para_tarefa','POST',{'tarefa_id':f['task_id']})
  for client,expected_simulations in [(A,2),(B,0)]:
   stats=client.call('estatisticas.php?action=geral')
   check(stats['geral']==dict(total_questoes=2,total_acertos=1,total_erros=1,percentual=50),'daily statistics unaffected by simulations')
   check(stats['simulados_finalizados']==expected_simulations,'separate simulation indicator counts each owned finalization once')
  check(len(A.call('simulados.php?action=historico')['historico'])==1 and B.call('simulados.php?action=historico')['historico']==[],'conventional private history')
  check(B.call('simulados-planejados.php?action=listar')['simulados']==[],'planned history private')
  links=sql("echo json_encode($db->query('SELECT t.status task_status,i.status item_status,e.status event_status FROM tarefas_estudo t JOIN plano_estudo_itens i ON i.id=t.item_id AND i.usuario_id=t.usuario_id JOIN eventos_calendario_estudai e ON e.tarefa_id=t.id AND e.usuario_id=t.usuario_id WHERE t.id="+str(f['task_id'])+"')->fetch());")
  check(links==dict(task_status='concluida',item_status='concluida',event_status='concluido'),'planned task item calendar finalized together')
  integrity=sql("$a=$db->query('SELECT COUNT(*) FROM simulado_respostas r LEFT JOIN simulado_tentativas t ON t.id=r.tentativa_id LEFT JOIN simulado_questoes x ON x.simulado_id=t.simulado_id AND x.questao_id=r.questao_id WHERE t.id IS NULL OR x.id IS NULL')->fetchColumn();$b=$db->query('SELECT COUNT(*) FROM simulados_planejados_respostas r LEFT JOIN simulados_planejados p ON p.id=r.simulado_planejado_id AND p.usuario_id=r.usuario_id WHERE p.id IS NULL')->fetchColumn();echo json_encode([(int)$a,(int)$b]);")
  check(integrity==[0,0],'no orphan answers or cross-owner planned response links')
  check(snap()==before,'replays and GETs change no rows after finalization')
 core.private_write(p,'simulations_'+args.phase+'_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',dict(result='PASS',phase=args.phase,checks=checks,snapshots=snap()))
 print(json.dumps(dict(result='PASS',phase=args.phase,checks=len(checks))))
if __name__=='__main__':main()
