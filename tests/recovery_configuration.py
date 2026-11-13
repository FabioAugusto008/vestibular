"""Configuration and safety regressions; credentials are never printed."""
import datetime
import json
import os
from pathlib import Path
import subprocess
import argparse

ROOT=Path(__file__).resolve().parents[1]
PHP=r"C:\xampp\php\php.exe"
tests=[]
base={k:v for k,v in os.environ.items() if not k.startswith(("DB_","DATABASE_","OPENROUTER_")) and k!="ESTUDAI_AI_DISABLED"}

def test(name,expression,env=None,setup=""):
    code="require 'server/config/database.php';"+setup+" echo json_encode((bool)("+expression+"));"
    p=subprocess.run([PHP,"-d","display_errors=0","-d","log_errors=0","-r",code],cwd=ROOT,env={**base,**(env or {})},capture_output=True,text=True)
    if p.returncode or p.stdout!="true":raise RuntimeError("Configuration check failed: "+name)
    tests.append(dict(test=name,result="PASS"))

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument("--private-dir",required=True);args=parser.parse_args()
    private=Path(args.private_dir).resolve();allowed=(Path(os.environ["LOCALAPPDATA"])/"EstudAI/Recovery").resolve()
    if not private.is_relative_to(allowed):raise RuntimeError("Invalid private report path")
    test("real_dotenv_identity","DB_NAME==='estudai_teste_2026'&&DB_USER==='estudai_teste_app'&&DB_HOST==='localhost'&&(string)DB_PORT==='3306'&&DB_PASS!==''")
    test("real_AI_disabled","(require 'server/config/openrouter.php')['enabled']===false")
    test("empty_password_preserved","DB_PASS===''",{"DB_PASS":""})
    test("environment_precedence","DB_NAME==='env_name'",{"DB_NAME":"env_name"})
    test("ENV_precedence","envValue('PROBE_STAGE08','fallback')==='env'",setup="$_ENV['PROBE_STAGE08']='env';$_SERVER['PROBE_STAGE08']='server';")
    test("SERVER_fallback","envValue('PROBE_STAGE08','fallback')==='server'",setup="$_SERVER['PROBE_STAGE08']='server';")
    test("default_value","envValue('PROBE_STAGE08','fallback')==='fallback'")
    test("DATABASE_URL_precedence","DB_NAME==='url_name'&&DB_USER==='url_user'&&DB_PASS==='url_pass'&&(string)DB_PORT==='3307'&&DB_SSL===true",{"DATABASE_URL":"mysql://url_user:url_pass@localhost:3307/url_name?ssl-mode=required","DB_NAME":"other"})
    test("DB_URL_fallback","DB_NAME==='fallback_url'",{"DB_URL":"mysql://test:inert@localhost:3306/fallback_url"})
    test("calendar_read_only_blocks_UPDATE","$blocked",setup="""
    require 'server/helpers/helpers.php';$_SERVER['REQUEST_METHOD']='GET';$_GET['action']='mes';
    $db=getDB();beginDashboardReadOnlyGet($db,['mes']);$blocked=false;
    try{$db->exec('UPDATE planos_estudo SET status=status WHERE 1=0');}
    catch(PDOException $e){$blocked=($e->errorInfo[1]??0)===1792;}
    finally{if($db->inTransaction())$db->rollBack();}
    """)
    test("historical_schemas_denied","$blocked===2",setup="""
    $db=getDB();$blocked=0;foreach(['vestibular_estudos','estudai_demo_2026'] as $schema){
    try{$db->query('SELECT COUNT(*) FROM '.$schema.'.usuarios');}
    catch(PDOException $e){if(in_array($e->errorInfo[1]??0,[1044,1142],true))$blocked++;}
    }
    """)
    test("AI_guard_stops_before_network","$blocked",setup="""
    require 'server/services/ai/openrouterClient.php';$blocked=false;
    try{openrouterChatCompletion([]);}
    catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'desativadas');}
    """)
    result=dict(result="PASS",checks=len(tests),tests=tests,ai_requests=0)
    target=private/("configuration_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S_%f")+".json")
    with target.open("x",encoding="utf-8") as f:json.dump(result,f,indent=2)
    print(json.dumps(dict(result="PASS",checks=len(tests),report=str(target),ai_requests=0)))

if __name__=="__main__":main()

