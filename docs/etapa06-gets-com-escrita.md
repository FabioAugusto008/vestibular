# GETs com efeitos de escrita — inspeção da Etapa 06

Somente inspeção estática. Estes endpoints continuam negados pelo Apache e pelo
`.htaccess`; as solicitações negativas receberam 403 antes da execução PHP.
Nenhuma das rotinas abaixo foi executada nos bancos preservados ou no novo.

| Endpoint / ação GET | Escrita localizada | Entrada pelo frontend | Risco | Proposta mínima futura |
| --- | --- | --- | --- | --- |
| `questoes.php?action=carregar` (e demais ações, antes do switch) | linha 51 chama `gerarQuestoesDodia`; `helpers.php` contém `INSERT IGNORE INTO questoes_do_dia` | `loadQuestoes`, na inicialização com onboarding completo e após salvar onboarding | Geração de lote em uma leitura; concorrência e mudança diária implícita | Separar preparação explícita/autorizada por POST e manter GET de consulta sem escrita |
| `metas.php?action=carregar` | linhas 52/63: UPDATE do progresso ou INSERT da meta semanal | `loadMeta`, na inicialização completa e após onboarding/respostas | Atualização/criação involuntária; corrida na primeira criação | Retornar cálculo agregado sem persistir em GET; salvar por POST explícito |
| `preferencias.php?action=carregar` | linha 28: INSERT de preferências quando ausentes | `loadPreferencias`, na inicialização completa e após onboarding | Criação implícita e corrida entre requisições | Retornar defaults transitórios; persistir por POST autorizado |
| `calendario-estudai.php?action=mes/ano/dia/eventos` | linha 143 chama `calendarioSyncTarefas`; linha 81 insere eventos ausentes | Abertura do calendário e recargas posteriores a alterações de tarefas | Sincronização implícita e possível duplicação concorrente por checagem seguida de INSERT | Sincronizar em comando POST idempotente; GET somente leitura |
| `simulados-planejados.php?action=listar/carregar` | linha 335 e funções de carga chamam `simPlanReleaseDue`; linha 89 atualiza status para liberado | Abertura da seção simulados e recargas do planejamento | Avanço de estado causado por leitura | Calcular disponibilidade na leitura ou executar liberação explícita/autorizada |

`app.js`, na inicialização (linhas 195–229), verifica a sessão e o onboarding.
Se ele estiver incompleto, abre o assistente e retorna antes de carregar os
GETs acima. Com onboarding completo, inicia preferências, questões e metas.

Após o salvamento (região da linha 710), o frontend também tenta diagnóstico e
carregamentos do painel. Nesta etapa esses endpoints permanecem bloqueados;
o backend de onboarding em si não depende de IA e foi validado separadamente.
Não foi alterado JavaScript para mascarar esses bloqueios. A interface completa
e o painel ainda não estão aprovados.

`loadEstudaiCore` agrega estatísticas, diagnóstico salvo, plano e rotina.
Calendário e simulados são carregados ao abrir suas seções e em recargas de
planejamento/tarefas; não são necessários ao cadastro ou onboarding básico.

Os dois crons permanecem totalmente bloqueados. `cron_gerar.php` pode gerar
questões sem método POST; `cron_revisao_semanal_ia.php` aceita autorização local
e pode atualizar planos/revisões. Sua liberação exige etapa específica.

O logout atual usa GET e altera somente a sessão PHP; foi mantido para preservar
o contrato atual. Migrar logout para POST com CSRF é uma melhoria posterior,
sem liberar os demais endpoints nesta etapa.
