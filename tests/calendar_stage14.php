<?php
require_once __DIR__ . '/ai_stage13_bootstrap.php';
ai13LoadFunctions(__DIR__ . '/../server/api/calendario-estudai.php');
$row = ['id'=>1, 'tarefa_id'=>3, 'plano_id'=>2, 'titulo'=>'Atividade ficticia', 'tipo'=>'exercicio',
    'data_evento'=>'2026-11-16', 'hora_inicio'=>null, 'hora_fim'=>null, 'status'=>'pendente',
    'metadata_json'=>'{"fixture":"existing"}',
    'tarefa_contexto_json'=>'{"materia":"Matematica","tipo_tarefa":"questoes","tempo_estimado":30,"prioridade":"media"}'];
$result = calendarioMapEvento($row);
if (($result['metadata']['tempo_estimado'] ?? 0) !== 30 || ($result['metadata']['materia'] ?? '') !== 'Matematica') throw new RuntimeException('Existing linked task context lost');
if ($result['metadata']['fixture'] !== 'existing') throw new RuntimeException('Existing metadata lost');
$row['metadata_json'] = '{"tempo_estimado":60,"materia":"Redacao"}';
$result = calendarioMapEvento($row);
if ($result['metadata']['tempo_estimado'] !== 60 || $result['metadata']['materia'] !== 'Redacao') throw new RuntimeException('Existing explicit metadata replaced');
$row['tarefa_contexto_json'] = null;
if (calendarioMapEvento($row)['metadata']['tempo_estimado'] !== 60) throw new RuntimeException('Unlinked event regressed');
echo json_encode(['result'=>'PASS','checks'=>4,'external_requests'=>0,'database_writes'=>0]);
