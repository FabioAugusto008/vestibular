"""Read-only HTTP/assets/security checks for stage 09; no login or writes."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.request
from urllib.parse import urljoin, urlparse, unquote

ROOT=Path(__file__).resolve().parents[1]
BASE="http://localhost/estudAi_1.1/"
CSP="connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'"
checks=[]

def check(ok,name,status=None):
    if not ok:raise RuntimeError("HTTP check failed: "+name)
    checks.append(dict(test=name,result="PASS",status=status))

def response(url,method="GET"):
    req=urllib.request.Request(url,method=method,headers={"X-Requested-With":"XMLHttpRequest"})
    try:return urllib.request.build_opener(urllib.request.ProxyHandler({})).open(req,timeout=8)
    except urllib.error.HTTPError as error:return error

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument("--private-dir",required=True);parser.add_argument("--simulations-enabled",action="store_true");args=parser.parse_args()
    private=Path(args.private_dir).resolve()
    check(private.is_relative_to((Path(os.environ["LOCALAPPDATA"])/"EstudAI/Recovery").resolve()),"private_report_path")
    assets={"app/public/index.html","app/public/app.html","app/public/offline.html","app/public/sw.js","app/public/manifest.webmanifest"}
    external=set()
    for name in ["app/public/index.html","app/public/app.html","app/public/offline.html"]:
        text=(ROOT/name).read_text(encoding="utf-8")
        for item in re.findall(r'(?:src|href)="([^"]+)"',text):
            url=urlparse(urljoin(BASE+name,item))
            if url.netloc=="localhost" and url.path.startswith("/estudAi_1.1/"):
                assets.add(url.path.removeprefix("/estudAi_1.1/"))
            elif url.scheme in ["http","https"]:external.add(url.scheme+"://"+url.netloc)
    manifest=json.loads((ROOT/"app/public/manifest.webmanifest").read_text(encoding="utf-8"))
    for item in manifest["icons"]:
        assets.add(urlparse(urljoin(BASE+"app/public/manifest.webmanifest",item["src"])).path.removeprefix("/estudAi_1.1/"))
    results=[]
    for name in sorted(assets):
        disk=(ROOT/unquote(name)).resolve()
        check(disk.is_relative_to(ROOT) and disk.is_file(),"local_asset_path_"+name)
        with response(BASE+name) as r:
            body=r.read()
            check(r.status==200 and body==disk.read_bytes(),"asset_bytes_"+name,r.status)
            check(r.headers.get("Content-Security-Policy")==CSP,"asset_CSP_"+name)
            check(r.headers.get("X-Content-Type-Options")=="nosniff","asset_nosniff_"+name)
            mime=r.headers.get_content_type()
            if name.endswith(".js"):check(mime in ["application/javascript","text/javascript"],"javascript_MIME_"+name)
            if name.endswith(".css"):check(mime=="text/css","stylesheet_MIME_"+name)
            if name.endswith(".png"):check(mime=="image/png","PNG_MIME_"+name)
            if name.endswith(".svg"):check(mime=="image/svg+xml","SVG_MIME_"+name)
            if name.endswith(".ico"):check(mime in ["image/x-icon","image/vnd.microsoft.icon"],"favicon_MIME")
            results.append(dict(path=name,status=r.status,content_type=mime,bytes=len(body),cache_control=r.headers.get("Cache-Control")))
    with response(BASE+"app/public/index.html?utm_source=chatgpt.com") as r:
        check(r.status==200 and r.read()==(ROOT/"app/public/index.html").read_bytes(),"tracking_query_same_static_HTML",r.status)
    private_paths=[".env",".env.example",".git/HEAD",".git/config",".htaccess","backup/estudai.zip","storage/logs/app.log",
                   "database/schema.sql","database/recovery/catalog_stage06.json","server/config/database.php","server/config/openrouter.php",
                   "tests/browser_stage09.mjs","docs/etapa08-planejamento-validacao.md"]
    denied=["server/api/cron_gerar.php","server/api/cron_revisao_semanal_ia.php","server/api/ia.php","server/api/diagnostico.php?action=gerar",
            "server/api/simulados-planejados.php?action=listar","server/api/redacao.php","server/api/exercicios-ia.php",
            "server/api/plano-estudos.php?action=gerar_semana","server/api/calendario-estudai.php?action=atualizar_status"]
    if args.simulations_enabled:
        denied[denied.index("server/api/simulados-planejados.php?action=listar")]="server/api/simulados-planejados.php?action=gerar_para_semana"
    for name in private_paths+denied:
        with response(BASE+name) as r:check(r.status==403,"private_or_unauthorized_"+name,r.status)
    for name in ["plano-estudos.php?action=carregar_ativo","calendario-estudai.php?action=mes","tarefas-estudo.php?action=hoje","onboarding.php?action=status"]:
        with response(BASE+"server/api/"+name) as r:
            check(r.status==401,"anonymous_requires_session_"+name,r.status)
            check(r.headers.get_content_type()=="application/json","anonymous_JSON_"+name)
    with response("http://localhost/favicon.ico","HEAD") as r:root_favicon_status=r.status
    with response("http://127.0.0.1:8082/") as r:
        text=r.read(131072).decode("utf-8",errors="replace")
        check(r.status==200 and "Little Rio Manager" in text,"other_project_Little_Rio_preserved",r.status)
    report=dict(result="PASS",checks=checks,assets=results,external_font_origins=sorted(external),root_favicon_status=root_favicon_status,
                root_favicon_outside_EstudAI=True,application_writes=False)
    target=private/("http_assets_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S_%f")+".json")
    with target.open("x",encoding="utf-8") as f:json.dump(report,f,indent=2)
    print(json.dumps(dict(result="PASS",checks=len(checks),assets=len(results),report=str(target),root_favicon_status=root_favicon_status,application_writes=False)))

if __name__=="__main__":main()

