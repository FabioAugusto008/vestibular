# Etapa 12 — Simulados convencionais e planejados

Data: 11/11/2026. Projeto: C:\xampp\htdocs\estudAi_1.1.

## 01. Resumo executivo

Etapa 12 tecnicamente concluída no escopo descrito neste relatório. Os dois fluxos existentes foram recuperados: seleção, início autorizado, resposta, retomada, finalização, resultado e consulta posterior. O planejado preserva os vínculos com plano, tarefa, item e calendário.

Gabarito, acerto e explicações ficam fora das respostas públicas antes da finalização. A correção ocorre no servidor. Tentativas finalizadas rejeitam respostas; repetir finalização recupera o mesmo resultado sem nova gravação.

Uma tentativa convencional de duas questões terminou com um acerto e um erro, 50%. Um simulado planejado de dez questões terminou com nove acertos e um erro, 90%. Foram usadas somente questões existentes e contas fictícias.

Nenhum banco histórico foi modificado. Não houve schema, migration, seed, DELETE, commit, deploy, chamada remota de IA ou alteração global do Apache. As alterações válidas anteriores foram preservadas.

## 02. Estado inicial

Foram consultados os relatórios das Etapas 07 a 11, o diff acumulado e os testes pertinentes. HEAD 67a2fe0, árvore de trabalho já modificada e nenhum arquivo staged. O inventário inicial registrou 150 arquivos fora de .git, incluindo os arquivos anteriores ainda não versionados.

Conexão efetiva confirmada: estudai_teste_2026, estudai_teste_app@localhost, MySQL 8.0.45 na porta 3306. A conta PHP tinha 24 entradas de SHOW GRANTS. O catálogo tinha 32 questões, 160 alternativas, 13 matérias e 42 conteúdos; as seis tabelas de simulados estavam vazias.

O plano fictício existente tinha três tarefas, itens e eventos: teoria, prática completa e prática parcial. As respostas diárias, revisão e práticas da Etapa 11 já estavam persistidas. Os problemas dos simulados incluíam contrato HTTP incompatível com os bloqueios atuais, escrita em leitura, correção antecipada, estados permissivos e montagem incorreta do botão de resposta.

Apache compartilhado PID 25536 e MySQL worker 7032 estavam funcionando. Little Rio Manager respondia 200 em 127.0.0.1:8082. Require local, CSP e ESTUDAI_AI_DISABLED=true estavam ativos.

## 03. Arquitetura dos simulados

Os dois endpoints validam sessão, método, ação e CSRF; delegam a um serviço específico. As consultas GET entram na transação READ ONLY existente. As escritas usam transação e bloqueio da linha do perfil do usuário.

| Função frontend | Endpoint | Método / ação | SQL principal | Tabelas | Permissões | Efeito |
| --- | --- | --- | --- | --- | --- | --- |
| loadSimulados | simulados.php | GET listar | SELECT catálogo, contagens e melhor nota próprios | simulados, simulado_questoes, questoes, simulado_tentativas | SELECT | Catálogo e disponibilidade real |
| iniciarSimulado | simulados.php | POST iniciar | SELECT perfil FOR UPDATE; SELECT tentativa; INSERT se ausente | estudo_perfis, simulados, simulado_questoes, questoes, questoes_alternativas, simulado_tentativas, simulado_respostas | SELECT; INSERT tentativa | Retoma tentativa existente ou cria uma válida |
| Retomada/inspeção | simulados.php | GET carregar | SELECT tentativa com usuario_id e respostas vinculadas | simulado_tentativas, simulado_respostas, catálogo e alternativas | SELECT | Questões e progresso próprios; resultado só se finalizado |
| responderSimulado | simulados.php | POST responder | SELECT posse/estado/vínculos; INSERT ou UPDATE resposta | estudo_perfis, simulado_tentativas, simulado_respostas, catálogo | SELECT, INSERT; UPDATE nas colunas autorizadas | Salva alternativa sem devolver correção |
| finalizarSimulado | simulados.php | POST finalizar | SELECT respostas; UPDATE tentativa | estudo_perfis, simulado_tentativas, simulado_respostas, catálogo | SELECT; UPDATE estado, tempo, data, acertos e erros | Fecha e retorna resultado calculado |
| Catálogo/reabertura; consulta de histórico | simulados.php | GET historico | SELECT tentativas finalizadas do usuário | simulado_tentativas, simulados | SELECT | Histórico privado |
| loadPlannedSimulados | simulados-planejados.php | GET listar | SELECT próprios ligados a tarefa; mapas por registro | simulados_planejados, simulados_planejados_respostas, planos_estudo, tarefas_estudo, plano_estudo_itens | SELECT | Lista em andamento do plano ativo e resultados próprios |
| abrirSimuladoDaTarefa | simulados-planejados.php | POST gerar_para_tarefa | SELECT tarefa/plano/item e questões; INSERT se ausente | estudo_perfis, planos_estudo, tarefas_estudo, plano_estudo_itens, questoes, questoes_alternativas, simulados_planejados | SELECT; INSERT simulado | Prepara conjunto privado de dez, com mínimo de cinco |
| iniciarSimuladoPlanejado | simulados-planejados.php | POST iniciar | SELECT liberação; UPDATE estado e vínculos | estudo_perfis, simulados_planejados, tarefas_estudo, plano_estudo_itens, eventos_calendario_estudai, planos_estudo | SELECT; UPDATE estado e timestamps autorizados | Inicia somente após liberação |
| Recuperação após salvar | simulados-planejados.php | GET carregar | SELECT por id e usuario_id; SELECT respostas | simulados_planejados, simulados_planejados_respostas, tarefas_estudo, planos_estudo, plano_estudo_itens | SELECT | Recupera progresso e estado |
| responderSimuladoPlanejado | simulados-planejados.php | POST responder | SELECT posse, estado, questão e alternativa; INSERT ou UPDATE | estudo_perfis, simulados_planejados, simulados_planejados_respostas e vínculos | SELECT, INSERT; UPDATE resposta/correção/data | Salva sem expor avaliação |
| finalizarSimuladoPlanejado | simulados-planejados.php | POST finalizar | SELECT conjunto/respostas; UPDATE simulado e vínculos | estudo_perfis, simulados_planejados, simulados_planejados_respostas, tarefas_estudo, plano_estudo_itens, eventos_calendario_estudai, planos_estudo | SELECT; UPDATE restrito | Finalização atômica; conjunto completo conclui vínculos |
| loadEstudaiStats / estatísticas existentes | estatisticas.php | GET geral / estudai_geral | SELECT agregados separados por modalidade e usuário | respostas_usuario, simulado_tentativas, simulados_planejados e tabelas anteriores | SELECT | Mantém indicadores diários separados dos simulados |

server/services/simulationRecovery.php reutiliza validações e sincronização de tarefas de plannedExercises.php. Não invoca os comandos de resposta diária. A API antiga não permanece como caminho alternativo de escrita.

## 04. Diferenças entre as modalidades

| Aspecto | Convencional | Planejado |
| --- | --- | --- |
| Origem | Catálogo predefinido e simulado_questoes | Questões aprovadas conforme tarefa |
| Quantidade | Exatamente a declarada no catálogo | Dez solicitadas, mínimo de cinco elegíveis |
| Conjunto | Referência ao catálogo existente | Cópia privada em questoes_json |
| Identificador de questão | ID numérico | Chave string q seguida de ID |
| Respostas | Por tentativa e questão | Por usuário, registro planejado e question_key |
| Liberação | Catálogo ativo e íntegro | Data de liberação e vínculo de tarefa/plano |
| Conclusão | Resultado da tentativa | Resultado e, se conjunto completo, tarefa/item/evento |
| Retomada | Ação iniciar reutiliza a última tentativa ativa ou finalizada | A tarefa reutiliza o registro não arquivado |

Não foi habilitada uma nova tentativa convencional automática após concluir. O botão passa a reabrir o resultado. O gerador semanal desvinculado de tarefa permanece bloqueado.

## 05. Diagnóstico do bug de onclick

Defeito confirmado: a string da questão era serializada dentro de um atributo onclick já delimitado por aspas duplas. O HTML resultante continha a chamada com aspas internas incompatíveis, impedindo o envio normal de chaves como q1.

O problema estava na renderização dos cartões de questões planejadas. Escape de texto HTML não torna uma chamada JavaScript inline com aspas arbitrárias segura.

## 06. Correção dos botões

O botão existente conserva classe, texto e estrutura. A chave vai para data-sim-question com escape HTML; após renderizar, addEventListener registra o clique e lê dataset.simQuestion. Não há avaliação de código construído com a chave.

O teste de regressão inclui chave com aspas e conteúdo malicioso, além de chaves normais. No Chrome real foram clicados o radio e o botão Salvar resposta da última questão planejada.

Guardas evitam envios simultâneos e ações do frontend após finalização. Radios e botões de resposta ficam desabilitados no resultado. Os demais botões relacionados passam IDs numéricos conhecidos. Não houve troca de framework, componente ou design.

## 07. Máquina de estados

| Modalidade / estado existente | Operações autorizadas | Transição / restrição |
| --- | --- | --- |
| Convencional em_andamento | Consultar, retomar, responder, finalizar | Finaliza somente com todas as questões respondidas |
| Convencional finalizado | Consultar resultado e histórico; repetir finalizar | Nenhuma nova resposta; repetição não altera nota, tempo ou data |
| Convencional cancelado | Nenhuma transição recuperada | Rejeitado como tentativa encerrada |
| Planejado bloqueado | Consultar; reutilizar registro da tarefa | Início antes da data retorna 403 |
| Planejado liberado | Consultar e iniciar | POST iniciar passa para iniciado |
| Planejado iniciado | Consultar, retomar, responder e finalizar | Finaliza somente com todas as questões reais respondidas |
| Planejado finalizado | Consultar resultado; repetir início/finalização para recuperar | Respostas retornam 409; estado e timestamps permanecem iguais |
| Planejado arquivado | Nenhuma transição recuperada | Fora da lista e rejeitado no fluxo auditado |

A liberação por vencimento da data é calculada no GET: um registro armazenado como bloqueado pode ser apresentado como liberado sem UPDATE. O POST iniciar efetua a transição persistida. Não foram criados estados novos.

## 08. Proteção do gabarito

Antes da finalização, questões e respostas públicas não contêm indicador de acerto, alternativa correta, explicação, avaliação ou metadados de correção. O campo gabarito convencional é nulo, e resultado permanece nulo. Isso vale inclusive para questões já respondidas.

O servidor conserva a correção privada para finalizar, mas responder retorna somente sucesso e informação de reutilização. O planejado remove os campos privados de sua cópia JSON antes de produzir a resposta pública.

Depois de finalizar, gabarito e explicações tornam-se disponíveis somente ao proprietário. A proteção foi conferida nos contratos HTTP, nos testes frontend e nas telas reais; não depende de CSS.

## 09. Diagnóstico da base de questões

Confirmados 32 registros distintos, 160 alternativas, 13 matérias e 42 conteúdos. Não foram criadas questões ou alternativas. A seleção aceita somente questões aprovadas com enunciado, cinco alternativas e uma correta, sem duplicação dentro do conjunto.

O planejado respeita matéria, conteúdo e dificuldade informados na tarefa. Ausência de matéria/conteúdo significa escopo geral explícito; critério informado sem resultados não sofre relaxamento silencioso. Questões usadas em outra modalidade podem reaparecer: não existe regra recuperada de exclusão global por uso anterior.

Seleção testada para 5, 12 e 40 solicitadas: máximo real de 32, sem repetição. Recorte de Matemática forneceu oito para doze solicitadas. Conteúdo inexistente forneceu zero.

## 10. Tratamento de questões insuficientes

Convencional: exige a quantidade exata predefinida. Catálogo incompleto retorna 409 antes de INSERT de tentativa. A lista informa quantidade real e desabilita início. A fixture indisponível exige quarenta e possui zero vínculos; a interface mostra 0/40 disponíveis.

Planejado: solicita dez; menos de cinco rejeita criação; cinco a nove permite conjunto reduzido com aviso explícito. A nota usa somente as questões reais e a tarefa permanece em andamento após finalizar esse conjunto. Dez questões respondidas e finalizadas permitem concluir o vínculo.

A insuficiência foi exercitada na seleção e no catálogo convencional. Não foi persistida uma segunda tentativa planejada reduzida nesta etapa: seu ramo de conclusão foi revisado estaticamente. O fluxo persistido e concluído no Chrome usa dez questões. Nenhum registro foi duplicado para completar quantidade.

## 11. Estrutura do banco utilizada

Foram inspecionadas as seis tabelas de simulados, seus campos, enums, JSON, timestamps, índices, UNIQUE e FKs, além de tarefas, itens, plano, calendário, perfil e catálogo.

O schema de testes continua com 42 tabelas, 406 colunas, 139 índices e 65 chaves estrangeiras. As restrições de resposta impedem duplicação por tentativa/questão e usuário/registro/chave. A auditoria final não encontrou respostas órfãs ou vínculos planejados entre proprietários diferentes.

Não existe UNIQUE para tentativa convencional por usuário/catálogo ou registro planejado por tarefa. O protocolo transacional do perfil protege esses caminhos sem alterar o schema.

## 12. Alterações de schema

Nenhuma. Não foram executados schema.sql, migrations históricas, seeds, DROP, DELETE ou reinício de contadores. Rollbacks podem consumir números de AUTO_INCREMENT, sem deixar registros parciais.

As fixtures foram inclusões explícitas no banco de testes, usando catálogo já existente e um plano fictício existente.

## 13. Privilégios MySQL

Seis concessões específicas foram aplicadas à conta estudai_teste_app@localhost, exclusivamente em estudai_teste_2026:

| Tabela | Privilégios acrescentados |
| --- | --- |
| simulados | SELECT |
| simulado_questoes | SELECT |
| simulado_tentativas | SELECT, INSERT; UPDATE(status, finalizado_em, tempo_gasto_seg, acertos, erros) |
| simulado_respostas | SELECT, INSERT; UPDATE(resposta_marcada, acertou, respondido_em) |
| simulados_planejados | INSERT; UPDATE(status, atualizado_em), preservando SELECT anterior |
| simulados_planejados_respostas | SELECT, INSERT; UPDATE(resposta_marcada, acertou, avaliacao_json, respondido_em) |

SHOW GRANTS passou de 24 para 29 entradas porque simulados_planejados já tinha SELECT. A auditoria final confirmou correspondência exata às concessões autorizadas. Não foi concedido DELETE, privilégio administrativo, acesso global ou acesso aos históricos.

Os privilégios anteriores de estado/timestamps da tarefa, item e evento foram reutilizados. A conta PHP não pode alterar data_liberacao, catálogo, questões ou credenciais. Root foi usado somente nos atos administrativos necessários de fixture/concessão e nas auditorias, sem entregar sua credencial ao PHP da aplicação.

## 14. Endpoints liberados

As liberações novas no .htaccess são somente:

- simulados.php: GET listar, carregar, historico; POST iniciar, responder, finalizar.
- simulados-planejados.php: GET listar, carregar; POST gerar_para_tarefa, iniciar, responder, finalizar.

A ação deve constar na URL, com validação adicional contra ações conflitantes/ambíguas no dispatcher. As permissões das etapas anteriores foram preservadas.

## 15. Endpoints bloqueados

Permanecem bloqueados: gerar_para_semana de simulados planejados, exclusão, ações administrativas/desconhecidas, métodos indevidos, GETs de escrita, endpoints de IA, cron, diagnósticos e código privado.

Nenhuma operação genérica de reabertura, arquivamento, nova tentativa automática, alteração de catálogo ou geração de questões foi liberada. O gerador semanal local já autorizado em etapa anterior não foi acionado pelos testes de simulados.

## 16. GETs e operações de escrita

Listagem, carga e histórico usam SELECT em transação READ ONLY. Abrir a seção não cria tentativas. Criação, início, resposta e finalização exigem POST, sessão, CSRF e validação de posse/estado.

Fingerprints das tabelas antes/depois confirmaram ausência de efeitos nas leituras, inclusive liberação calculada pela data. A regressão de configuração confirmou que um UPDATE dentro de READ ONLY é rejeitado pelo MySQL.

As escritas não usam REPLACE, DELETE/INSERT de conveniência ou pontuação recebida do cliente. Resposta repetida idêntica não altera timestamp; uma alternativa diferente só pode substituir a anterior enquanto a tentativa está em andamento.

## 17. Simulado convencional: testes

Catálogo fictício identificado como teste12, duas questões existentes e duração de dez minutos. Foi criada somente uma tentativa persistida para a conta proprietária.

Validados login, listagem, início concorrente, carga sem gabarito, resposta, rejeição de questão/alternativa externa, retomada e finalização incompleta rejeitada. A última resposta e a finalização foram realizadas por cliques no Chrome.

Resultado persistido: duas respondidas, um acerto, um erro, 50%; gabarito e explicações disponíveis ao concluir. Reabertura e nova sessão recuperam o mesmo resultado. Histórico contém uma tentativa, sem novas linhas nas repetições.

## 18. Simulado planejado: testes

Foi acrescentada uma tarefa fictícia de simulado ao plano existente, com item e evento próprios. A criação concorrente retornou o mesmo registro e selecionou dez questões locais, sem duplicação.

A data original de 16/11 foi efetivamente testada: início 403; resposta e finalização indevidas 409. Para executar o fluxo em 11/11, um ajuste administrativo mudou apenas data_liberacao do novo registro para 11/11. Plano, tarefa, item e evento mantiveram sua data original. Não houve alteração do relógio ou bypass na aplicação.

Depois da liberação, GET apenas apresentou o estado efetivo. Início, nove respostas HTTP, recuperação por nova sessão, último radio/botão no Chrome e finalização passaram. Resultado: dez respondidas, nove acertos, um erro, 90%.

Esse ajuste é fixture de teste documentada, não comprovação de geração automática de agenda nem nova regra de antecipação.

## 19. Persistência

Alternativas salvas foram recuperadas da API após atualização e nova autenticação, independentemente de localStorage. O convencional persiste respostas e, ao finalizar, estado, data, tempo, acertos e erros.

O planejado preserva seu conjunto em JSON privado, respostas, correções e estado. O resultado é calculado a partir desse conjunto congelado e das respostas imutáveis após conclusão.

Seis ensaios de falha no commit demonstraram rollback integral: criação, resposta e finalização de cada modalidade. Cada ensaio verificou transação encerrada e fingerprints restaurados.

## 20. Finalização e bloqueio pós-conclusão

Não se finaliza conjunto incompleto. Campos de nota enviados pelo cliente são ignorados; a nota deriva dos registros privados do servidor.

Depois de finalizar, POST responder retorna 409 mesmo para uma resposta idêntica. Repetir finalizar devolve o resultado existente sem UPDATE, alteração de tempo/data, nova resposta ou nova conclusão de vínculos.

O frontend mostra resultado, desabilita resposta/finalização e permite revisão. A proteção real permanece no backend, independentemente dos controles visuais.

## 21. Concorrência

Cada comando composto abre transação e bloqueia a linha de estudo_perfis do proprietário antes de ler/criar/alterar a tentativa. Assim, duas sessões da mesma conta serializam criação, resposta e finalização.

Foram testados início convencional simultâneo, criação planejada simultânea, resposta idêntica simultânea, início planejado repetido e finalização concorrente/repetida. IDs, contagens, resultados e fingerprints permaneceram estáveis.

Essa garantia exige que futuros escritores respeitem o mesmo protocolo; não foi criada uma restrição UNIQUE de tentativa/lote por tarefa. Falha abrupta de processo ou queda real do servidor não foi injetada.

## 22. Isolamento de usuários

Usadas duas contas fictícias existentes. Somente a proprietária possui os simulados desta etapa. A segunda não consulta nem modifica tentativa, tarefa, resultado ou respostas da primeira.

Usuário recebido no corpo não substitui a sessão. Leituras/escritas de IDs alheios retornam 404. Histórico convencional e lista planejada da segunda conta permanecem vazios; seu contador de simulados concluídos é zero.

## 23. Estatísticas

Cada resultado contém total, respondidas, acertos, erros, percentual e distribuição por matéria. Os testes conferiram que a soma por matéria corresponde ao resultado e que acertos mais erros cobrem todas as questões reais.

O indicador separado simulados_finalizados conta uma tentativa convencional e um simulado planejado para a proprietária: dois, sem duplicar finalizações repetidas. A segunda conta permanece com zero.

Questões diárias continuam em duas respostas por conta, um acerto, um erro e 50%. Revisão e exercícios planejados conservam seus próprios registros. Evolução diária não recebe respostas de simulados. Conquistas continuam somente em leitura; não foi habilitada atribuição automática ao finalizar.

## 24. Histórico

GET historico convencional retorna somente finalizados próprios, com resultado e datas. O catálogo exibe uma execução, melhor nota e botão Reabrir resultado. O planejado mantém o finalizado na lista e permite Ver resultado pela tarefa e pela seção de simulados.

Leituras de resultados finalizados não exigem que o catálogo convencional ainda esteja ativo nem que o plano planejado permaneça ativo; a posse e os vínculos continuam sendo validados. Essa exceção foi revisada e a recuperação dos registros atuais foi retestada. Não foram arquivados planos ou desativados catálogos para criar dados extras de teste.

Limitação estrutural: o convencional referencia o catálogo atual, sem versão congelada de questões. Preservar o catálogo é necessário para conservar gabarito/exibição histórica; a conta PHP não tem permissão para alterá-lo. O planejado usa cópia privada do conjunto. Nenhum gabarito ou catálogo anterior foi alterado.

## 25. Integração com planejamento e calendário

O simulado planejado valida propriedade conjunta de tarefa, plano e item. Início coloca o vínculo em andamento; finalizar o conjunto completo conclui tarefa, item e evento na mesma transação.

A conferência SQL encontrou tarefa concluida, item concluida e evento concluido. O plano passou de uma tarefa concluída em três para duas em quatro: 50%. As três tarefas anteriores, seus itens e eventos não mudaram.

Não foram criados plano, versão, controle semanal ou novo JSON de planejamento. A tarefa de simulado é uma fixture manual; não demonstra distribuição automática de horários. O botão da tarefa concluída agora diz Ver resultado, inclusive quando a lista planejada ainda não foi carregada ou a data da tarefa é futura.

## 26. Testes negativos

Passaram: anônimo, sessão expirada após logout, CSRF ausente/inválido, IDs inexistentes e de outra conta, IDs com tipo indevido, questão fora do simulado, alternativa inválida/de outra questão, finalização incompleta, início futuro bloqueado, resposta duplicada, finalização repetida/concorrente e resposta após conclusão.

Também foram testados JSON inválido, corpo acima do limite, ação conflitante/ambígua, método incorreto, ações administrativas/semanais bloqueadas, catálogo insuficiente e consultas de correção antecipada.

Frontend: 49 cenários aprovados. Incluem falhas 401/403/419/409/500, JSON inválido, rede, resposta vazia, AbortError, duplo clique, CSRF, recuperação de estados, bloqueio pós-conclusão, chaves string/escape e ausência de correção antecipada. Interrupção do cliente foi simulada; servidor não foi encerrado.

Integração HTTP dos simulados: preparação com 618 verificações, respostas com 364 e conferência final ampliada com 102. A contagem da conferência inclui validações de transporte/contrato, além das invariantes funcionais; não equivale a 102 jornadas independentes.

## 27. Validação visual no Chrome

Chrome real 157.0.8103.39, modo headless, perfil privado isolado e depuração somente em loopback. Execução inicial: 36 verificações e oito capturas, com as últimas respostas/finalizações reais nas duas modalidades. Execução após correções pontuais: 25 verificações e seis capturas, reutilizando resultados fechados.

Foram inspecionadas visualmente dez imagens: as oito da execução inicial e as duas finais de catálogo/plano. Cobertura: catálogo, questão pendente, resultado e gabarito convencional; questão pendente e resultado planejado; estado do plano; viewport móvel. As imagens finais confirmaram Ver resultado na tarefa concluída e 0/40 no catálogo indisponível.

Desktop 1365×900 e viewport móvel 390×844; sem transbordamento horizontal no cenário móvel. Console sem exceções não tratadas ou erros do worker. A execução de simulados não apresentou falhas de rede inesperadas. Network confirmou ausência de IA/cron/geração de plano e ausência de respostas de API servidas pelo cache do service worker.

Os navegadores de teste foram encerrados. Nenhum perfil pessoal, cookie pessoal ou cache global de localhost foi manipulado. Não houve teste em telefone físico, instalação standalone ou auditoria completa de acessibilidade. Os indicadores automáticos visual_inspection_pending dos relatórios brutos são complementados pelo registro privado de revisão humana, sem reescrever a evidência original.

## 28. Regressões

| Conjunto executado nesta etapa | Resultado |
| --- | --- |
| Inicialização do dashboard | 23 cenários |
| Planejamento frontend | 12 cenários |
| Questões diárias frontend | 29 cenários |
| Exercícios planejados frontend | 22 cenários |
| PWA/service worker | 21 cenários |
| HTTP/assets/proteções | 124 verificações, 19 assets |
| Configuração, READ ONLY, IA e restrição dos históricos | 12 verificações |
| Questões/revisões preenchidas HTTP | 139 verificações |
| Exercícios planejados preenchidos HTTP | 159 verificações |
| Chrome real de regressão da Etapa 09 | 58 verificações |
| Chrome real de exercícios da Etapa 11 | 32 verificações |
| Seleção de questões de simulados | 9 verificações |
| Rollback de simulados | 6 ensaios, 2 verificações por ensaio |

Os testes anteriores receberam a opção --simulations-enabled para esperar quatro tarefas/eventos e 50% no plano, mantendo os cenários anteriores sem essa opção. O denylist HTTP passou a testar a ação semanal ainda proibida, pois listar planejados agora é uma leitura autorizada.

Cadastro vazio, senha curta e duplicidade, login, sessão, logout, onboarding de leitura, dashboard, tarefas, calendário, isolamento, offline e PWA passaram. Cadastro positivo, salvamento de onboarding e geração de plano não foram repetidos; suas tabelas permaneceram iguais. A regressão offline contém falhas de transporte induzidas e esperadas, não falhas da jornada online de simulados.

## 29. Arquivos modificados

Oito arquivos existentes alterados em relação ao inventário inicial desta etapa:

- .htaccess: ações/métodos estritamente necessários.
- app/src/pages/app.js: integração pontual dos simulados, eventos, estados e mensagens.
- server/api/simulados.php: dispatcher convencional validado.
- server/api/simulados-planejados.php: dispatcher planejado ligado à tarefa.
- server/api/estatisticas.php: contador separado de finalizados das duas modalidades.
- tests/browser_stage09.mjs: opção explícita para a nova fixture.
- tests/browser_stage11.mjs: opção de fixture/progresso e passagem de argumento ao contexto do navegador.
- tests/http_stage09.py: opção para testar o bloqueio semanal no lugar da leitura agora autorizada.

Seis arquivos novos:

- server/services/simulationRecovery.php.
- tests/simulations_stage12.php.
- tests/simulations_stage12.py.
- tests/simulations_frontend.test.js.
- tests/browser_stage12.mjs.
- docs/etapa12-simulados-validacao.md.

app.html, service worker, CSS, .env, helpers diários, serviço de exercícios e demais arquivos anteriores permaneceram idênticos ao início desta etapa. O inventário final diferencia estas mudanças do diff acumulado das Etapas 07–11.

Evidências privadas fora do DocumentRoot: C:\Users\player\AppData\Local\EstudAI\Recovery\20261111_204714_stage12_e851887b.

Reexecução segura da conferência, sem criar novas tentativas:

    python -B tests/simulations_stage12.py verify --private-dir <privado12> --accounts <contas06>
    node tests/simulations_frontend.test.js <fixture_dashboard08> <simulation_frontend_fixture12>
    node tests/browser_stage12.mjs <privado12> <contas06>

Não repetir fixture, release-fixture, prepare, answer ou ensaios de rollback iniciais sobre o estado finalizado. As credenciais permanecem no armazenamento privado anterior, não no repositório.

## 30. Verificações de sintaxe

Todos aprovados:

- php -l: cinco arquivos, os dois endpoints de simulado, estatisticas.php, serviço e teste PHP.
- node --check: app.js e os quatro testes JS/MJS alterados ou novos.
- AST Python: integração nova e teste HTTP anterior modificado, sem gerar cache.
- Apache: Syntax OK.
- git diff --check: sem erros.

O aviso Apache de DocumentRoot ausente de outro projeto na porta 8081 já existia e foi preservado. Avisos Git de conversão LF/CRLF não motivaram alterações globais.

## 31. Segurança

.env, .git, backups, logs, SQL, testes, documentos e código privado continuam bloqueados por HTTP. CSP connect-src self, form-action self, base-uri self, object-src none e nosniff preservados. Erros públicos não incluem SQLSTATE, stack trace ou credenciais.

ESTUDAI_AI_DISABLED=true foi preservado no ambiente e na configuração HTTP. Os testes vigiam chamadas externas e nenhum fluxo executou OpenRouter. Não foram habilitados cron ou APIs de IA.

O Apache compartilhado mantém suas interfaces originais amplas em 80/443/8081; 8082 permanece em loopback. A proteção específica do EstudAI é Require local. Nenhuma configuração global/VirtualHost foi alterada, e os 29 arquivos .conf têm os mesmos fingerprints iniciais. Não foi feito teste remoto em outro equipamento.

Little Rio Manager continuou respondendo 200; não houve reinício de Apache/MySQL nem alteração de outro projeto. Evidências, contas fictícias, respostas privadas e perfis de navegador ficam fora do DocumentRoot.

## 32. Estado dos bancos históricos

| Banco | Antes | Depois | Evidência |
| --- | --- | --- | --- |
| vestibular_estudos | 42 tabelas / 641 registros | Igual | Conteúdo completo, colunas e DDL normalizado idênticos |
| estudai_demo_2026 | 42 tabelas / 641 registros | Igual | Conteúdo completo, colunas e DDL normalizado idênticos |
| estudai_teste_2026 | 42 tabelas / 305 registros | 42 tabelas / 326 registros | Somente os nove conjuntos autorizados abaixo |

Inclusões líquidas: dois catálogos convencionais, dois vínculos de questão, uma tentativa convencional, duas respostas convencionais, um simulado planejado, dez respostas planejadas, uma tarefa, um item e um evento: 21 registros.

Estados/respostas dessas fixtures foram atualizados pelos comandos auditados; a liberação administrativa afetou somente o novo registro planejado. Todas as linhas preexistentes das tabelas de tarefa/item/evento foram comparadas separadamente e permaneceram iguais.

Todas as demais tabelas de testes mantiveram seus fingerprints, inclusive questões/alternativas, respostas diárias, revisão, práticas, perfis, planos, versões, controles, metas e conquistas. Estruturas dos três bancos e backup original permaneceram intactos.

## 33. Estado do Git

HEAD 67a2fe0 preservado, nenhum commit, deploy ou arquivo staged. .env segue ignorado, não rastreado e inalterado. O inventário final registra 156 arquivos fora de .git: oito anteriores modificados, seis novos e nenhuma remoção nesta etapa.

As mudanças acumuladas das etapas anteriores continuam na árvore de trabalho. Nenhum reset, checkout de arquivos anteriores ou limpeza de evidências foi executado. Verificações de conteúdo privado não encontraram credenciais das contas de teste nos arquivos desta etapa.

## 34. Problemas encontrados

Foram corrigidos o onclick com chave string, exposição antecipada de correção, escritas em GET, ausência de autorização consistente de tentativa/alternativa, finalização incompleta, modificação pós-conclusão e criação repetida sem proteção transacional nos caminhos recuperados.

A revisão e os testes também demonstraram: resultado convencional escondendo questões de revisão; temporizador anterior sobrevivendo à reabertura de resultado; falha de catálogo engolida por catch vazio; estado iniciado rotulado como liberado; quantidade indisponível apresentada sem contagem real; tarefa concluída ainda mostrando data futura em vez de Ver resultado.

As correções foram locais, com testes de regressão. Não houve refatoração geral do dashboard, mudança visual, alteração de schema ou liberação indiscriminada de ações.

## 35. Pendências

Nenhum critério obrigatório ficou sem demonstração nos dois fluxos completos descritos. Permanecem os limites explícitos:

- Uma tentativa convencional recuperável por catálogo/usuário neste escopo; retentativa adicional exige ação e protocolo próprios, ainda não habilitados.
- Gerador semanal de simulados sem tarefa, arquivamento e ações administrativas continuam bloqueados.
- Base reduzida, sem certificação editorial/pedagógica do conteúdo.
- Conjunto planejado reduzido: seleção e regra inspecionadas; uma segunda jornada persistida de cinco a nove questões não foi criada.
- Convencional sem cópia versionada do catálogo. Alteração administrativa futura de questões pode afetar exibição/gabarito histórico; deve ser tratada em escopo específico.
- Cronômetro convencional é informativo; tempo é persistido na finalização e reinicia na retomada em andamento. Não se validou prazo obrigatório no servidor ou continuidade do cronômetro entre sessões.
- O protocolo de bloqueio por perfil deve ser seguido por qualquer escritor futuro.
- Fixtures planejadas manuais não validam novamente o gerador de agenda. A liberação antecipada do único novo registro foi administrativa e documentada.
- Sem teste de queda real do servidor, telefone físico, perfil pessoal ou instalação standalone.

Não apagar fixtures ou limpar indiscriminadamente o armazenamento de localhost para ensaiar. Para conferir o estado existente: login fictício proprietário > Simulados > Reabrir resultado (50%) e Ver resultado (90%); Plano > tarefa teste12 concluída, progresso 50%. Não acionar geração de plano ou operações fora do escopo.

## 36. Próxima etapa recomendada

Analisar este relatório e decidir explicitamente o próximo escopo, considerando as limitações de catálogo, retentativa e base reduzida.

A Etapa 12 termina aqui. OpenRouter, preparação da apresentação e novas funcionalidades não serão iniciados automaticamente. Aguardando análise do usuário.
