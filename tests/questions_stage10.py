"""Controlled stage 10 integration. Existing fictional accounts; no seeds or cleanup."""
import argparse,concurrent.futures,datetime,hashlib,http.cookiejar,json,os,re,subprocess,sys,urllib.error,urllib.parse,urllib.request
from pathlib import Path
sys.dont_write_bytecode=True
ROOT=Path(__file__).resolve().parents[1]
BASE='http://localhost/estudAi_1.1/server/api/'
PHP=r'C:\xampp\php\php.exe'
checks=[]
def check(ok,name):
 if not ok:raise RuntimeError('Failed: '+name)
 checks.append(dict(test=name,result='PASS'))
def php(code):
 r=subprocess.run([PHP,'-d','display_errors=0','-d','log_errors=0','-r',code],cwd=ROOT,capture_output=True,text=True)
 if r.returncode:raise RuntimeError('Guarded PHP inspection failed')
 return json.loads(r.stdout)
GUARD="""require 'server/services/dailyQuestions.php';$ai=require 'server/config/openrouter.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||$ai['enabled']!==false)exit(1);$db=getDB();if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')exit(2);"""
def snapshot():
 return php(GUARD+"""$r=[];foreach(['questoes','questoes_alternativas','questoes_do_dia','respostas_usuario','desempenho_diario','questoes_respostas_usuario','metas_semanais','conquistas_usuario','planos_estudo','tarefas_estudo','eventos_calendario_estudai'] as $t){$rows=$db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll();$r[$t]=['rows'=>count($rows),'digest'=>hash('sha256',json_encode($rows))];}echo json_encode($r);""")
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar();self.token=None
  self.opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar))
 def call(self,path,method='GET',payload=None,status=200,csrf=True,raw=None):
  headers={'X-Requested-With':'XMLHttpRequest'}
  if csrf and self.token:headers['X-CSRF-Token']=self.token
  body=None
  if payload is not None:
   body=json.dumps(payload).encode();headers['Content-Type']='application/json'
   if path.startswith('auth.php'):
    body=urllib.parse.urlencode(payload).encode();headers['Content-Type']='application/x-www-form-urlencoded'
  if raw is not None:body=raw;headers['Content-Type']='application/json'
  req=urllib.request.Request(BASE+path,data=body,method=method,headers=headers)
  try:r=self.opener.open(req,timeout=15)
  except urllib.error.HTTPError as e:r=e
  with r:
   check(r.status==status,method+' '+path+' status '+str(status))
   if r.status==403 and r.headers.get_content_type()!='application/json':return {}
   check(r.headers.get_content_type()=='application/json',path+' JSON')
   data=json.load(r)
   check(not any(x in json.dumps(data) for x in ['SQLSTATE','PDOException','Stack trace','Fatal error']),path+' no technical leak')
  if isinstance(data.get('csrf_token'),str):self.token=data['csrf_token']
  return data
 def login(self,a):
  self.call('auth.php?action=status')
  self.call('auth.php?action=login','POST',{'email':a['email'],'senha':a['password']})
  auth=self.call('auth.php?action=status')
  check(auth.get('logado') is True,'authenticated fictional session')
  return auth
def private_write(p,name,value):
 with (p/name).open('x',encoding='utf-8') as f:json.dump(value,f,ensure_ascii=False,indent=2)
def no_answers(data,name):
 for q in data['questoes']:
  check('resposta_correta' not in q and 'alternativa_correta_id' not in q and 'explicacao' not in q,name+' question not disclosed')
  check(all('correta' not in a for a in q['alternativas']),name+' alternatives not disclosed')
 check(not data.get('gabarito') and not data.get('respostas'),name+' correction empty')
def rollback(p,mode):
 r=subprocess.run([PHP,'tests/questions_transaction_stage10.php',mode],cwd=ROOT,capture_output=True,text=True)
 check(r.returncode==0,'rollback '+mode)
 result=json.loads(r.stdout)
 private_write(p,'rollback_'+mode+'.json',result)
 checks.extend(result['checks'])
def verify_populated(p,accounts):
 A,B,B2=Client(),Client(),Client();A.login(accounts[0]);B.login(accounts[1]);B2.login(accounts[1])
 before=snapshot()
 questions=php(GUARD+"echo json_encode(questionDailyRows($db,date('Y-m-d')));")
 q=questions[0];body={'questao_id':q['id'],'resposta':q['resposta_correta']}
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  retries=list(pool.map(lambda c:c.call('revisao.php?action=responder','POST',body),[B,B2]))
 check(all(x['acertou'] for x in retries),'review repeated concurrent result remains stable')
 B.call('revisao.php?action=responder','POST',{'questao_id':q['id'],'resposta':next(a['letra'] for a in q['alternativas'] if not a['correta'])},status=409)
 for c in [A,B]:
  for path in ['questoes.php?action=carregar','historico.php','revisao.php?action=carregar','revisao.php?action=estatisticas','estatisticas.php?action=geral','estatisticas.php?action=evolucao&dias=14','conquistas.php?action=listar','metas.php?action=carregar','preferencias.php?action=carregar']:
   c.call(path)
  stats=c.call('estatisticas.php?action=geral')
  check(stats['geral']==dict(total_questoes=2,total_acertos=1,total_erros=1,percentual=50),'review does not contaminate daily results')
  check(stats['por_materia']['Português']['percentual']==50,'accented daily subject statistics')
 check(len(A.call('revisao.php?action=carregar&materia=portugues')['questoes'])==1,'unaccented subject tab matches accented catalog')
 check(A.call('revisao.php?action=carregar&materia=matematica')['questoes']==[],'empty subject filter')
 check(A.call('revisao.php?action=estatisticas')['total']==1,'A pending error remains')
 check(B.call('revisao.php?action=estatisticas')['total']==0,'B correct review removed pending error')
 no_answers(A.call('revisao.php?action=carregar'),'A has not attempted review')
 no_answers(B.call('revisao.php?action=carregar'),'B review empty after correction')
 check(B.call('revisao.php?action=carregar&usuario_id=1')['questoes']==[],'forged user cannot access other review queue')
 check(snapshot()==before,'review duplicates and populated GETs leave all snapshot tables unchanged')
 check(before['questoes_do_dia']['rows']==2 and before['respostas_usuario']['rows']==4 and before['desempenho_diario']['rows']==2 and before['questoes_respostas_usuario']['rows']==1,'only intended educational records persisted')
 private_write(p,'questions_populated_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',dict(result='PASS',checks=checks,snapshots=before,application_data_writes=0))
 print(json.dumps(dict(result='PASS',checks=len(checks),mode='verify-populated',application_data_writes=0)))
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--private-dir',required=True);parser.add_argument('--accounts',required=True);parser.add_argument('--resume',action='store_true');parser.add_argument('--verify-only',action='store_true');args=parser.parse_args()
 p=Path(args.private_dir).resolve();ap=Path(args.accounts).resolve();allowed=(Path(os.environ['LOCALAPPDATA'])/'EstudAI/Recovery').resolve()
 check(p.is_relative_to(allowed) and ap.is_relative_to(allowed),'private evidence and credential paths')
 accounts=json.loads(ap.read_text());check(len(accounts)==2 and all(x['email'].endswith('@estudai.invalid') for x in accounts),'existing fictional accounts only')
 check(php(GUARD+"echo json_encode(true);") is True,'database account AI guard before writes')
 if args.verify_only:return verify_populated(p,accounts)
 A,B,A2=Client(),Client(),Client();anon=Client()
 authA=A.login(accounts[0]);authB=B.login(accounts[1]);A2.login(accounts[0])
 for endpoint in ['questoes.php?action=carregar','revisao.php?action=carregar','revisao.php?action=estatisticas','estatisticas.php?action=geral','historico.php']:
  anon.call(endpoint,status=401)
 for action in ['preparar','responder','finalizar']:anon.call('questoes.php?action='+action,'POST',{},status=401)
 anon.call('revisao.php?action=responder','POST',{},status=401)
 expired=Client();expired.login(accounts[1]);expired.call('auth.php?action=logout');expired.call('questoes.php?action=responder','POST',{},status=401)
 for path in ['questoes.php?action=preparar','questoes.php?action=responder','questoes.php?action=finalizar','revisao.php?action=responder']:
  A.call(path,'POST',{},status=419,csrf=False)
  token=A.token;A.token='invalid';A.call(path,'POST',{},status=419);A.token=token
 for path,method in [('questoes.php?action=finalizar','GET'),('questoes.php?action=preparar','GET'),('questoes.php?action=carregar','POST'),('questoes.php?action=responder','PUT'),('revisao.php?action=estatisticas','POST'),('conquistas.php?action=verificar','POST'),('estatisticas.php?action=evolucao_materia','GET'),('estatisticas.php?action=geral','POST'),('simulados.php?action=carregar','GET'),('exercicios-ia.php?action=gerar','POST'),('cron_gerar.php','GET'),('ia.php','POST')]:
  A.call(path,method,{} if method=='POST' else None,status=403)
 A.call('questoes.php?action=responder&action=carregar','POST',{},status=403)
 A.call('questoes.php?action=responder&action[]=responder','POST',{},status=403)
 A.call('questoes.php?action=preparar','POST',{'action':'responder'},status=403)
 A.call('questoes.php?action=preparar','POST',raw=b'{',status=400)
 A.call('questoes.php?action=preparar','POST',raw=b'x'*8001,status=413)
 for qty in [0,21,'bad',[]]:A.call('questoes.php?action=preparar','POST',{'quantidade':qty},status=400)
 initial=A.call('questoes.php?action=carregar')
 if not args.resume:
  check(initial['questoes']==[],'empty daily batch before explicit POST')
  check(A.call('estatisticas.php?action=geral')['geral']['total_questoes']==0,'empty progress A')
  check(B.call('estatisticas.php?action=geral')['geral']['total_questoes']==0,'empty progress B')
 before_get=snapshot()
 for c in [A,B]:
  for path in ['questoes.php?action=carregar','historico.php','revisao.php?action=carregar','revisao.php?action=estatisticas','estatisticas.php?action=geral','estatisticas.php?action=evolucao&dias=14','conquistas.php?action=listar','metas.php?action=carregar','preferencias.php?action=carregar']:
   c.call(path)
 check(snapshot()==before_get,'all assessed GETs read only before answers')
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  batches=list(pool.map(lambda c:c.call('questoes.php?action=preparar','POST',{'quantidade':2}),[A,B]))
 check(all(x['ok'] for x in batches),'concurrent explicit preparation')
 first=A.call('questoes.php?action=carregar')
 check(first['total']==2,'only two existing catalog questions selected')
 if not args.resume:no_answers(first,'initial daily')
 private_write(p,'prepared_batch.private.json',{'ids':[q['id'] for q in first['questoes']]})
 questions=php(GUARD+"echo json_encode(questionDailyRows($db,date('Y-m-d')));")
 q1,q2=questions
 correct=q1['resposta_correta'];wrong=next(a['letra'] for a in q2['alternativas'] if not a['correta'])
 if not args.resume:rollback(p,'answer')
 for body in [{},{'questao_id':0,'resposta':'A'},{'questao_id':[],'resposta':'A'},{'questao_id':q1['id'],'resposta':'Z'},{'questao_id':q1['id'],'resposta':[]},{'questao_id':q1['id'],'alternativa_id':999999999},{'questao_id':q1['id'],'alternativa_id':q2['alternativas'][0]['id']},{'questao_id':q1['id'],'alternativa_id':q1['alternativas'][0]['id'],'resposta':'Z'}]:
  A.call('questoes.php?action=responder','POST',body,status=400)
 for bad in [999999999,max(q1['id'],q2['id'])+1]:A.call('questoes.php?action=responder','POST',{'questao_id':bad,'resposta':'A'},status=404)
 if not args.resume:A.call('questoes.php?action=finalizar','POST',{},status=409)
 body={'questao_id':q1['id'],'resposta':correct,'usuario_id':authB['usuario_id'] if 'usuario_id' in authB else 999999}
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  answered=list(pool.map(lambda c:c.call('questoes.php?action=responder','POST',body),[A,A2]))
 check(all(x['acertou'] and x['correta']==correct for x in answered),'server correction and concurrent duplicate stability')
 check(all(x.get('explicacao') for x in answered),'explanation only after attempt')
 A.call('questoes.php?action=responder','POST',{'questao_id':q1['id'],'resposta':next(a['letra'] for a in q1['alternativas'] if a['letra']!=correct)},status=409)
 bad=A.call('questoes.php?action=responder','POST',{'questao_id':q2['id'],'resposta':wrong})
 check(not bad['acertou'] and bad['correta']==q2['resposta_correta'],'incorrect answer corrected on server')
 Bfresh=B.call('questoes.php?action=carregar&usuario_id='+str(authA.get('usuario_id',999999)))
 if not args.resume:no_answers(Bfresh,'second account after A responses')
 pending=A.call('revisao.php?action=carregar')
 check(any(q['id']==q2['id'] for q in pending['questoes']),'daily error becomes review pending')
 no_answers(pending,'review before review attempt')
 A.call('revisao.php?action=responder','POST',{'questao_id':q1['id'],'resposta':correct},status=404)
 B.call('revisao.php?action=responder','POST',{'questao_id':q2['id'],'resposta':q2['resposta_correta']},status=404)
 A.call('revisao.php?action=responder','POST',{'questao_id':q2['id'],'alternativa_id':q1['alternativas'][0]['id']},status=400)
 if not args.resume:rollback(p,'review')
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  finished=list(pool.map(lambda c:c.call('questoes.php?action=finalizar','POST',{'tempo_seg':90}),[A,A2]))
 check(all(x['acertos']==1 and x['erros']==1 and x['total']==2 for x in finished),'finalization concurrent and isolated')
 again=A.call('questoes.php?action=finalizar','POST',{'tempo_seg':999})
 check(again['reutilizado'] and again['tempo_seg']==90,'finish retry preserves original duration')
 daily=A.call('questoes.php?action=carregar')
 check(daily['finalizado'] and len(daily['respostas'])==2,'daily results persisted and reloaded')
 check(all(q.get('explicacao') for q in daily['questoes']),'answered question explanation retained on reload')
 stats=A.call('estatisticas.php?action=geral')['geral']
 check(stats==dict(total_questoes=2,total_acertos=1,total_erros=1,percentual=50),'daily statistics agree with persisted answers')
 hist=A.call('historico.php');check(len(hist['historico'])==1 and int(hist['totais']['total_acertos'])==1 and int(hist['totais']['total_erros'])==1,'history matches completed daily result')
 check(A.call('metas.php?action=carregar')['meta']['questoes_feitas']==2,'weekly progress counts daily attempts')
 check(B.call('estatisticas.php?action=geral')['geral']['total_questoes']==0 if not args.resume else True,'B daily progress remains separate')
 before_get=snapshot()
 for c in [A,B]:
  for path in ['questoes.php?action=carregar','historico.php','revisao.php?action=carregar','revisao.php?action=estatisticas','estatisticas.php?action=geral','estatisticas.php?action=evolucao&dias=14','conquistas.php?action=listar','metas.php?action=carregar']:
   c.call(path)
 check(snapshot()==before_get,'GETs remain read only after populated results')
 private_write(p,'questions_stage10_'+datetime.datetime.now().strftime('%H%M%S_%f')+'.json',dict(result='PASS',checks=checks,phase='HTTP complete A; B reserved for real browser',snapshots=before_get,new_daily_questions=2,new_daily_answers_A=2,new_review_answers_A=0))
 print(json.dumps(dict(result='PASS',checks=len(checks),A_answers=2,B_answers=0,review_answers=0,private_dir=str(p))))
if __name__=='__main__':main()
