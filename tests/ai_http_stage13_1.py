"""Local negative API tests; no new plan, essay or external AI request."""
import argparse
import datetime
import json
from pathlib import Path
import ai_stage13 as ai
import questions_stage10 as core

parser = argparse.ArgumentParser()
parser.add_argument('--private-dir', required=True, type=Path)
parser.add_argument('--accounts', required=True, type=Path)
args = parser.parse_args()
allowed = Path(r'C:\Users\player\AppData\Local\EstudAI\Recovery')
assert args.private_dir.resolve().is_relative_to(allowed) and args.accounts.resolve().is_relative_to(allowed)
assert ai.inspect("echo json_encode((require 'server/config/openrouter.php')['enabled']);") is False
accounts = json.loads(args.accounts.read_text())
assert len(accounts) == 2 and all(a['email'].endswith('@estudai.invalid') for a in accounts)
A, B, anonymous = core.Client(), core.Client(), core.Client()
A.login(accounts[0]); B.login(accounts[1])
before = ai.snapshot()
for path in ['diagnostico.php?action=status', 'diagnostico.php?action=carregar_ultimo', 'redacao.php?action=listar']:
    anonymous.call(path, status=401)
for path in ['diagnostico.php?action=gerar', 'redacao.php?action=salvar', 'redacao.php?action=analisar', 'plano-estudos.php?action=gerar_semana']:
    anonymous.call(path, 'POST', {}, status=401)
    A.call(path, 'POST', {}, status=419, csrf=False)
    token = A.token
    A.token = 'invalid'
    A.call(path, 'POST', {}, status=419)
    A.token = token
    A.call(path, status=403)
for path in ['diagnostico.php?action=apagar', 'plano-estudos.php?action=replanejar_semana', 'plano-estudos.php?action=gerar_anual', 'revisao-semanal-ia.php?action=executar_manual', 'ia.php']:
    A.call(path, 'POST', {}, status=403)
A.call('cron_revisao_semanal_ia.php', status=403)
A.call('diagnostico.php?action=gerar', 'POST', {'action': 'status'}, status=403)
for path in ['redacao.php?action=salvar', 'diagnostico.php?action=gerar', 'plano-estudos.php?action=gerar_semana']:
    A.call(path, 'POST', raw=b'{', status=400)
A.call('redacao.php?action=salvar', 'POST', raw=b'x' * 65537, status=413)
for payload in [{}, {'tema': [], 'texto': 'texto'}, {'tema': 'x', 'texto': ''}, {'tema': 'x' * 181, 'texto': 'texto'}, {'tema': 'x', 'texto': 'x' * 12001}, {'tema': 'x', 'texto': 'texto', 'redacao_id': []}]:
    B.call('redacao.php?action=salvar', 'POST', payload, status=400)
B.call('redacao.php?action=analisar', 'POST', {'tema': 'x', 'texto': 'curto'}, status=400)
B.call('redacao.php?action=analisar', 'POST', {'tema': 'x', 'texto': 'x' * 300, 'redacao_id': 99999999}, status=404)
result = B.call('diagnostico.php?action=gerar', 'POST', {'usuario_id': 1}, status=503)
core.check(result['origem'] == 'indisponivel', 'disabled_diagnostic_never_reports_AI_success')
for client in [A, B]:
    result = client.call('plano-estudos.php?action=gerar_semana', 'POST', {'usuario_id': 999})
    core.check(result['reutilizado'] and result['origem'] == 'fallback', 'existing_plan_reused_without_generation')
core.check(ai.snapshot() == before, 'negative_requests_and_replays_change_no_rows')
A.call('auth.php?action=logout', 'POST', {})
A.call('diagnostico.php?action=carregar_ultimo', status=401)
target = 'ai_http_negative_' + datetime.datetime.now().strftime('%H%M%S_%f') + '.json'
core.private_write(args.private_dir, target, {'result': 'PASS', 'checks': core.checks, 'external_AI_requests': 0, 'committed_rows': 0})
print(json.dumps({'result': 'PASS', 'checks': len(core.checks), 'external_AI_requests': 0}))
