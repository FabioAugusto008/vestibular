# Etapa 11 — Exercícios planejados e planejamento semanal

Data: 11/11/2026. Projeto: C:\xampp\htdocs\estudAi_1.1.
Apresentação prevista: 17/11/2026, Parque Tecnológico de São José dos Campos.

## 01. Resumo executivo

Etapa 11 tecnicamente concluída no escopo recuperado: abrir tarefa de questões do plano existente, preparar explicitamente um lote, responder, obter correção no servidor, recuperar tentativas em nova sessão e refletir a conclusão nos vínculos/progresso.

Foram criadas somente duas tarefas fictícias mínimas no plano de testes existente, seus itens/eventos, dois lotes e seis respostas. Não foi gerado outro plano ou conteúdo educacional. O lote completo teve cinco questões, 4 acertos e 1 erro; tarefa, item e evento foram concluídos juntos. O lote insuficiente ofereceu uma das cinco solicitadas, informou a redução e manteve a tarefa em andamento.

A integração retomada passou 241 verificações; a conferência final passou 159. Passaram 22 cenários novos de frontend, 35 verificações no Chrome real e as regressões pertinentes. Sete capturas foram inspecionadas.

Bancos históricos, todas as linhas anteriores do teste, catálogo, schema e backup preservados. Apache/Little Rio Manager permaneceram ativos. Sem IA externa, cron, simulados, dependências novas, redesign, commit ou deploy.

## 02. Estado inicial

Foram lidos os relatórios obrigatórios das Etapas 07–10, documentação de banco/segurança, riscos de GETs, testes e código efetivo. Git já tinha alterações válidas anteriores sem commit; HEAD 67a2fe0.

A configuração e a conexão real confirmaram estudai_teste_2026, estudai_teste_app@localhost, IA desativada. O inventário inicial tinha 144 arquivos fora do Git e 291 registros nas 42 tabelas do teste. SHOW GRANTS tinha 23 instruções.

O plano ID 2 continha uma tarefa de teoria de Redação em 16/11, não elegível para exercícios. Nenhum lote planejado ou resposta planejada existia. O perfil fictício solicita cinco questões por atividade. As respostas diárias, revisões e demais dados da Etapa 10 já estavam preenchidos e foram preservados.

Problemas localizados no endpoint bloqueado: criação em GET, gabarito antecipado, arquivamento antes de criar novo lote, DELETE/INSERT de respostas sem transação, gravação nos modos diário/revisão, ausência de conclusão vinculada e perda de recuperação de lotes concluídos.

## 03. Arquitetura dos exercícios planejados

O endpoint existente exercicios-ia.php foi mantido, com ações restritas. A lógica local foi isolada em plannedExercises.php para testar as transações sem executar o dispatcher HTTP. A validação/CSRF e bloqueio de perfil reutilizam helpers existentes; isso não executa os comandos de questões diárias.

| Função JS | Endpoint | Método | Ação | SQL principal | Tabelas | Permissões |
| --- | --- | --- | --- | --- | --- | --- |
| abrirExerciciosTarefa / loadExercisesForTask(false) | exercicios-ia.php | GET | carregar_por_tarefa | SELECT tarefa/plano/item, lote e respostas próprias | tarefas_estudo, planos_estudo, plano_estudo_itens, exercicios_planejados, respostas_exercicios_planejados | SELECT; READ ONLY |
| Consulta semanal existente | exercicios-ia.php | GET | carregar_semana | SELECT lotes do usuário/semana, vínculos e respostas | mesmas tabelas | SELECT; READ ONLY |
| regenerarExerciciosTarefa / loadExercisesForTask(true) | exercicios-ia.php | POST | gerar_por_tarefa | Bloqueio de perfil; SELECT elegíveis; INSERT de lote; UPDATE de início | estudo_perfis, tarefas_estudo, planos_estudo, plano_estudo_itens, questoes, questoes_alternativas, exercicios_planejados | SELECT; INSERT lote; UPDATE restrito |
| responderExercicio | exercicios-ia.php | POST | responder | SELECT snapshot/duplicidade; INSERT da resposta; UPDATE de conclusão quando aplicável | estudo_perfis, exercicios_planejados, respostas_exercicios_planejados, tarefas_estudo, plano_estudo_itens, eventos_calendario_estudai | SELECT; INSERT resposta; UPDATE restrito |
| loadPlanoAtivo / loadRotina / calendário | Endpoints existentes | GET | ações anteriores | SELECT dos estados persistidos | plano, tarefas, itens/eventos conforme contrato anterior | SELECT; READ ONLY |
| loadEstudaiStats | estatisticas.php | GET | estudai_geral | COUNT/SUM de respostas planejadas e tarefas, em campos separados | respostas_exercicios_planejados, tarefas_estudo e fontes anteriores | SELECT; READ ONLY |

O nome exercicios-ia.php é histórico: o caminho liberado usa somente questões aprovadas locais. Não chama o serviço OpenRouter.

## 04. Relação com planejamento semanal

O lote pertence a usuário, tarefa e plano. O servidor exige tarefa tipo questoes, plano ativo do mesmo usuário e item compatível quando associado. Informar usuario_id no corpo não troca o proprietário da sessão.

Foram acrescentadas duas fixtures manuais ao plano ID 2, em sua data de 16/11: prática por matéria de Matemática e prática de um conteúdo de Português. Cada uma recebeu um item e um evento. Não foi modificado o plano persistido original, sua versão, o controle semanal ou a tarefa/evento anteriores.

As fixtures têm horário não definido e 30 minutos estimados, para testar vínculos e estados. Não foram geradas pelo algoritmo e não representam nova validação da disponibilidade do perfil ou distribuição automática de horários.

Depois da conclusão de uma das três tarefas, o plano mostrou 33%. A prática insuficiente continuou em andamento e a teoria original continuou pendente.

## 05. Diagnóstico da base de questões

Revalidados pelos snapshots completos: 32 questões distintas, 160 alternativas, 13 matérias e 42 conteúdos. As questões/alternativas mantiveram conteúdo idêntico ao início.

O catálogo contém oito questões de Matemática; os conteúdos cobertos têm uma questão cada. Dez conteúdos cadastrados não têm questão. Todas as 32 questões estavam aprovadas, com cinco alternativas e uma correta, conforme auditoria anterior preservada.

A seleção mantém matéria, conteúdo quando definido, dificuldade quando especificada e status aprovado. Valida cinco alternativas distintas A–E, uma correta, textos e explicação preenchidos. Não usa o título da tarefa como se fosse conteúdo cadastrado.

## 06. Comportamento com quantidade insuficiente

Decisão implementada: permitir prática parcial claramente identificada, sem declarar a tarefa integralmente concluída.

| Filtro testado | Solicitadas | Disponíveis/retornadas |
| --- | --- | --- |
| Matemática, sem conteúdo, dificuldade mista | 5 | 5 das 8 elegíveis |
| Mesmo filtro | 8 | 8 |
| Mesmo filtro | 12 | 8, sem duplicação |
| Conteúdo de Português escolhido | 5, 8 ou 12 | 1 |
| Conteúdo inexistente | 5 | 0; nenhum relaxamento |
| Matemática difícil | 12 | 1; dificuldade preservada |

O lote persiste quantidade real e quantidade solicitada. Se houver redução, a API/UI informam “1 de 5” e que a tarefa permanecerá em andamento. Responder todos os itens oferecidos conclui o lote parcial, mas não a tarefa nem o evento.

Com zero elegíveis, o serviço retorna 409 antes de INSERT, com rollback. Esse caso foi comprovado na seleção e revisado no caminho de criação; não se criou uma terceira tarefa apenas para fabricar o cenário HTTP vazio.

Não há ampliação silenciosa para outra matéria/conteúdo/dificuldade. Não são duplicadas questões. Reabrir recupera o mesmo lote; não completa a diferença com conteúdo inventado nem substitui tentativas.

## 07. Correções implementadas

Retirada a criação automática por GET. A ação POST existente gerar_por_tarefa passou a preparar/reutilizar atomicamente, sem arquivamento destrutivo ou geração remota.

Removida a rotina que copiava respostas planejadas para respostas_usuario e questoes_respostas_usuario. Removido DELETE de resposta para substituição. A primeira tentativa é preservada; reenvio igual retorna o resultado original e diferente retorna 409.

Criadas transações para lote/início e para resposta/conclusão. Lotes concluídos continuam recuperáveis. A última resposta de lote completo atualiza lote, tarefa, item e evento na mesma transação.

No frontend: estado vazio com preparação explícita, guarda de clique duplo, descarte de resposta tardia de outra tarefa, campos respondidos bloqueados, mensagem de insuficiência e recarga dos agregados/plano/rotina após salvar.

Foi corrigida também a interpolação do onclick do botão Responder: as aspas JSON anteriores quebravam o atributo HTML. O Chrome confirmou seleção e envio reais.

## 08. Contratos frontend/backend

Mantidos os nomes de endpoint/ações e o envelope ok/lote, campos de questões, respostas e avaliacao. O lote recebeu quantidade_solicitada, insuficiente e resumo com contagem, acertos/erros, percentual e estados de conclusão.

GET pode retornar lote=null sem criar dados. POST gerar_por_tarefa retorna reutilizado para o mesmo lote. O nome da função regenerarExerciciosTarefa foi preservado; sua semântica autorizada é preparar/reabrir, não apagar ou arquivar tentativas.

O rótulo do botão existente mudou de “Gerar novamente” para “Preparar ou reabrir lote”, evitando prometer substituição. Nenhuma classe, CSS ou estrutura visual foi redesenhada.

## 09. Proteção do gabarito

Questões não respondidas não recebem resposta_correta nem explicacao. Alternativas públicas são textos e IDs, sem flags de acerto. O JSON interno completo do lote não é devolvido.

Após a tentativa própria, a API fornece acerto e avaliação com letra correta/explicação. GET posterior preserva o feedback apenas para as respostas daquele usuário.

A correção usa o snapshot privado persistido na criação do lote, não o gabarito enviado pelo cliente. Assim, reenvios recuperam a avaliação original. O caso de alteração administrativa posterior do catálogo não foi executado, pois o catálogo deve permanecer preservado.

## 10. Estrutura das tabelas

exercicios_planejados possui FKs de usuário, plano e tarefa, JSON interno, quantidade, nível, origem banco/manual e status ativo/arquivado/concluido. Não possui UNIQUE de lote por tarefa.

respostas_exercicios_planejados possui FKs de usuário/lote e UNIQUE usuario_id/exercicio_planejado_id/exercise_key. A associação com questão está no snapshot JSON; não existe FK direta dessa tabela para a questão.

O servidor valida propriedade dos vínculos, associação da chave ao lote e da alternativa à questão. Uma consulta SQL com JSON_TABLE conferiu que não existem respostas órfãs nem chaves sem questão correspondente.

Não foram alterados schema, FKs, índices, ENUMs ou contadores manualmente. A exclusão da opção AUTO_INCREMENT na comparação de DDL é necessária porque rollback pode consumir IDs.

## 11. Permissões MySQL

Adições somente em estudai_teste_2026 para estudai_teste_app@localhost:

| Tabela | Adição |
| --- | --- |
| exercicios_planejados | SELECT, INSERT; UPDATE somente status e atualizado_em |
| respostas_exercicios_planejados | INSERT; SELECT já existia |
| tarefas_estudo | UPDATE somente status, concluida_em e atualizada_em |
| plano_estudo_itens | UPDATE somente status e atualizado_em |
| eventos_calendario_estudai | UPDATE somente status e atualizado_em |

Foram executadas cinco instruções GRANT específicas. SHOW GRANTS passou de 23 para 24 entradas. Não houve DELETE, privilégio global/schema, acesso histórico, CREATE, ALTER, DROP ou GRANT OPTION novo.

O bloqueio do perfil usa permissões anteriores. A conta PHP continua distinta de root. A credencial administrativa foi usada em prompt protegido para auditoria/concessões e não foi gravada em arquivo.

## 12. Endpoints liberados

Somente estas ações de exercicios-ia.php foram liberadas no .htaccess e dispatcher PHP:

- GET carregar_por_tarefa.
- GET carregar_semana.
- POST gerar_por_tarefa, autenticado e com CSRF.
- POST responder, autenticado e com CSRF.

As permissões anteriores permaneceram. URL sem ação não libera implicitamente todo o endpoint.

## 13. Endpoints bloqueados

Continuam bloqueados simulados, cron, diagnóstico/IA remota, avaliar_aberta, administração, exclusões, arquivamento/regeneração destrutiva, mudanças genéricas de tarefas, reabrir/concluir manualmente tarefas, replanejamento e ações não auditadas.

A conclusão autorizada nesta etapa ocorre somente pela resposta final validada de lote suficiente. Não foi liberado tarefas-estudo.php?action=concluir.

## 14. GETs e efeitos colaterais

carregar_por_tarefa e carregar_semana usam validação de ação e transação READ ONLY. Consultar tarefa sem lote retorna null; navegar/recarregar não cria lote nem muda status.

Consultas com dados preenchidos foram comparadas por fingerprints. A conferência incluiu lotes, respostas, tarefas, itens, eventos e tabelas diárias/revisão. Reenvios idempotentes também mantiveram os snapshots.

As proteções anteriores de questões, histórico, metas, estatísticas, conquistas e calendário foram preservadas. O teste MySQL de READ ONLY continuou rejeitando UPDATE.

## 15. Teste de criação de lote

O plano anterior não tinha tarefa elegível. Foram criadas duas tarefas fictícias e seus quatro vínculos, sem novo plano. IDs das fixtures: tarefas 3/4, itens 3/4 e eventos 2/3; o registro privado identifica os vínculos.

Duas sessões PHP independentes solicitaram o lote principal simultaneamente e receberam o mesmo ID. A prática parcial criou apenas seu lote necessário. Reabertura explícita no Chrome manteve o ID e as tentativas.

Uma falha injetada antes de commit na criação reverteu INSERT do lote e os estados da tarefa/item. Nenhum lote do ensaio ficou persistido.

## 16. Teste de recuperação de questões

O lote principal retornou cinco questões distintas de Matemática. O parcial retornou a única questão elegível do conteúdo de Português, sem relaxar filtros.

GET inicial retornou vazio sem gravação. GETs posteriores recuperaram quatro tentativas do principal em nova sessão; depois do Chrome, recuperaram as cinco e o estado concluído.

Repetir a mesma questão em modos diferentes é permitido como prática independente; uma resposta diária anterior não preenche o exercício planejado. A seleção não exclui automaticamente todas as questões já vistas em outros modos. Não há repetição de ID dentro de um lote.

## 17. Teste de envio de respostas

Quatro respostas do lote principal e uma do parcial foram enviadas por HTTP. A quinta do principal foi selecionada e enviada pelo botão real no Chrome.

Foram validados CSRF, propriedade, chave de exercício, alternativa por letra/ID e consistência com questão. A requisição não pode registrar tentativa para outro usuário apenas alterando campos do corpo.

No frontend, clicar sem alternativa avisa; o guarda impede POST duplicado enquanto uma resposta está pendente. Campos de uma questão respondida ficam bloqueados e exibem “Resposta registrada”.

## 18. Correção e persistência

Lote principal: cinco tentativas, quatro corretas e uma incorreta, 80%. Lote parcial: uma tentativa correta. Total planejado da conta: seis tentativas, cinco acertos e um erro.

O servidor corrigiu pelo snapshot interno e persistiu avaliacao_json. A explicação apareceu após a resposta e continuou disponível em nova sessão.

Falhas antes de commit foram injetadas em criação, resposta e última resposta com conclusão. Em cada uma, a transação foi encerrada e os fingerprints de todas as linhas/estados anteriores permaneceram iguais. Não existe parâmetro de falha na API.

## 19. Conclusão da tarefa

A última resposta do principal gravou a tentativa e concluiu lote, tarefa, item e evento atomicamente. SQL confirmou estados concluida/concluida/concluido nos três vínculos.

No lote parcial, o estado do lote indica que a única questão oferecida foi respondida; tarefa e item continuam em_andamento e evento pendente. O campo tarefa_concluida e a mensagem deixam essa distinção explícita.

A tarefa de teoria original não mudou. Reabrir o lote não reabre tarefa concluída nem cria nova tentativa.

## 20. Estatísticas e progresso

O campo separado de exercícios planejados em estatisticas/estudai_geral mostrou seis respostas, cinco acertos, um erro. A outra conta continuou com zero resultados planejados.

As duas contas mantiveram os resultados diários de 2 questões, 1 acerto, 1 erro e 50%, com meta diária/semanal previamente calculada e histórico inalterados. A revisão de erros não ganhou tentativas planejadas.

O plano exibiu 33%: uma de três tarefas concluída. As tarefas estão em 16/11, portanto o painel da semana civil atual de 11/11 não deve inventar conclusão nessa semana; agregados mensais e a visão do plano usam os estados/datas correspondentes.

## 21. Duplicação e concorrência

A criação/resposta usa bloqueio da linha única de estudo_perfis, dentro da transação, antes das leituras e escritas. Sessões PHP distintas foram usadas para não depender da trava da sessão.

A UNIQUE de resposta fornece proteção adicional. Repetir a mesma marcação retorna reutilizado; outra marcação retorna 409. Reenvios concorrentes em lote concluído não mudaram timestamps, avaliação ou contagens.

Não há UNIQUE de lote por tarefa. A garantia do lote único vale para o escritor autorizado atual, que respeita o bloqueio. Futuras rotas devem seguir o protocolo ou apresentar outra solução comprovada.

## 22. Isolamento entre usuários

A conta B não acessou tarefa/lote da conta A por GET, POST de criação ou resposta. A listagem semanal de B ficou vazia. IDs de usuário forjados no corpo não mudaram a propriedade.

No Chrome, a tentativa de abrir tarefa alheia apresentou indisponibilidade e não manteve em tela o lote anterior. Nova sessão da conta proprietária recuperou as cinco respostas corretas do lote correspondente.

O isolamento dos modos também foi comprovado por fingerprints e estatísticas, não apenas por filtros estáticos.

## 23. Testes negativos

Cobertos: anônimo, sessão encerrada, CSRF ausente/incorreto, tarefa/lote/questão inexistentes, questão real do catálogo fora do lote, chave inválida, alternativa inválida/de outra questão, conflito questão/chave, usuário alheio, mudança de resposta, métodos/ações indevidos, action como array/conflitante, JSON inválido, corpo excessivo, lote incompleto/concluído e insuficiência.

Interrupção de rede/AbortError foi simulada no frontend; não houve retry automático nem falso salvamento. Reenvios e recuperação posterior foram testados no servidor. Não foi encerrado o Apache/PHP durante um INSERT; os ensaios de falha de commit verificaram atomicidade sem afetar outros projetos.

O caso sem nenhuma questão elegível foi testado na seleção e revisado no serviço; não foi fabricado apagando catálogo. Lote insuficiente real, com uma questão, foi exercitado por HTTP e Chrome.

## 24. Validação em Chrome

Chrome/157.0.8103.39 real, headless, perfil isolado: 35 verificações aprovadas. Exercitados plano, botão de prática, recuperação do lote, reabertura explícita, alternativa/botão de envio, feedback, conclusão, retorno ao plano, insuficiência, logout/login, isolamento e viewport 390 × 844.

As sete capturas finais foram abertas e inspecionadas: plano antes, questão pendente, feedback, plano concluído, aviso parcial, tarefa alheia negada e prática em viewport estreito. Nenhum impedimento visual foi observado no fluxo testado.

Console/Network: zero exceções não tratadas, zero erros de worker, nenhuma chamada OpenRouter/cron/simulado ou geração de plano. APIs não vieram do cache do worker.

A primeira tentativa do cliente CDP parou por colisão de variável antes do fluxo; foi corrigida sem respostas adicionais. O teste HTTP foi retomado após falha de decodificação UTF-8 do cliente, preservando as quatro respostas já gravadas. Os estados/evidências anteriores não foram apagados.

Não foram certificados aparelho físico, standalone, outros navegadores ou acessibilidade completa. Nenhum armazenamento do perfil pessoal foi acessado/limpo.

## 25. Regressões

| Conjunto | Resultado |
| --- | --- |
| Integração retomada de exercícios | 241 verificações |
| Conferência final SQL/HTTP preenchida | 159 verificações |
| Seleção 5/8/12, dificuldade e ausência de fallback | 12 verificações |
| Rollback de criação/resposta/conclusão | 2 verificações por ensaio |
| Frontend de exercícios | 22 cenários |
| Inicialização do dashboard | 23 cenários |
| Planejamento frontend | 12 cenários |
| Questões diárias/frontend | 29 cenários |
| Questões/revisões preenchidas, HTTP | 139 verificações |
| PWA | 21 cenários |
| HTTP/assets/proteções | 124 verificações, 19 assets |
| Configuração/READ ONLY/IA/históricos | 12 verificações |
| Regressão real da Etapa 09 | 58 verificações |

O teste de navegador anterior recebeu a opção explícita --planned-exercises-enabled para esperar três tarefas/eventos, preservando o caso original de uma tarefa. A contagem foi passada como argumento ao contexto do navegador após corrigir uma falha do próprio teste.

A regressão real cobriu cadastro vazio/senha curta/duplicidade, login/sessão/logout, onboarding de leitura, dashboard, plano existente, rotina, calendário, isolamento e offline. Cadastro positivo, salvamento de onboarding e geração de plano não foram repetidos.

## 26. Segurança

.env, .git, backups, logs, SQL, código privado, testes e documentos seguem bloqueados por HTTP. Crons e ações indevidas continuam negados. CSP/nosniff e ESTUDAI_AI_DISABLED=true foram preservados.

Apache compartilhado PID 25536 e MySQL worker 7032 continuaram em execução; MariaDB do XAMPP não foi iniciado. Portas/configurações globais e VirtualHosts não mudaram. Little Rio Manager em 127.0.0.1:8082 continuou respondendo 200.

O Apache continua com as interfaces originais amplas em 80/443/8081; Require local é a proteção específica do EstudAI. Não foi feito teste remoto a partir de outro equipamento. Aviso do projeto ausente da porta 8081 e certificado local anterior permaneceram fora do escopo.

Credenciais, tokens e cookies não foram incluídos nos arquivos versionáveis/relatório. Evidências privadas ficam fora do DocumentRoot.

## 27. Arquivos modificados

Cinco arquivos existentes alterados nesta etapa:

- .htaccess: quatro ações/métodos do endpoint.
- server/api/exercicios-ia.php: dispatcher restrito e sem efeitos GET.
- app/src/pages/app.js: integração pontual da prática, estados e erros.
- app/public/app.html: somente rótulo funcional do botão existente.
- tests/browser_stage09.mjs: opção de fixture ampliada, sem afrouxar o caso anterior.

Seis arquivos novos:

- server/services/plannedExercises.php.
- tests/planned_exercises_stage11.php.
- tests/planned_exercises_stage11.py.
- tests/planned_exercises_frontend.test.js.
- tests/browser_stage11.mjs.
- docs/etapa11-exercicios-planejados.md.

O service worker, helpers diários, CSS e demais alterações anteriores foram preservados. As evidências estão em C:\Users\player\AppData\Local\EstudAI\Recovery\20261111_194627_stage11_bd30cd6d.

Reexecução sem acrescentar tentativas:

    python -B tests/planned_exercises_stage11.py --verify-only --private-dir <privado11> --accounts <contas06>
    node tests/planned_exercises_frontend.test.js <fixture_dashboard08> <planned_frontend_fixture>
    node tests/browser_stage11.mjs <privado11> <contas06>

Não repetir fixture, rollback inicial ou a fase inicial de escrita depois da conclusão. O teste de navegador reutiliza os registros existentes.

## 28. Estado dos três bancos

| Banco | Antes | Depois | Resultado |
| --- | --- | --- | --- |
| vestibular_estudos | 42 tabelas / 641 registros | Igual | Conteúdo e estrutura intactos |
| estudai_demo_2026 | 42 tabelas / 641 registros | Igual | Conteúdo e estrutura intactos |
| estudai_teste_2026 | 42 tabelas / 291 registros | 42 tabelas / 305 registros | Somente 14 inclusões autorizadas |

Inclusões: dois itens, duas tarefas, dois eventos, dois lotes e seis respostas planejadas. Os estados das novas fixtures foram atualizados pelos comandos autorizados.

A auditoria comparou conteúdo completo por tabela, colunas e DDL normalizado. Além das tabelas não afetadas, comparou explicitamente todas as linhas preexistentes das três tabelas que receberam fixtures: nenhuma mudou. Catálogo, respostas diárias/revisões, plano original, perfis e demais dados anteriores mantiveram os fingerprints.

Backup original íntegro; grants finais coincidem com as cinco concessões específicas. Nenhum schema, migration, seed, DROP, DELETE ou reinício de contador foi executado. IDs podem avançar nos rollbacks sem deixar registros parciais.

## 29. Estado do Git

HEAD 67a2fe0 preservado, sem commit, deploy ou arquivos staged. .env permanece ignorado/não rastreado e inalterado. O inventário diferencia esta etapa das mudanças anteriores ainda pendentes.

Verificações finais: php -l nos PHPs alterados/novos; node --check no JS modificado e testes; AST Python; Apache Syntax OK; git diff --check; git status. O aviso Apache conhecido e os avisos LF/CRLF do Git não motivaram mudanças globais.

Os 29 arquivos .conf do Apache permaneceram idênticos. Os Chromes isolados de teste foram encerrados.

## 30. Pendências

Não restou critério obrigatório de conclusão sem comprovação no escopo testado. Limitações mantidas:

- Base pequena: 32 questões; um conteúdo específico costuma oferecer apenas uma.
- Lote parcial não conclui tarefa. A complementação/substituição futura exige decisão de produto e autorização, sem apagar tentativas silenciosamente.
- O protocolo de bloqueio deve ser respeitado por escritores futuros, pois não foi criada UNIQUE de lote por tarefa.
- As duas tarefas são fixtures manuais, sem nova validação do gerador de horários.
- Motivo de erro/atualização posterior da tentativa, anotações, reabertura genérica de tarefa, simulados e respostas abertas não foram liberados.
- Falha abrupta do processo servidor não foi injetada; rollback e interrupção do cliente foram testados de forma controlada.
- Ensaio no equipamento/perfil pessoal da apresentação continua recomendado.

Roteiro: abrir login, entrar com a conta fictícia proprietária, Plano > “Praticar questões” na tarefa principal, conferir cinco respostas/resultado e tarefa concluída; abrir a parcial e conferir “1 de 5” com tarefa em andamento. Não clicar em geração de plano ou ações genéricas bloqueadas. Não limpar dados de localhost indiscriminadamente para atualizar o PWA.

## 31. Próxima etapa recomendada

Analisar este relatório e ensaiar o fluxo aprovado no equipamento da apresentação, preservando os dados fictícios já preparados.

A Etapa 11 está concluída no escopo descrito. Simulados e integração OpenRouter não foram iniciados; nenhuma próxima etapa será executada automaticamente.
