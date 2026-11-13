"""Local evidence only: hashes and sanitized configuration, never remote requests."""
import argparse
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PREVIOUS = Path(r'C:\Users\player\AppData\Local\EstudAI\Recovery\20261113_092526_stage13_20ed838b')
parser = argparse.ArgumentParser()
parser.add_argument('phase', choices=['before', 'after'])
parser.add_argument('--private-dir', required=True, type=Path)
args = parser.parse_args()
private = args.private_dir.resolve()
assert private.parent == PREVIOUS.parent and private != PREVIOUS

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

files = {p.relative_to(ROOT).as_posix(): {'bytes': p.stat().st_size, 'sha256': digest(p)}
         for p in ROOT.rglob('*') if p.is_file() and '.git' not in p.relative_to(ROOT).parts}
apache = {str(p): digest(p) for p in Path(r'C:\xampp\apache\conf').rglob('*.conf')}
budget = {name: digest(PREVIOUS / name) for name in ['openrouter_budget.json', 'openrouter_budget.json.results.jsonl']}
env = {}
for line in (ROOT / '.env').read_text(encoding='utf-8-sig').splitlines():
    if '=' in line and not line.lstrip().startswith('#'):
        key, value = line.split('=', 1)
        env[key.strip()] = value.strip().strip('"\'')
assert env['DB_NAME'] == 'estudai_teste_2026' and env['DB_USER'] == 'estudai_teste_app'
assert env['ESTUDAI_AI_DISABLED'].lower() == 'true'
assert 'SetEnv ESTUDAI_AI_DISABLED true' in (ROOT / '.htaccess').read_text()
out = {'files': files, 'apache': apache, 'budget': budget,
       'config': {k: env[k] for k in ['DB_NAME', 'DB_USER', 'ESTUDAI_AI_DISABLED', 'OPENROUTER_MODEL', 'OPENROUTER_TIMEOUT_SECONDS']},
       'git_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
       'git_status': subprocess.check_output(['git', 'status', '--short'], cwd=ROOT, text=True),
       'staged': subprocess.check_output(['git', 'diff', '--cached', '--name-only'], cwd=ROOT, text=True)}
if args.phase == 'before':
    source = private / 'source_before'
    source.mkdir()
    for name in files:
        if name == '.env' or name.startswith('storage/'):
            continue
        destination = source / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes((ROOT / name).read_bytes())
    previous = {v['path']: v for v in json.loads((PREVIOUS / 'project_after.json').read_text())}
    out['changed_since_stage13'] = [n for n, v in files.items() if n in previous and v['sha256'] != previous[n]['sha256']]
else:
    before = json.loads((private / 'evidence_before.json').read_text())
    assert before['apache'] == apache and before['budget'] == budget
    assert before['files']['.env'] == files['.env']
    assert before['git_head'] == out['git_head'] and not out['staged']
    old_log = before['files'].get('storage/logs/app.log')
    if old_log:
        assert hashlib.sha256((ROOT / 'storage/logs/app.log').read_bytes()[:old_log['bytes']]).hexdigest() == old_log['sha256']
    out['changed_files'] = [n for n, v in files.items() if n not in before['files'] or v != before['files'][n]]
    out['removed_files'] = sorted(set(before['files']) - set(files))
    assert not out['removed_files']
with (private / ('evidence_' + args.phase + '.json')).open('x', encoding='utf-8') as handle:
    json.dump(out, handle, indent=2)
print(json.dumps({'result': 'PASS', 'phase': args.phase, 'files': len(files), 'apache_configs': len(apache),
                  'AI_disabled': True, 'external_budget_unchanged': True,
                  'changed_files': out.get('changed_files', out.get('changed_since_stage13'))}))
