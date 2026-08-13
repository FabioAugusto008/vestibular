<?php
// api/cron_gerar.php — Geração diária de questões via Cron Job / Tarefa Agendada
// Execute todo dia à meia-noite:
//   Linux Cron: 0 0 * * * php /caminho/para/vestibular/api/cron_gerar.php
//   Windows:    Tarefa Agendada apontando para este arquivo

require_once __DIR__ . '/../helpers/helpers.php';

$hoje = date('Y-m-d');

try {
    gerarQuestoesDodia($hoje);
    echo "[" . date('Y-m-d H:i:s') . "] Questões do dia $hoje geradas com sucesso.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erro ao gerar questões: " . $e->getMessage() . "\n";
    exit(1);
}
