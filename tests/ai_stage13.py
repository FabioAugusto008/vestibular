"""Controlled AI HTTP contracts. Prepare uses disabled AI and one fictional essay."""
import argparse
import concurrent.futures
import datetime
import json
import os
from pathlib import Path
import subprocess
import questions_stage10 as core

ROOT = Path(__file__).resolve().parents[1]
checks = core.checks
check = core.check
GUARD = """require 'server/config/database.php';
if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app')exit(10);
$db=getDB();
if($db->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost')exit(11);
"""

def inspect(code):
    result = subprocess.run(
        [core.PHP, "-d", "display_errors=0", "-d", "log_errors=0", "-r", GUARD + code],
        cwd=ROOT, capture_output=True, encoding="utf-8",
    )
    if result.returncode:
        raise RuntimeError("Guarded AI inspection failed")
    return json.loads(result.stdout)

def snapshot():
    return inspect("""$o=[];
foreach(['estudo_perfis','planos_estudo','plano_versoes','planejamento_semanal_controle','plano_estudo_itens','tarefas_estudo','eventos_calendario_estudai','ia_historico','redacoes_enem','respostas_usuario','desempenho_diario','simulado_tentativas','simulado_respostas','simulados_planejados','simulados_planejados_respostas'] as $t)
$o[$t]=hash('sha256',json_encode($db->query('SELECT * FROM '.$t.' ORDER BY id')->fetchAll()));
echo json_encode($o);""")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("phase", choices=["prepare", "verify"])
    parser.add_argument("--private-dir", required=True)
    parser.add_argument("--accounts", required=True)
    args = parser.parse_args()
    private = Path(args.private_dir).resolve()
    accounts_path = Path(args.accounts).resolve()
    allowed = (Path(os.environ["LOCALAPPDATA"]) / "EstudAI/Recovery").resolve()
    check(private.is_relative_to(allowed) and accounts_path.is_relative_to(allowed), "private paths")
    accounts = json.loads(accounts_path.read_text())
    check(len(accounts) == 2 and all(a["email"].endswith("@estudai.invalid") for a in accounts), "existing fictional accounts")
    A, B, B2, anonymous = core.Client(), core.Client(), core.Client(), core.Client()
    A.login(accounts[0])
    B.login(accounts[1])
    B2.login(accounts[1])
    fixture_file = private / "ai_fixture.private.json"
    if args.phase == "prepare":
        check(not fixture_file.exists(), "do not repeat preparation")
        check(inspect("echo json_encode((require 'server/config/openrouter.php')['enabled']);") is False, "AI disabled for HTTP preparation")
        before = snapshot()
        for path in ["diagnostico.php?action=status", "diagnostico.php?action=carregar_ultimo", "redacao.php?action=listar"]:
            anonymous.call(path, status=401)
        for path in ["diagnostico.php?action=gerar", "redacao.php?action=salvar", "redacao.php?action=analisar"]:
            anonymous.call(path, "POST", {}, status=401)
            A.call(path, "POST", {}, status=419, csrf=False)
            saved = A.token
            A.token = "invalid"
            A.call(path, "POST", {}, status=419)
            A.token = saved
            A.call(path, status=403)
        for path, method in [
            ("diagnostico.php?action=apagar", "POST"),
            ("redacao.php?action=listar", "POST"),
            ("plano-estudos.php?action=replanejar_semana", "POST"),
            ("plano-estudos.php?action=gerar_anual", "POST"),
            ("revisao-semanal-ia.php?action=executar_manual", "POST"),
            ("cron_revisao_semanal_ia.php", "GET"), ("ia.php", "POST"),
        ]:
            A.call(path, method, {} if method == "POST" else None, status=403)
        A.call("diagnostico.php?action=gerar", "POST", {"action": "status"}, status=403)
        A.call("redacao.php?action=salvar", "POST", raw=b"{", status=400)
        for payload in [
            {}, {"tema": [], "texto": "texto"}, {"tema": "x", "texto": ""},
            {"tema": "x" * 181, "texto": "texto"},
            {"tema": "x", "texto": "x" * 12001},
            {"tema": "x", "texto": "texto", "redacao_id": []},
        ]:
            B.call("redacao.php?action=salvar", "POST", payload, status=400)
        B.call("redacao.php?action=analisar", "POST", {"tema": "x", "texto": "curto"}, status=400)
        B.call("redacao.php?action=analisar", "POST", {"tema": "x", "texto": "x" * 300, "redacao_id": 99999999}, status=404)
        B.call("diagnostico.php?action=gerar", "POST", {"usuario_id": 1}, status=503)
        A.call("plano-estudos.php?action=gerar_semana", "POST", {"usuario_id": 2})
        check(snapshot() == before, "negative requests and existing plan replay write nothing")
        essay = {
            "tema": "Texto ficticio teste13 - acesso a educacao",
            "texto": ("O acesso a educacao amplia a participacao social. A escola deve oferecer "
                      "oportunidades de leitura e estudo. Bibliotecas comunitarias e materiais "
                      "acessiveis ajudam estudantes a desenvolver autonomia. ") * 4,
        }
        saved = B.call("redacao.php?action=salvar", "POST", essay)
        essay["redacao_id"] = saved["redacao_id"]
        result = B.call("redacao.php?action=analisar", "POST", essay)
        check(result["persistido"] and result["origem"] == "fallback", "essay persisted with explicit local origin")
        check(result["analise"]["estimativa_nao_oficial"] is True, "essay guidance is non-official")
        stable = snapshot()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            replies = list(pool.map(lambda c: c.call("redacao.php?action=analisar", "POST", essay), [B, B2]))
        check(all(x["reutilizado"] and x["redacao_id"] == saved["redacao_id"] for x in replies), "concurrent repeated essay analysis reuses one result")
        A.call("redacao.php?action=salvar", "POST", essay, status=404)
        A.call("redacao.php?action=analisar", "POST", essay, status=404)
        check(A.call("redacao.php?action=listar")["redacoes"] == [], "essay belongs only to its owner")
        check(len(B.call("redacao.php?action=listar")["redacoes"]) == 1, "one fictional essay")
        check(snapshot() == stable, "GET and cached essay replay leave all data unchanged")
        core.private_write(private, "ai_fixture.private.json", {"essay_id": saved["redacao_id"], "essay": essay})
        core.private_write(private, "ai_frontend_fixture.json", {
            "redacao.php?action=analisar": result,
            "redacao.php?action=listar": B.call("redacao.php?action=listar"),
            "diagnostico.php?action=carregar_ultimo": {"ok": True, "resultado": None},
        })
    else:
        fixture = json.loads(fixture_file.read_text())
        before = snapshot()
        old = A.call("plano-estudos.php?action=carregar_ativo")["plano"]
        check(old["id"] == 2 and old["origem"] == "fallback" and len(old["tarefas"]) == 4, "original local plan preserved")
        own = B.call("plano-estudos.php?action=carregar_ativo")["plano"]
        check(own is not None and own["id"] != old["id"], "second account has its own plan")
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            repeats = list(pool.map(lambda client: client.call("plano-estudos.php?action=gerar_semana", "POST", {}), [B, B2]))
        check(all(x["reutilizado"] and x["plano"]["id"] == own["id"] for x in repeats), "plan replay does not call generation")
        B.call("redacao.php?action=analisar", "POST", fixture["essay"])
        check(A.call("redacao.php?action=listar")["redacoes"] == [], "essay remains private")
        diag = B.call("diagnostico.php?action=carregar_ultimo")
        check(A.call("diagnostico.php?action=carregar_ultimo")["resultado"] is None, "diagnostic remains private")
        if diag["resultado"]:
            repeated = B.call("diagnostico.php?action=gerar", "POST", {})
            check(repeated["reutilizado"], "successful diagnostic reused")
        for client in [A, B]:
            daily = client.call("questoes.php?action=carregar")
            check(daily["questoes"] == [], "new day remains empty without preparing another batch")
            history = client.call("historico.php")
            check(len(history["historico"]) == 1, "historical daily result preserved")
            check(client.call("estatisticas.php?action=geral")["geral"] == {
                "total_questoes": 2, "total_acertos": 1, "total_erros": 1, "percentual": 50
            }, "AI flows do not alter daily scores")
            client.call("estatisticas.php?action=evolucao&dias=14")
            check(client.call("metas.php?action=carregar")["meta"]["questoes_feitas"] == 2, "weekly daily-question progress preserved")
        check(A.call("revisao.php?action=estatisticas")["total"] == 1, "first account review remains pending")
        check(B.call("revisao.php?action=estatisticas")["total"] == 0, "second account review stays resolved")
        check(snapshot() == before, "all verification GETs and replays preserve persisted state")
    target = "ai_http_" + args.phase + "_" + datetime.datetime.now().strftime("%H%M%S_%f") + ".json"
    core.private_write(private, target, {"result": "PASS", "checks": checks, "phase": args.phase})
    print(json.dumps({"result": "PASS", "phase": args.phase, "checks": len(checks)}))

if __name__ == "__main__":
    main()
