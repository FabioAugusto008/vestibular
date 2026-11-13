"""Controlled stage 08 tests: one committed plan; no schema or cleanup DML."""
import argparse
import concurrent.futures
import datetime
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import urllib.error
import urllib.request

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("stage06", ROOT / "tests/recovery_stage06.py")
old = importlib.util.module_from_spec(spec)
spec.loader.exec_module(old)
BASE = "http://localhost/estudAi_1.1/server/api/"
TABLES = ["planos_estudo", "plano_versoes", "plano_estudo_itens", "tarefas_estudo",
          "eventos_calendario_estudai", "planejamento_semanal_controle"]
checks = []

def check(ok, name, status=None):
    if not ok:
        raise RuntimeError("Check failed: " + name + (" HTTP " + str(status) if status else ""))
    checks.append(dict(test=name, result="PASS", http_status=status))

def loopback():
    code = "Get-NetTCPConnection -State Listen -LocalPort 80 | Select-Object LocalAddress,OwningProcess | ConvertTo-Json -Compress"
    p = subprocess.run(["powershell", "-NoProfile", "-Command", code], capture_output=True, text=True)
    check(p.returncode == 0, "inspect_HTTP_listener")
    rows = json.loads(p.stdout)
    check(isinstance(rows, list) and len(rows) == 2 and {x["LocalAddress"] for x in rows} == {"127.0.0.1", "::1"}, "HTTP_loopback_only")

def call(client, path, method="GET", payload=None, expected=200, csrf=True, raw=None):
    headers = {"X-Requested-With": "XMLHttpRequest"}
    if method == "POST":
        headers["Content-Type"] = "application/json"
        if csrf and client.token:
            headers["X-CSRF-Token"] = client.token
    body = raw if raw is not None else (json.dumps(payload or {}).encode() if method == "POST" else None)
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers)
    try:
        response = client.opener.open(req, timeout=12)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        check(response.status == expected, method + " " + path, response.status)
        if response.status == 403:
            return None
        check(response.headers.get_content_type() == "application/json", "JSON " + path)
        data = json.load(response)
        old.safe_json(data)
        return data

def php(code, *args):
    p = subprocess.run([old.PHP, "-d", "display_errors=0", "-d", "log_errors=0", "-r", code, *map(str,args)], capture_output=True, text=True, cwd=ROOT)
    check(p.returncode == 0, "real_DB_guarded_query")
    return json.loads(p.stdout)

def db_snapshot():
    return php(r"""
    require 'server/config/database.php';
    if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||DB_HOST!=='localhost')exit(1);
    $db=getDB();$ai=require 'server/config/openrouter.php';
    if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost'||$ai['enabled']!==false)exit(2);
    $db->exec('SET SESSION group_concat_max_len=1048576');
    $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
    $tables=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
    $out=[];$q=chr(96);
    foreach($tables as $table){
        if(!preg_match('/^[a-z0-9_]+$/',$table))exit(3);
        $s=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);
        $cols=$s->fetchAll(PDO::FETCH_COLUMN);
        $row='SELECT SHA2(JSON_ARRAY('.$q.implode($q.','.$q,$cols).$q.'),256) rh FROM '.$q.$table.$q;
        $sql="SELECT COUNT(*) rows_count,SHA2(COALESCE(GROUP_CONCAT(rh ORDER BY rh SEPARATOR ''),''),256) digest,COALESCE(LENGTH(GROUP_CONCAT(rh ORDER BY rh SEPARATOR '')),0) digest_bytes FROM (".$row.") h";
        $out[$table]=$db->query($sql)->fetch();
        if((int)$out[$table]['digest_bytes']!==64*(int)$out[$table]['rows_count'])exit(4);
    }
    $db->rollBack();echo json_encode($out);
    """)

def integrity(uid):
    return php(r"""
    require 'server/config/database.php';if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app')exit(1);
    $db=getDB();$uid=(int)$argv[1];$out=['counts'=>[]];
    foreach(['planos_estudo','plano_versoes','plano_estudo_itens','tarefas_estudo','eventos_calendario_estudai','planejamento_semanal_controle'] as $t){
        $s=$db->prepare('SELECT COUNT(*) FROM '.$t.' WHERE usuario_id=?');$s->execute([$uid]);$out['counts'][$t]=(int)$s->fetchColumn();
    }
    $out['bad_task_links']=(int)$db->query('SELECT COUNT(*) FROM tarefas_estudo t LEFT JOIN planos_estudo p ON p.id=t.plano_id AND p.usuario_id=t.usuario_id LEFT JOIN plano_estudo_itens i ON i.id=t.item_id AND i.plano_id=t.plano_id AND i.usuario_id=t.usuario_id WHERE p.id IS NULL OR i.id IS NULL')->fetchColumn();
    $out['bad_profile_links']=(int)$db->query('SELECT COUNT(*) FROM planos_estudo p LEFT JOIN estudo_perfis e ON e.id=p.perfil_id AND e.usuario_id=p.usuario_id WHERE e.id IS NULL')->fetchColumn();
    $s=$db->prepare('SELECT perfil_id FROM planos_estudo WHERE usuario_id=? AND status=\'ativo\' LIMIT 1');$s->execute([$uid]);$out['owned_plan_profile']=(int)$s->fetchColumn();
    $out['bad_event_links']=(int)$db->query("SELECT COUNT(*) FROM eventos_calendario_estudai e LEFT JOIN tarefas_estudo t ON t.id=e.tarefa_id AND t.plano_id=e.plano_id AND t.usuario_id=e.usuario_id WHERE t.id IS NULL OR e.data_evento<>t.data_prevista OR e.hora_inicio<>t.hora_inicio OR e.hora_fim<>t.hora_fim OR e.conteudo<>t.conteudo")->fetchColumn();
    echo json_encode($out);
    """, uid)

def login(account):
    c = old.Client()
    status, data, _ = c.call("auth", "login", "POST", {"email": account["email"], "senha": account["password"]})
    check(status == 200 and data.get("ok"), "existing_fictional_login", status)
    return c

def run(private, accounts_path, resume):
    loopback()
    accounts = json.loads(accounts_path.read_text(encoding="utf-8"))
    check(len(accounts) == 2 and all(a["email"].endswith("@estudai.invalid") for a in accounts), "fictional_accounts_only")
    before = db_snapshot()
    clients = [login(a) for a in accounts]
    profiles = [call(c, "onboarding.php?action=status") for c in clients]
    check(all(p["onboarding_completo"] for p in profiles), "existing_onboarding_complete")
    uid, other_uid = [p["perfil"]["usuario_id"] for p in profiles]
    check(uid != other_uid, "distinct_profile_owners")
    anon = old.Client()
    call(anon, "plano-estudos.php?action=gerar_semana", "POST", expected=401, csrf=False)
    call(anon, "calendario-estudai.php?action=mes", expected=401)
    call(clients[0], "plano-estudos.php?action=gerar_semana", "POST", expected=419, csrf=False)
    saved = clients[0].token
    clients[0].token = "inert-invalid-token"
    call(clients[0], "plano-estudos.php?action=gerar_semana", "POST", expected=419)
    clients[0].token = saved
    call(clients[0], "plano-estudos.php?action=gerar_semana", "POST", expected=400, raw=b"{broken")
    call(clients[0], "plano-estudos.php?action=gerar_semana", "POST", expected=413, raw=b" " * 9000)
    blocked = [
        ("GET","plano-estudos.php?action=gerar_semana"),("POST","plano-estudos.php?action=carregar_ativo"),
        ("PUT","plano-estudos.php?action=gerar_semana"),("POST","plano-estudos.php?action=arquivar"),
        ("POST","plano-estudos.php?action=replanejar_semana"),("POST","plano-estudos.php?action=gerar_anual"),
        ("POST","plano-estudos.php?action=gerar_semana&action=arquivar"),
        ("POST","plano-estudos.php?action=gerar_semana&action[]=gerar_semana"),
        ("POST","calendario-estudai.php?action=atualizar_status"),("POST","tarefas-estudo.php?action=concluir"),
        ("GET","cron_gerar.php"),("GET","cron_revisao_semanal_ia.php"),("POST","diagnostico.php?action=gerar"),
        ("POST","ia.php"),("GET","simulados-planejados.php?action=listar"),("POST","exercicios-ia.php"),("POST","redacao.php")]
    for method,path in blocked:
        call(clients[0],path,method,{"usuario_id":other_uid,"plano_id":999999},expected=403)
    for path in ["calendario-estudai.php?action=mes&mes=2026-13","calendario-estudai.php?action=dia&data=2026-02-30",
                 "calendario-estudai.php?action=eventos&inicio=2026-11-20&fim=2026-11-10"]:
        call(clients[0],path,expected=400)
    for form,expected in [({},400),({"nome":"Fictional stage08 test","email":"invalid","senha":"inert-test-value"},400),
                          ({"nome":accounts[0]["name"],"email":accounts[0]["email"],"senha":accounts[0]["password"]},409)]:
        status,_,_ = clients[0].call("auth","cadastrar","POST",form)
        check(status==expected,"registration_validation_or_duplicate_no_insert",status)
    initial = call(clients[0],"plano-estudos.php?action=carregar_ativo")["plano"]
    check(resume or initial is None,"no_unexpected_existing_plan")
    check(db_snapshot()==before,"negative_tests_do_not_write")
    marker = private/"created_plan.private.json"
    if initial is not None:
        check(resume and marker.exists(),"resume_requires_recorded_plan")
        generated=json.loads(marker.read_text())["response"]
        check(initial["id"]==generated["plano"]["id"],"resume_reuses_recorded_plan")
    else:
        check(not marker.exists(),"never_regenerate_after_recorded_creation")
        loopback()
        generated=call(clients[0],"plano-estudos.php?action=gerar_semana","POST",{"usuario_id":other_uid,"perfil_id":other_uid,"plano_id":999999})
        check(generated.get("ok") and generated.get("plano"),"weekly_generation_success")
        with marker.open("x",encoding="utf-8") as f:json.dump(dict(response=generated,owner_uid=uid),f,ensure_ascii=True)
    plan=generated["plano"];tasks=plan["tarefas"]
    check(plan["origem"]=="fallback" and generated["fallback_usado"],"honest_fallback_origin")
    check(all(t["usuario_id"]==uid for t in tasks),"payload_cannot_choose_another_owner")
    check(plan["tipo_plano"]==plan["escopo"]=="semanal","weekly_type_and_scope")
    check(len(tasks)>0,"plan_has_tasks")
    profile=profiles[0]["perfil"];slots=set();days=["segunda","terca","quarta","quinta","sexta","sabado","domingo"]
    for t in tasks:
        day=datetime.date.fromisoformat(t["data_prevista"])
        check(generated["janela"]["hoje"]<=day.isoformat()<=(profile["data_prova"] or plan["semana_fim"]),"valid_task_date")
        check(t["materia"] in profile["materias_base"],"selected_subject")
        check(any(datetime.time.fromisoformat(t["hora_inicio"])>=datetime.time.fromisoformat(b["inicio"]) and datetime.time.fromisoformat(t["hora_fim"])<=datetime.time.fromisoformat(b["fim"]) for b in profile["disponibilidade"][days[day.weekday()]]),"saved_availability")
        start,end=[datetime.time.fromisoformat(t[k]) for k in ["hora_inicio","hora_fim"]]
        duration=end.hour*60+end.minute-start.hour*60-start.minute
        check(t["tempo_estimado"]==duration and duration>0,"duration_matches_hours")
        check(t["status"]=="pendente" and t["origem"]=="manual" and t["metadata"]["origem_geracao"]=="fallback","task_status_and_origin")
        key=(t["data_prevista"],t["hora_inicio"],t["hora_fim"]);check(key not in slots,"unique_task_slot");slots.add(key)
    data=integrity(uid)
    counts={"planos_estudo":1,"plano_versoes":1,"planejamento_semanal_controle":1,
            **{t:len(tasks) for t in ["plano_estudo_itens","tarefas_estudo","eventos_calendario_estudai"]}}
    check(data["counts"]==counts and all(v==0 for k,v in data.items() if k.startswith("bad_")),"persistence_and_referential_integrity")
    check(data['owned_plan_profile']==profiles[0]['perfil']['id'],"plan_uses_own_profile")
    check(all(v==0 for v in integrity(other_uid)['counts'].values()),"forged_payload_does_not_create_other_user_plan")
    after=db_snapshot()
    if not resume:check({t for t in before if before[t]!=after[t]}==set(TABLES),"only_six_authorized_tables_changed")
    repeat=call(clients[0],"plano-estudos.php?action=gerar_semana","POST")
    check(repeat["reutilizado"] and repeat["plano"]["id"]==plan["id"],"sequential_idempotence")
    check(db_snapshot()==after,"sequential_repeat_no_row_changes")
    extra=[login(accounts[0]),login(accounts[0])]
    check(extra[0].sid()!=extra[1].sid(),"independent_sessions_for_concurrency")
    loopback()
    holder=subprocess.Popen([old.PHP,"-d","display_errors=0","-d","log_errors=0","tests/planning_stage08.php","hold-lock",str(uid)],
                            cwd=ROOT,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    try:
        check(holder.stdout.readline().strip()=="LOCKED","profile_lock_acquired")
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            futures=[pool.submit(call,c,"plano-estudos.php?action=gerar_semana","POST") for c in extra]
            time.sleep(0.15);check(not any(f.done() for f in futures),"writers_wait_for_profile_lock")
            holder.stdin.write("release\n");holder.stdin.flush()
            results=[f.result(timeout=12) for f in futures]
        check(holder.wait(timeout=5)==0,"lock_released")
    finally:
        if holder.poll() is None:holder.terminate();holder.wait(timeout=5)
    check(all(d["reutilizado"] and d["plano"]["id"]==plan["id"] for d in results),"concurrent_idempotence")
    check(db_snapshot()==after,"concurrent_repeat_no_row_changes")
    for c in extra:c.call("auth","logout")
    check(call(clients[0],"plano-estudos.php?action=carregar_ativo")["plano"]==plan,"persisted_plan_recovered")
    check(call(clients[1],"plano-estudos.php?action=carregar_ativo&usuario_id="+str(uid)+"&plano_id="+str(plan["id"]))["plano"] is None,"nonempty_plan_isolation")
    day=tasks[0]["data_prevista"];month=day[:7];year=day[:4];fixture={}
    calendar=["calendario-estudai.php?action=mes&mes="+month,"calendario-estudai.php?action=ano&ano="+year,
              "calendario-estudai.php?action=dia&data="+day,
              "calendario-estudai.php?action=eventos&inicio="+plan["semana_inicio"]+"&fim="+plan["semana_fim"]]
    for path in calendar:
        response=call(clients[0],path)
        check(len(response["eventos"])==len(tasks),"calendar_cardinality")
        check({e["tarefa_id"] for e in response["eventos"]}=={t["id"] for t in tasks},"calendar_task_links")
        check(call(clients[1],path+"&usuario_id="+str(uid))["eventos"]==[],"nonempty_calendar_isolation")
        fixture[path]=response
    check(call(clients[0],"calendario-estudai.php?action=eventos&inicio=2026-01-01&fim=2026-01-07")["eventos"]==[],"other_week_empty")
    reads=["preferencias.php?action=carregar","questoes.php?action=carregar","historico.php","metas.php?action=carregar",
           "revisao.php?action=estatisticas","estatisticas.php?action=estudai_geral","conquistas.php?action=listar",
           "plano-estudos.php?action=carregar_ativo","tarefas-estudo.php?action=hoje","tarefas-estudo.php?action=semana",
           "tarefas-estudo.php?action=atrasadas","tarefas-estudo.php?action=recentes&limite=6"]
    for index,c in enumerate(clients):
        _,auth,_=c.call("auth","status")
        if index==0:
            fixture["auth.php?action=status"]=dict(auth,csrf_token="inert-test-token")
            fixture["onboarding.php?action=status"]=profiles[0]
        for path in reads:
            response=call(c,path)
            if index==0:fixture[path]=response
    fixture["plano-estudos.php?action=gerar_semana"]=generated
    check(db_snapshot()==after,"all_calendar_and_dashboard_GETs_read_only")
    for c in clients:
        c.call("auth","logout");call(c,"plano-estudos.php?action=carregar_ativo",expected=401)
    return dict(result="PASS",checks=checks,fixture=fixture,committed_plans=1,task_count=len(tasks),
                expected_counts=counts,read_queries_unchanged=True,concurrency_lock_test=True,
                registration_scope="Validation and duplicate rejection; no extra account",resumed=resume,ai_requests=0,visual_validation=False)

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--private-dir",required=True);parser.add_argument("--accounts",required=True)
    parser.add_argument("--resume",action="store_true");args=parser.parse_args()
    private,accounts=Path(args.private_dir).resolve(),Path(args.accounts).resolve()
    base=(Path(os.environ["LOCALAPPDATA"])/"EstudAI/Recovery").resolve()
    check(private.is_relative_to(base) and accounts.is_relative_to(base) and accounts.name=="test_accounts.private.json","private_paths")
    stamp=datetime.datetime.now().strftime("%Y%m%d_%H%M%S_%f");report=private/("planning_api_"+stamp+".json")
    try:
        data=run(private,accounts,args.resume);fixture=data.pop("fixture")
        fixture_path=private/("planning_fixture_"+stamp+".json")
        with fixture_path.open("x",encoding="utf-8") as f:json.dump(fixture,f,ensure_ascii=True)
        with report.open("x",encoding="utf-8") as f:json.dump(data,f,indent=2)
    except Exception as error:
        with report.open("x",encoding="utf-8") as f:json.dump(dict(result="FAIL",error=str(error),checks=checks),f,indent=2)
        raise SystemExit(str(error))
    print(json.dumps(dict(result=data["result"],checks=len(checks),committed_plans=1,task_count=data["task_count"],report=str(report),fixture=str(fixture_path),ai_requests=0)))

if __name__=="__main__":main()

