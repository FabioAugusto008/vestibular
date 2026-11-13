"""Exercise the private request ledger without calling any HTTP transport."""
import argparse
import concurrent.futures
import json
from pathlib import Path
import subprocess
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = r"C:\xampp\php\php.exe"
checks = []

def check(condition, name):
    if not condition:
        raise RuntimeError("Failed: " + name)
    checks.append({"test": name, "result": "PASS"})

def reserve(config, user=2):
    code = """require 'server/config/database.php';
if(DB_NAME!=='estudai_teste_2026'||DB_USER!=='estudai_teste_app')exit(10);
require 'server/services/ai/openrouterClient.php';
$_SESSION=['usuario_id'=>(int)$argv[2]];
try{$n=openrouterReserveRequest(json_decode($argv[1],true),'diagnostico');echo json_encode(['reserved'=>$n]);}
catch(OpenRouterException $e){echo json_encode(['category'=>$e->category]);}"""
    result = subprocess.run([PHP, "-d", "display_errors=0", "-r", code, json.dumps(config), str(user)], cwd=ROOT, capture_output=True, encoding="utf-8")
    if result.returncode:
        raise RuntimeError("Private budget inspection failed")
    return json.loads(result.stdout)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--private-dir", required=True)
    args = parser.parse_args()
    private = Path(args.private_dir).resolve()
    allowed = Path(r"C:\Users\player\AppData\Local\EstudAI\Recovery").resolve()
    check(private.is_relative_to(allowed), "private evidence directory")
    directory = private / ("budget-unit-" + uuid.uuid4().hex[:8])
    directory.mkdir()
    path = directory / "budget.json"
    config = {"allowed_users": [2], "budget_file": str(path), "max_requests": 1, "model": "tests/model:free"}
    live = private / "openrouter_budget.json"
    live_before = live.read_bytes()
    check(reserve(config, 1) == {"category": "user_scope"}, "unapproved user cannot reserve")
    check(not path.exists(), "scope rejection does not create a budget file")
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda _: reserve(config), range(2)))
    check(sum("reserved" in item for item in results) == 1, "concurrent reservations permit only one request")
    check(sum(item.get("category") == "budget_exhausted" for item in results) == 1, "concurrent excess is rejected")
    check(len(json.loads(path.read_text())["requests"]) == 1, "one reservation persisted")
    check(reserve(config) == {"category": "budget_exhausted"}, "repeated request cannot reset the cap")
    wrong = dict(config, budget_file=str(ROOT / "forbidden-budget.json"))
    check(reserve(wrong) == {"category": "budget_configuration"}, "budget cannot be placed in DocumentRoot")
    path.write_text("{", encoding="utf-8")
    check(reserve(config) == {"category": "budget_invalid"}, "corrupt budget fails closed")
    path.write_text(json.dumps({"requests": [], "stopped_after_error": True}), encoding="utf-8")
    check(reserve(config) == {"category": "provider_stopped"}, "provider failure blocks new reservations")
    check(live.read_bytes() == live_before, "real stage budget is untouched")
    report = {"result": "PASS", "checks": checks, "external_requests": 0, "mock_reservations": 1}
    with (directory / "result.json").open("x", encoding="utf-8") as handle:
        json.dump(report, handle, indent=2)
    print(json.dumps({"result": "PASS", "checks": len(checks), "external_requests": 0, "report": str(directory / "result.json")}))

if __name__ == "__main__":
    main()
