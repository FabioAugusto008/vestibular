# EstudAI — capturas reais para a apresentação de 17/11/2026

Capturadas em 13/11/2026, no Chrome real, usando exclusivamente contas fictícias do banco estudai_teste_2026. Layout original preservado. Nenhum resultado foi montado, gerado por imagem de IA ou inserido artificialmente na tela.

**Entrega principal:** [screenshots.zip](screenshots.zip), com somente os 12 PNGs aprovados. Pasta completa: `C:\xampp\htdocs\estudAi_1.1\docs\apresentacao-2026\screenshots`.

As seis prioridades estão nos arquivos 01, 02, 03, 04, 06 e 08. Para slides 16:9, começar por 01, 04 e 06, em 1920×1080. Telas longas foram capturadas por inteiro para preservar tarefas, calendário, detalhes e avisos; inserir com proporção preservada ou distribuir entre slides. Não esticar as imagens. A captura 07 é a região real do resultado, renderizada em alta resolução pelo próprio Chrome, sem montagem ou edição posterior.

## Arquivos e estado real

| Arquivo PNG | Resolução | Funcionalidade e contexto real |
| --- | --- | --- |
| [01-dashboard.png](screenshots/01-dashboard.png) | 1920×1080 | Conta fictícia A: saudação, indicadores, plano ativo e atalhos. Semana atual de 09–15/11 sem tarefas; resultado diário histórico de 11/11. |
| [02-planejamento.png](screenshots/02-planejamento.png) | 1905×1535 | Plano A existente de 16/11: quatro tarefas, duas concluídas, 50%. Origem FALLBACK e aviso de IA desativada mantidos. |
| [03-calendario.png](screenshots/03-calendario.png) | 1905×2036 | Novembro de 2026, quatro eventos de A em 16/11, detalhes e visão anual. Matérias/durações das tarefas vinculadas recuperadas por leitura. |
| [04-questoes.png](screenshots/04-questoes.png) | 1920×1080 | Questão existente da base, enunciado e cinco alternativas completos. Lote normal de 13/11 com 20 questões, 0 respondidas. Sem gabarito antecipado. |
| [05-correcao.png](screenshots/05-correcao.png) | 1920×1080 | Prática planejada já concluída de A: cinco respostas salvas, quatro acertos. Questão errada anterior e explicação preservadas; nenhuma resposta alterada. |
| [06-simulados.png](screenshots/06-simulados.png) | 1920×1080 | Simulado planejado finalizado e catálogo convencional real. O catálogo de teste insuficiente permanece visível e indisponível, como realmente está. |
| [07-resultado-simulado.png](screenshots/07-resultado-simulado.png) | 1824×943 | Resultado convencional já persistido: 1/2, um acerto e um erro. Tempo 00:00 é o valor fictício previamente salvo, não medição de duração desta captura. |
| [08-estatisticas.png](screenshots/08-estatisticas.png) | 1905×1322 | Detalhamento expandido: duas questões diárias históricas, 50%, dois simulados finalizados; evolução dos últimos 14 dias com registro em 11/11. Agregados de tarefas da semana atual são zero. |
| [09-conquistas.png](screenshots/09-conquistas.png) | 1905×1547 | Catálogo real de 22 conquistas, nenhuma concedida, nível 1 e 0 XP. Mostra a interface; não comprova desbloqueio automático. Nenhuma conquista foi concedida artificialmente. |
| [10-rotina.png](screenshots/10-rotina.png) | 1905×1242 | Hoje/semana atual sem atividades; painel inferior mostra prática e simulado relacionados ao plano já concluídos. Datas anteriores não foram remarcadas. |
| [11-redacao.png](screenshots/11-redacao.png) | 1905×1937 | Conta fictícia B: redação já salva, feedback FALLBACK local e histórico. Aviso de orientação não oficial/IA indisponível mantido. Não é correção remota aprovada. |
| [12-perfil-diagnostico.png](screenshots/12-perfil-diagnostico.png) | 1920×1080 | Perfil fictício A, diagnóstico ainda não gerado e próximo compromisso de 16/11. A captura demonstra o estado disponível; não existe diagnóstico remoto validado. |

Todas as imagens usam dados fictícios. As contas A e B são as mesmas dos testes anteriores; não foram criadas contas novas. A redação usa B porque esse registro já estava disponível apenas para essa conta. Demais capturas usam A.

## Como explicar os números nos slides

- Questões diárias históricas: duas respondidas em 11/11, um acerto, um erro, 50%. O novo lote de 20 questões de 13/11 está sem respostas e não altera esse resultado.
- Prática planejada da captura 05: cinco respostas, quatro acertos, 80%, modalidade distinta do treino diário.
- Simulado convencional: duas questões, um acerto, 50%. O planejado anterior tem dez questões, nove acertos, 90%. São dois resultados finalizados independentes.
- Plano de 16/11: duas de quatro tarefas concluídas, 50%. A semana atual na data das capturas é 09–15/11 e possui zero tarefas; portanto, o painel semanal e o plano futuro usam períodos diferentes.
- Estatísticas por matéria/dificuldade referem-se ao fluxo diário; não somam automaticamente as práticas planejadas e os simulados. Zero em um recorte sem respostas não representa nota de uma tentativa realizada.
- Conquistas: zero desbloqueadas. Não apresentar essa tela como prova de concessão automática funcionando.

## Preparação mínima e correção aplicada

O único acréscimo confirmado no banco nesta etapa foi o lote do dia 13/11: **20 associações em questoes_do_dia**, criadas pelo botão normal “Preparar treino de hoje”, usando questões já cadastradas. O fluxo normal escolheu essa quantidade. Não foram inseridos enunciados, alternativas, respostas, resultados, planos, tarefas, eventos ou conquistas novos. Banco de testes passou de 334 para 354 registros; todas as linhas anteriores foram preservadas.

Foi necessária uma correção pontual em `server/api/calendario-estudai.php`: eventos antigos com metadados incompletos exibiam “Geral / 0 min” apesar da matéria/duração existentes na tarefa vinculada. A consulta de leitura recupera esses campos somente da tarefa do mesmo proprietário e conserva os metadados explícitos anteriores. Nenhum UPDATE/sincronização em GET foi introduzido. O teste específico reproduziu a falha e passou após a correção.

Arquivos de código desta etapa:

- Alterado: `server/api/calendario-estudai.php`.
- Criado: `tests/calendar_stage14.php`, quatro verificações sem rede ou escrita.
- Criado: `tests/capture_stage14.mjs`, automação do Chrome real em perfil privado isolado.

app.js, HTML, CSS, service worker, .env e .htaccess foram preservados. Não houve instalação de ferramentas. O script usa a inicialização CDP já empregada nas etapas anteriores. A primeira passagem parou após 11 imagens por seletor de logout incorreto no próprio script; foi corrigido para o botão existente, sem alteração do aplicativo. A passagem final capturou as 12 telas, e o resultado 07 recebeu enquadramento regional nativo posterior. Todos os Chromes isolados foram encerrados.

## Verificações mínimas e preservação

Aplicação e login fictício abriram normalmente; plano, calendário, questões e simulados foram carregados no Chrome real. Detalhes das estatísticas foram abertos pelo controle existente. As 12 imagens finais foram abertas e inspecionadas. Não contêm senha, token, email real, console, spinner pendente ou notificação temporária. Controles e avisos reais de fallback/indisponibilidade permanecem.

php -l, node --check, teste específico do calendário e git diff --check aprovados. Não foi reiniciada a bateria funcional completa. Auditoria final READ ONLY confirmou bancos históricos de 641 registros cada, schema e backup original preservados. Comparação por linha confirmou somente as 20 associações autorizadas no teste, nenhuma linha anterior modificada e privilégios inalterados.

Apache e MySQL80 permaneceram ativos, sem reinícios ou mudança global de configuração. Os 29 arquivos .conf do Apache conservaram seus hashes. Little Rio Manager respondeu HTTP 200 na porta 8082. ESTUDAI_AI_DISABLED=true e os arquivos do orçamento real de IA ficaram idênticos. Nenhuma chamada OpenRouter ou teste de IA foi executado.

Git permaneceu no HEAD 67a2fe0, índice vazio, sem commit, deploy ou descarte das alterações anteriores. Evidências técnicas privadas ficam fora do projeto, em `C:\Users\player\AppData\Local\EstudAI\Recovery\20261113_stage14_fc9dc766`; não integram o ZIP.

## Limitações para a apresentação

IA remota continua sem aprovação. Planejamento e redação exibidos são locais/fallback. O diagnóstico não foi gerado. Rótulos antigos como “Sugestão da IA” não comprovam inferência externa: nesta execução não houve chamada remota. Replanejamento, cron, anual e ações genéricas não liberadas continuam fora da demonstração aprovada, mesmo que seus botões apareçam.

Não foram fabricadas atividades para hoje, conquistas ou melhores notas. O catálogo insuficiente foi mantido. Algumas imagens são verticais por mostrarem seções completas; as resoluções estão na tabela para escolher o enquadramento no PowerPoint/Canva. Não foi criado arquivo PowerPoint nesta etapa.

## Respostas objetivas da entrega

1. EstudAI abriu normalmente: sim.
2. Login fictício funcionou: sim, contas A e B existentes.
3. Telas finais capturadas: 12.
4. Arquivos PNG: todos os 12 nomes solicitados, listados na tabela acima.
5. Pasta: `C:\xampp\htdocs\estudAi_1.1\docs\apresentacao-2026\screenshots`.
6. ZIP: criado, com somente os 12 PNGs aprovados.
7. Dados fictícios: sim, exclusivamente estudai_teste_2026.
8. Correção necessária: sim, recuperação de matéria/duração no calendário.
9. Código: um endpoint alterado e dois scripts de captura/teste criados, identificados acima.
10. Limitações: IA desativada, diagnóstico ausente, conquistas bloqueadas e períodos diferentes, explicitados acima.
11. Bancos históricos: preservados por auditoria READ ONLY.
12. Apache/Little Rio Manager: ativos; Little Rio respondeu 200.
13. IA: continua desativada, sem chamadas externas.
14. Git: alterações anteriores preservadas; sem commit/deploy ou alterações indevidas.
15. Imagens: prontas para inserir nos slides, respeitando as proporções e o contexto real descrito neste documento.
