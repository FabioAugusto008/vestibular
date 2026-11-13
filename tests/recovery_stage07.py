"""Read-only dashboard HTTP checks using the two existing fictional accounts.

No registrations, onboarding saves, progress fixtures or cleanup DML. Reports
contain statuses and assertions; passwords/tokens/cookies are never printed.
"""
import argparse
import datetime
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import urllib.error
import urllib.request
from urllib.parse import unquote, urljoin, urlparse

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("previous", ROOT / "tests/recovery_stage06.py")
previous = importlib.util.module_from_spec(spec)
spec.loader.exec_module(previous)
BASE = "http://localhost/estudAi_1.1/"
READS = [
    ("preferencias.php?action=carregar", "preferencias"),
    ("questoes.php?action=carregar", "questoes"),
    ("historico.php", "historico"),
    ("metas.php?action=carregar", "meta"),
    ("revisao.php?action=estatisticas", "pendentes"),
    ("revisao.php?action=carregar&limite=20", "questoes"),
    ("estatisticas.php?action=estudai_geral", "perfil"),
    ("conquistas.php?action=listar", "conquistas"),
    ("plano-estudos.php?action=carregar_ativo", "plano"),
    ("tarefas-estudo.php?action=hoje", "tarefas"),
    ("tarefas-estudo.php?action=semana", "tarefas"),
    ("tarefas-estudo.php?action=atrasadas", "tarefas"),
    ("tarefas-estudo.php?action=recentes&limite=6", "tarefas"),
]
checks = []


def check(condition, name, status=None):
    if not condition:
        raise RuntimeError("Failed: " + name + (" (HTTP " + str(status) + ")" if status is not None else ""))
    checks.append(dict(test=name, result="PASS", http_status=status))


def request(client, path, method="GET", expected=200):
    req = urllib.request.Request(BASE + "server/api/" + path, method=method,
                                 headers={"X-Requested-With": "XMLHttpRequest"})
    try:
        response = client.opener.open(req, timeout=12)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        status = response.status
        check(status == expected, method + " " + path, status)
        if status == 403:
            return None
        check(response.headers.get_content_type() == "application/json", "json " + path)
        data = json.load(response)
        previous.safe_json(data)
        return data


def run(private, accounts_path, planning_enabled=False):
    code = r"""
    require 'server/config/database.php';
    if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||DB_HOST!=='localhost'){exit(1);}
    $db=getDB();
    if($db->query('SELECT DATABASE()')->fetchColumn()!=='estudai_teste_2026'||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost'){exit(2);}
    $ai=require 'server/config/openrouter.php';if($ai['enabled']!==false){exit(3);}
    echo json_encode(['database'=>'estudai_teste_2026','account'=>'estudai_teste_app@localhost','grants'=>$db->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN),'ai_disabled'=>true]);
    """
    p = subprocess.run([previous.PHP, "-d", "display_errors=0", "-d", "log_errors=0", "-r", code], cwd=ROOT,
                       capture_output=True, text=True)
    check(p.returncode == 0, "real_configuration_guard_before_HTTP")
    config = json.loads(p.stdout)
    check(len(config["grants"]) == (22 if planning_enabled else 18), "expected_recovery_grant_scope")
    accounts = json.loads(accounts_path.read_text(encoding="utf-8"))
    check(len(accounts) == 2 and all(a["email"].endswith("@estudai.invalid") for a in accounts), "existing_fictional_accounts_only")
    clients = [previous.Client(), previous.Client()]
    anonymous = previous.Client()
    status, data, _ = anonymous.call("auth", "status")
    check(status == 200 and data.get("logado") is False, "anonymous_auth_status", status)
    request(anonymous, "onboarding.php?action=status", expected=401)
    request(anonymous, "onboarding.php?action=carregar", expected=401)
    for path, _ in READS:
        request(anonymous, path, expected=401)
    fixture = {}
    profiles = []
    responses = []
    for index, (client, account) in enumerate(zip(clients, accounts)):
        client.call("auth", "status")
        old_sid = client.sid()
        status, _, _ = client.call("auth", "login", "POST", {"email": account["email"], "senha": "intentionally-incorrect"})
        check(status == 401, "wrong_password_rejected_" + str(index), status)
        status, data, _ = client.call("auth", "login", "POST", {"email": account["email"], "senha": account["password"]})
        check(status == 200 and data.get("ok") is True, "fictional_login_" + str(index), status)
        check(old_sid and client.sid() != old_sid, "session_rotated_" + str(index))
        cookie = next(c for c in client.jar if c.name == "estudai_session")
        check(cookie.has_nonstandard_attr("HttpOnly") and cookie.get_nonstandard_attr("SameSite") == "Lax", "session_cookie_flags_" + str(index))
        status, auth, _ = client.call("auth", "status")
        check(status == 200 and auth["logado"] and auth["nome"] == account["name"], "session_identity_" + str(index), status)
        status, profile, _ = client.call("onboarding", "status")
        check(status == 200 and profile["onboarding_completo"], "onboarding_unchanged_" + str(index), status)
        profiles.append(profile)
        responses.append({})
        if index == 0:
            fixture["auth.php?action=status"] = dict(auth, csrf_token="inert-test-token")
            fixture["onboarding.php?action=status"] = profile
        for path, key in READS:
            result = request(client, path)
            responses[index][path] = result
            check(result.get("ok") is True and key in result, "contract_" + path)
            if index == 0:
                fixture[path] = result
            if key in ("questoes", "historico") or (key == "tarefas" and not planning_enabled):
                check(result[key] == [], "empty_state_" + path)
            if key == "plano":
                if not planning_enabled or index == 1:
                    check(result[key] is None, "no_plan_is_created_for_unplanned_account")
                else:
                    check(result[key] is None or isinstance(result[key].get("id"), int), "existing_authorized_plan_contract")
            if key == "meta":
                check(result[key]["questoes_feitas"] == 0 and result[key]["concluida"] is False, "computed_goal_without_record")
            if path.startswith("conquistas"):
                check(result["total"] == 22 and result["desbloqueadas"] == 0, "catalog_read_without_awards")
            if path.startswith("estatisticas"):
                check(result["perfil"]["tem_perfil"] and (planning_enabled or result["tarefas"]["total_semana"] == 0), "profile_and_progress_contract")
    check(profiles[0]["perfil"]["usuario_id"] != profiles[1]["perfil"]["usuario_id"], "different_authenticated_profiles")
    for index, client in enumerate(clients):
        other_id = profiles[1-index]["perfil"]["usuario_id"]
        profile = request(client, "onboarding.php?action=carregar&usuario_id=" + str(other_id))
        check(profile["perfil"]["usuario_id"] == profiles[index]["perfil"]["usuario_id"], "foreign_user_id_ignored_" + str(index))
        for path, _ in READS:
            separator = "&" if "?" in path else "?"
            injected = request(client, path + separator + "usuario_id=" + str(other_id))
            check(injected == responses[index][path], "foreign_user_parameter_does_not_override_session_" + path)
    # Both checks exit before any onboarding DML; do not replay the stage 06 saves.
    status, _, _ = clients[0].call("onboarding", "salvar", "POST", {}, csrf=False, json_body=True)
    check(status == 419, "onboarding_csrf_still_required", status)
    status, _, _ = clients[0].call("onboarding", "salvar")
    check(status == 405, "onboarding_save_get_rejected", status)
    blocked = ["cron_gerar.php", "cron_revisao_semanal_ia.php?force=1", "diagnostico.php?action=gerar", "ia.php",
               "simulados-planejados.php?action=listar", "calendario-estudai.php?action=" + ("atualizar_status" if planning_enabled else "mes"),
               "plano-estudos.php?action=gerar", "metas.php?action=definir", "conquistas.php?action=verificar",
               "questoes.php?action=finalizar", "tarefas-estudo.php?action=concluir", "estatisticas.php?action=geral",
               "preferencias.php?action=carregar&action=salvar", "questoes.php?action=carregar&action[]=responder"]
    for path in blocked:
        request(clients[0], path, expected=403)
    for path, _ in READS:
        request(clients[0], path, method="POST", expected=403)
        request(clients[0], path, method="PUT", expected=403)
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    for path in [".env", ".env.example", ".git/HEAD", ".git/config", ".htaccess", "backup/", "backup/estudai.zip",
                 "storage/logs/app.log", "database/schema.sql", "database/recovery/catalog_stage06.json",
                 "tests/recovery_stage07.py", "docs/etapa06-gets-com-escrita.md", "server/config/database.php",
                 "server/config/openrouter.php", "server/services/ai/openrouterClient.php"]:
        try:
            response = opener.open(BASE + path, timeout=8)
        except urllib.error.HTTPError as error:
            response = error
        status = response.status
        response.close()
        check(status == 403, "private_file_denied_" + path, status)
    csp = "connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'"
    assets = {"app/public/index.html", "app/public/app.html", "app/public/sw.js"}
    html = (ROOT / "app/public/app.html").read_text(encoding="utf-8")
    for asset in re.findall(r'(?:src|href)="([^"]+)"', html):
        url = urlparse(urljoin(BASE + "app/public/app.html", asset))
        if url.netloc == "localhost" and url.path.startswith("/estudAi_1.1/"):
            assets.add(url.path.removeprefix("/estudAi_1.1/"))
    for path in sorted(assets):
        with opener.open(BASE + path, timeout=8) as response:
            check(response.status == 200 and response.read() == (ROOT / unquote(path)).read_bytes(), "public_asset_matches_disk_" + path, response.status)
            check(response.headers.get("Content-Security-Policy") == csp, "same_origin_CSP_" + path)
            check(response.headers.get("X-Content-Type-Options") == "nosniff", "nosniff_" + path)
    for client in clients:
        status, data, _ = client.call("auth", "logout")
        check(status == 200 and data["ok"], "logout_preserved", status)
        status, data, _ = client.call("auth", "status")
        check(status == 200 and data["logado"] is False, "logout_invalidates_session", status)
        request(client, "metas.php?action=carregar", expected=401)
    return dict(result="PASS", configuration=config, checks=checks, fixture=fixture,
                data_writes_performed=False, added_accounts=0, visual_validation=False,
                isolation_scope="Distinct existing profiles verified; progress modules exercised empty and SQL user filters reviewed.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--private-dir", required=True)
    parser.add_argument("--accounts", required=True)
    parser.add_argument("--planning-enabled", action="store_true", help="Stage 08: allow its explicit grants and an existing plan; never generate one")
    args = parser.parse_args()
    private, accounts = Path(args.private_dir).resolve(), Path(args.accounts).resolve()
    base = (Path(os.environ["LOCALAPPDATA"]) / "EstudAI/Recovery").resolve()
    check(private.is_relative_to(base) and accounts.is_relative_to(base) and accounts.name == "test_accounts.private.json", "private_artifact_paths")
    suffix = datetime.datetime.now().strftime("%Y%m%d_%H%M%S_%f")
    report = private / ("dashboard_api_" + suffix + ".json")
    try:
        result = run(private, accounts, args.planning_enabled)
    except (RuntimeError, ValueError, OSError) as error:
        with report.open("x", encoding="utf-8") as handle:
            json.dump(dict(result="FAIL", error=str(error), checks=checks), handle, indent=2)
        raise SystemExit(str(error))
    fixture = result.pop("fixture")
    fixture_path = private / ("dashboard_fixture_" + suffix + ".json")
    with fixture_path.open("x", encoding="utf-8") as handle:
        json.dump(fixture, handle, ensure_ascii=True)
    with report.open("x", encoding="utf-8") as handle:
        json.dump(result, handle, indent=2)
    print(json.dumps(dict(result="PASS", checks=len(checks), report=str(report), fixture=str(fixture_path), data_writes=False)))


if __name__ == "__main__":
    main()
