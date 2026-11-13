"""Run existing local suites with private fixtures. Never runs external AI or prepare phases."""
import argparse
import concurrent.futures
import datetime
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
RECOVERY = Path(r'C:\Users\player\AppData\Local\EstudAI\Recovery')
OLD = RECOVERY / '20261113_092526_stage13_20ed838b'
PHP = r'C:\xampp\php\php.exe'
NODE = r'C:\Program Files\nodejs\node.exe'
ACCOUNTS = RECOVERY / '20261107_102629_f08677b1/test_accounts.private.json'
DASH = RECOVERY / '20261111_090948_2bb36209/dashboard_fixture_20261111_094925_795626.json'
PLAN = DASH.parent / 'planning_fixture_20261111_094611_223542.json'
PRACTICE = RECOVERY / '20261111_194627_stage11_bd30cd6d/planned_frontend_fixture.json'
SIM = RECOVERY / '20261111_204714_stage12_e851887b/simulation_frontend_fixture.json'
parser = argparse.ArgumentParser()
parser.add_argument('group', choices=['unit', 'http', 'transactions', 'browser'])
parser.add_argument('--private-dir', required=True, type=Path)
args = parser.parse_args()
private = args.private_dir.resolve()
assert private.parent == RECOVERY and private != OLD
for name in ['ai_fixture.private.json', 'ai_frontend_fixture.json', 'planned_fixture.private.json',
             'simulation_fixture.private.json', 'simulation_attempts.private.json', 'real_plan_result.json',
             'openrouter_budget.json', 'openrouter_budget.json.results.jsonl']:
    target = private / name
    if not target.exists(): target.write_bytes((OLD / name).read_bytes())

if args.group == 'unit':
    commands = [[PHP, p] for p in ['tests/ai_stage13_1.php', 'tests/openrouter_stage13.php', 'tests/ai_stage13.php', 'tests/planning_stage08.php']]
    commands += [[NODE, 'tests/' + p, str(DASH), *extra] for p, extra in [
        ('dashboard_initialization.test.js', []),
        ('questions_frontend.test.js', []), ('planned_exercises_frontend.test.js', [str(PRACTICE)]),
        ('simulations_frontend.test.js', [str(SIM)]), ('ai_frontend_stage13.test.js', [str(private / 'ai_frontend_fixture.json'), str(PLAN)])]]
    commands += [[NODE, 'tests/planning_frontend.test.js', str(PLAN)], [NODE, 'tests/service_worker_stage09.test.js']]
elif args.group == 'transactions':
    commands = [[PHP, 'tests/ai_transactions_stage13.php'], [PHP, 'tests/ai_transactions_stage13_1.php'],
                [sys.executable, '-B', 'tests/ai_budget_stage13.py', '--private-dir', str(private)]]
elif args.group == 'http':
    commands = [[sys.executable, '-B', 'tests/' + p, *extra, '--private-dir', str(private)] for p, extra in [
        ('ai_stage13.py', ['verify', '--accounts', str(ACCOUNTS)]),
        ('ai_http_stage13_1.py', ['--accounts', str(ACCOUNTS)]),
        ('planned_exercises_stage11.py', ['--verify-only', '--accounts', str(ACCOUNTS)]),
        ('simulations_stage12.py', ['verify', '--accounts', str(ACCOUNTS)]),
        ('http_stage09.py', ['--simulations-enabled']), ('recovery_configuration.py', [])]]
else:
    commands = [[NODE, 'tests/' + p, str(private), str(ACCOUNTS), *extra] for p, extra in [
        ('browser_stage13.mjs', []), ('browser_stage09.mjs', ['--simulations-enabled', '--ai-planning-enabled'])]]

def run(command):
    result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8', timeout=240)
    try: data = json.loads(result.stdout)
    except ValueError: data = {'result': 'FAIL', 'stdout': result.stdout[:8000], 'stderr': result.stderr[:8000]}
    ok = result.returncode == 0 and data.get('result') == 'PASS'
    return {'suite': next(p for p in command[1:] if p.startswith('tests/')), 'passed': ok, 'exit_code': result.returncode, 'data': data}

if args.group == 'unit':
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: results = list(pool.map(run, commands))
else:
    results = []
    for command in commands:
        result = run(command)
        results.append(result)
        if not result['passed']: break
stamp = datetime.datetime.now().strftime('%H%M%S_%f')
target = private / f'{args.group}_{stamp}.json'
target.write_text(json.dumps(results, indent=2), encoding='utf-8')
for r in results:
    data = r['data']
    count = data.get('checks', data.get('scenarios', data.get('tests')))
    print(json.dumps({'suite': r['suite'], 'passed': r['passed'], 'checks': len(count) if isinstance(count, list) else count}))
print(json.dumps({'report': str(target), 'result': 'PASS' if all(r['passed'] for r in results) else 'FAIL'}))
sys.exit(0 if all(r['passed'] for r in results) else 1)
