"""Local auth/onboarding integration checks, exclusively on the clean test DB.

Passwords, cookies, CSRF tokens and password hashes are never printed. Generated
fictional credentials stay in the explicitly supplied private recovery folder.
No IA, dashboard, question generation, calendar or cron flow is executed.
"""

import argparse
import http.cookiejar
import json
import os
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
PHP = r"C:\xampp\php\php.exe"
BASE = "http://localhost/estudAi_1.1/"
CHECKS = []


def check(condition, name, status=None):
    if not condition:
        raise RuntimeError("Check failed: " + name)
    item = {"test": name, "result": "PASS"}
    if status is not None:
        item["http_status"] = status
    CHECKS.append(item)


def inspect_database(accounts=None):
    code = r"""
    require 'server/config/database.php';
    if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app'||DB_HOST!=='localhost'||(string)DB_PORT!=='3306'){exit(1);}
    $db=getDB();
    if($db->query('SELECT DATABASE()')->fetchColumn()!=='estudai_teste_2026'||$db->query('SELECT CURRENT_USER()')->fetchColumn()!=='estudai_teste_app@localhost'){exit(2);}
    $input=json_decode(file_get_contents('php://stdin'),true);
    $counts=[];
    foreach(['usuarios','estudo_perfis','onboarding_respostas'] as $table){$counts[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
    $verified=true;
    foreach($input['accounts']??[] as $account){
        $s=$db->prepare('SELECT senha FROM usuarios WHERE email=?');$s->execute([$account['email']]);$hash=$s->fetchColumn();
        $verified=$verified&&is_string($hash)&&$hash!==$account['password']&&password_verify($account['password'],$hash)&&password_get_info($hash)['algoName']==='bcrypt';
    }
    echo json_encode(['database'=>'estudai_teste_2026','account'=>'estudai_teste_app@localhost','counts'=>$counts,'password_hashes_verified'=>$verified,'grants'=>$db->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN)]);
    """
    process = subprocess.run([PHP, "-d", "display_errors=0", "-d", "log_errors=0", "-r", code],
                             input=json.dumps({"accounts": accounts or []}), text=True,
                             capture_output=True, cwd=PROJECT)
    check(process.returncode == 0, "pdo_guard_before_http")
    result = json.loads(process.stdout)
    check(len(result["grants"]) == 4, "table_scoped_grants")
    return result


def safe_json(data):
    if isinstance(data, dict):
        check(not ({"senha", "password", "hash", "dsn"} & {str(k).lower() for k in data}), "no_credential_fields")
        for value in data.values():
            safe_json(value)
    elif isinstance(data, list):
        for value in data:
            safe_json(value)
    elif isinstance(data, str):
        check(not any(x in data for x in ("SQLSTATE", "PDOException", "Fatal error", "Stack trace:", "C:\\xampp")),
              "no_technical_details")


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.jar))
        self.token = None

    def sid(self):
        return next((c.value for c in self.jar if c.name == "estudai_session"), None)

    def call(self, endpoint, action, method="GET", payload=None, csrf=True, json_body=False):
        check(endpoint in ("auth", "onboarding"), "endpoint_allowlist")
        headers = {"X-Requested-With": "XMLHttpRequest"}
        if csrf and self.token:
            headers["X-CSRF-Token"] = self.token
        body = None
        if payload is not None:
            if json_body:
                body = json.dumps(payload).encode("utf-8")
                headers["Content-Type"] = "application/json"
            else:
                body = urllib.parse.urlencode(payload).encode("ascii")
                headers["Content-Type"] = "application/x-www-form-urlencoded"
        request = urllib.request.Request(BASE + "server/api/" + endpoint + ".php?action=" + action,
                                         data=body, method=method, headers=headers)
        try:
            response = self.opener.open(request, timeout=12)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            status, response_headers = response.status, response.headers
            check(response_headers.get_content_type() == "application/json", "json_content_type", status)
            data = json.load(response)
        safe_json(data)
        if isinstance(data.get("csrf_token"), str):
            self.token = data["csrf_token"]
        return status, data, response_headers


def response_check(result, expected, name, success=None):
    status, data, headers = result
    check(status == expected, name, status)
    if success is not None:
        check(data.get("ok") is success, name + "_contract")
    return data


def denied_resources():
    paths = [".env", ".env.example", ".git/HEAD", ".git/config", "backup/", "backup/estudai.zip",
             "storage/logs/app.log", "database/schema.sql", "database/recovery/catalog_stage06.json",
             "server/config/database.php", "server/config/openrouter.php", "server/services/ai/openrouterClient.php",
             "server/api/cron_gerar.php", "server/api/cron_revisao_semanal_ia.php", "server/api/diagnostico.php",
             "server/api/questoes.php?action=carregar", "server/api/metas.php?action=carregar",
             "server/api/preferencias.php?action=carregar", "server/api/calendario-estudai.php?action=mes",
             "server/api/simulados-planejados.php?action=listar", "server/api/plano-estudos.php?action=carregar_ativo"]
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    for path in paths:
        try:
            response = opener.open(BASE + path, timeout=8)
        except urllib.error.HTTPError as error:
            response = error
        status = response.status
        response.close()  # Do not read an unexpectedly served private resource.
        check(status == 403, "http_denied_" + path, status)
    return len(paths)


def run(private, resume):
    before = inspect_database()
    denied = denied_resources()
    credentials_path = private / "test_accounts.private.json"
    if resume:
        accounts = json.loads(credentials_path.read_text(encoding="utf-8"))
        check(len(accounts) == 2, "resume_only_known_fictional_accounts")
    else:
        check(not credentials_path.exists() and before["counts"] == dict(usuarios=0, estudo_perfis=0, onboarding_respostas=0),
              "fresh_test_accounts_and_personal_tables")
        tag = secrets.token_hex(4)
        accounts = [dict(name="Pessoa Ficticia " + label + " - Etapa 06",
                         email="etapa06." + label.lower() + "." + tag + "@estudai.invalid",
                         password=secrets.token_urlsafe(30)) for label in ("A", "B")]
        with credentials_path.open("x", encoding="utf-8") as handle:
            json.dump(accounts, handle, indent=2)
    a, b, anonymous = Client(), Client(), Client()
    response_check(anonymous.call("onboarding", "status"), 401, "onboarding_status_requires_session")
    response_check(anonymous.call("onboarding", "carregar"), 401, "onboarding_load_requires_session")
    response_check(anonymous.call("onboarding", "salvar", "POST", {}, json_body=True), 401, "onboarding_save_requires_session")
    for client, account in ((a, accounts[0]), (b, accounts[1])):
        status = client.call("auth", "status")
        check(status[0] == 200 and status[1]["logado"] is False, "initial_anonymous_session")
        check(client.token and len(client.token) == 64, "csrf_token_available")
        if client is a:
            response_check(client.call("auth", "cadastrar", "POST", {"nome": "", "email": "", "senha": ""}), 400, "registration_required_fields")
            response_check(client.call("auth", "cadastrar", "POST", {"nome": account["name"], "email": "invalid-address", "senha": account["password"]}), 400, "registration_email_validation")
        form = {"nome": account["name"], "email": account["email"], "senha": account["password"]}
        result = client.call("auth", "cadastrar", "POST", form)
        check(result[0] in ((200, 409) if resume else (200,)), "registration_valid_or_known_resume", result[0])
        if result[0] == 200:
            check(set(result[1]) == {"ok", "mensagem"} and result[1]["ok"], "registration_json_contract")
        response_check(client.call("auth", "cadastrar", "POST", form), 409, "duplicate_email_rejected")
        response_check(client.call("auth", "login", "POST", {"email": account["email"], "senha": "intentionally-incorrect"}), 401, "wrong_password_rejected")
        sid_before = client.sid()
        data = response_check(client.call("auth", "login", "POST", {"email": account["email"], "senha": account["password"]}), 200, "login_valid", True)
        check(set(data) == {"ok", "nome", "csrf_token"}, "login_response_contract")
        check(sid_before and client.sid() != sid_before, "session_id_rotated_on_login")
        stale = Client()
        old_cookie = http.cookiejar.Cookie(0, "estudai_session", sid_before, None, False, "localhost.local", False, False, "/", True, False, None, True, None, None, {})
        stale.jar.set_cookie(old_cookie)
        check(stale.call("auth", "status")[1]["logado"] is False, "prelogin_session_cannot_authenticate")
        status, data, headers = client.call("auth", "status")
        check(status == 200 and data["logado"] is True and data["nome"] == account["name"], "authenticated_session_status")
        cookie = next(c for c in client.jar if c.name == "estudai_session")
        check(cookie.has_nonstandard_attr("HttpOnly") and cookie.get_nonstandard_attr("SameSite") == "Lax", "cookie_httponly_samesite_lax")
        check(not cookie.secure, "cookie_http_loopback_transport_documented")
    verified = inspect_database(accounts)
    check(verified["counts"]["usuarios"] == 2 and verified["password_hashes_verified"], "only_two_fictional_accounts_with_bcrypt")
    if not resume or verified["counts"]["estudo_perfis"] == 0:
        for client in (a, b):
            data = response_check(client.call("onboarding", "status"), 200, "onboarding_initial_status", True)
            check(data["onboarding_completo"] is False and data["perfil"] is None, "onboarding_initial_incomplete")
            data = response_check(client.call("onboarding", "carregar"), 200, "onboarding_empty_load", True)
            check(data["perfil"] is None and data["respostas"] is None, "onboarding_initial_state_empty")
    response_check(a.call("onboarding", "salvar", "POST", {}, csrf=False, json_body=True), 419, "onboarding_csrf_required")
    response_check(a.call("onboarding", "salvar", "POST", {}, json_body=True), 400, "onboarding_required_fields")
    response_check(a.call("onboarding", "salvar"), 405, "onboarding_save_rejects_get")
    response_check(a.call("onboarding", "salvar", "POST", {"modo_estudo": "enem"}, json_body=True), 400, "onboarding_enem_language_required")
    base = dict(modo_estudo="enem", lingua_estrangeira="ingles", data_prova="2026-11-17",
                disponibilidade={"segunda": [{"inicio": "18:00", "fim": "19:00"}]}, reforcos={"Matematica": 3},
                intensidade="leve", exercicios_dia="3_5", frequencia_simulados="mensal", notificacoes=False)
    invalid = dict(base, disponibilidade={"segunda": [{"inicio": "19:00", "fim": "18:00"}]})
    response_check(a.call("onboarding", "salvar", "POST", invalid, json_body=True), 400, "onboarding_time_validation")
    marker = secrets.token_hex(6)
    profile_ids = []
    responses_before = verified["counts"]["onboarding_respostas"]
    for client, label in ((a, "A-first"), (a, "A-latest"), (b, "B-only")):
        payload = dict(base, informacao_livre="Perfil exclusivamente ficticio " + label,
                       usuario_id=999999, respostas={"test_marker": marker + label})
        data = response_check(client.call("onboarding", "salvar", "POST", payload, json_body=True), 200, "onboarding_save_" + label, True)
        check(data["onboarding_completo"] is True and data["perfil"]["informacao_livre"].endswith(label), "onboarding_saved_profile_" + label)
        profile_ids.append(data["perfil"]["usuario_id"])
    check(profile_ids[0] == profile_ids[1] and profile_ids[0] != profile_ids[2] and 999999 not in profile_ids, "onboarding_session_owned_user_association")
    for client, label, other_id in ((a, "A-latest", profile_ids[2]), (b, "B-only", profile_ids[0])):
        data = response_check(client.call("onboarding", "carregar&usuario_id=" + str(other_id)), 200, "onboarding_cross_user_read_isolation", True)
        check(data["respostas"]["respostas"]["test_marker"] == marker + label, "onboarding_latest_answer_and_user_isolation")
        check(data["perfil"]["usuario_id"] != other_id, "onboarding_ignores_foreign_user_id")
    logged_out_sid = a.sid()
    response_check(a.call("auth", "logout"), 200, "logout", True)
    check(a.call("auth", "status")[1]["logado"] is False, "logout_invalidates_session")
    response_check(a.call("onboarding", "carregar"), 401, "protected_access_after_logout_rejected")
    replay = Client()
    replay.jar.set_cookie(http.cookiejar.Cookie(0, "estudai_session", logged_out_sid, None, False, "localhost.local", False, False, "/", True, False, None, True, None, None, {}))
    check(replay.call("auth", "status")[1]["logado"] is False, "logged_out_cookie_replay_rejected")
    response_check(a.call("auth", "login", "POST", {"email": accounts[0]["email"], "senha": accounts[0]["password"]}), 200, "login_again_after_logout", True)
    data = response_check(a.call("onboarding", "carregar"), 200, "onboarding_persists_after_new_login", True)
    check(data["respostas"]["respostas"]["test_marker"] == marker + "A-latest", "latest_response_persists_after_new_session")
    after = inspect_database(accounts)
    check(after["counts"] == dict(usuarios=2, estudo_perfis=2, onboarding_respostas=responses_before + 3), "expected_personal_test_counts_only")
    response_check(a.call("auth", "logout"), 200, "final_logout_A", True)
    response_check(b.call("auth", "logout"), 200, "final_logout_B", True)
    return dict(result="PASS", checks=CHECKS, before=before, after=after, fictional_accounts=2,
                denied_resources=denied, credentials_file=str(credentials_path),
                csrf_tokens_passwords_cookies_and_hashes_in_report=False, ai_endpoints_executed=False,
                dashboard_side_effect_flows_executed=False, visual_validation=False)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--private-dir", required=True)
    parser.add_argument("--resume", action="store_true", help="Reuse only the two known fictional accounts after investigating a failure")
    args = parser.parse_args()
    private = Path(args.private_dir).resolve()
    allowed = Path(os.environ["LOCALAPPDATA"]) / "EstudAI" / "Recovery"
    check(private.is_relative_to(allowed.resolve()) and private.is_dir(), "private_recovery_directory")
    report = private / ("api_validation_resume.json" if args.resume else "api_validation.json")
    check(not report.exists(), "never_overwrite_api_report")
    try:
        result = run(private, args.resume)
    except (RuntimeError, ValueError, OSError) as error:
        result = dict(result="FAIL", error=str(error), checks=CHECKS)
        with report.open("x", encoding="utf-8") as handle:
            json.dump(result, handle, indent=2)
        raise SystemExit(str(error))
    with report.open("x", encoding="utf-8") as handle:
        json.dump(result, handle, indent=2)
    print(json.dumps(dict(result=result["result"], checks=len(CHECKS), fictional_accounts=2,
                          counts=result["after"]["counts"], report=str(report), visual_validation=False)))


if __name__ == "__main__":
    main()
