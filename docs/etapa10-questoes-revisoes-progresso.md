# Etapa 10 — Questões diárias, correção, revisões e progresso

Data: 11/11/2026. Projeto: C:\xampp\htdocs\estudAi_1.1.
Apresentação: 17/11/2026, Parque Tecnológico de São José dos Campos.

## 01. Resumo executivo

Etapa 10 tecnicamente concluída no escopo recuperado: consultar/preparar treino diário, responder alternativas, receber correção do servidor, recuperar respostas, finalizar, consultar histórico, meta e estatísticas, e revisar erros sem alterar o resultado diário.

Foram usadas duas questões existentes e duas contas fictícias. Persistência final: duas associações no lote do dia, quatro respostas diárias, dois resultados finalizados e uma tentativa de revisão. Cada conta terminou com 1 acerto e 1 erro, 50%. A revisão correta de B não alterou seu resultado diário nem resolveu o erro de A.

Passaram 387 verificações na primeira fase de integração e 139 na conferência dos dados preenchidos. O Chrome real concluiu 36 verificações no estado retomado, além das capturas do envio original. Passaram 29 cenários novos de frontend, 23 de inicialização, 12 de planejamento, 21 do PWA, 124 verificações HTTP/assets e 12 de configuração.

Bancos históricos, catálogo, schema, backup, configurações Apache e alterações anteriores foram preservados. Sem IA, cron, simulados, novos planos, mudança de design, instalação de dependência, commit ou deploy.

## 02. Estado inicial

Foram lidos os relatórios obrigatórios das Etapas 06–09, Git/diff, .htaccess, endpoints, helpers, consumidores JS e testes. HEAD: 67a2fe0, com alterações anteriores sem commit.

A configuração efetiva autorizada continuou em estudai_teste_2026, estudai_teste_app@localhost, MySQL 8.0.45, IA desativada. Banco de testes: 42 tabelas, 282 registros; lote diário, respostas diárias, resultados diários e tabela genérica de tentativas vazios. O plano ID 2 e seu evento já existiam.

Uma inspeção CLI no sandbox não conseguia ler o .env protegido e revelou os defaults antigos de conexão. Nenhuma escrita foi executada nessa condição. As execuções seguintes com acesso ao arquivo privado verificaram configuração antes de conectar e identidade real antes de testar. O .env e a conta da aplicação no Apache não foram alterados.

Apache compartilhado PID 25536 e MySQL worker 7032 permaneceram ativos. A restrição do EstudAI segue em Require local; os listeners gerais dos outros projetos não foram modificados. Little Rio Manager/8082 respondeu 200.

## 03. Arquitetura do fluxo de questões

A recuperação reutiliza as tabelas e a interface existentes. O serviço pequeno dailyQuestions.php reúne validação, transações e serialização comuns aos dois endpoints afetados. Não foi substituída a arquitetura do projeto.

| Função JS / consumidor | Endpoint | Método | Ação | SQL principal | Tabelas | Permissão |
| --- | --- | --- | --- | --- | --- | --- |
| loadQuestoes | questoes.php | GET | carregar | SELECT lote, catálogo, alternativas, respostas próprias e resultado | questoes_do_dia, questoes, questoes_alternativas, respostas_usuario, desempenho_diario | SELECT; READ ONLY |
| prepararQuestoes, clique explícito | questoes.php | POST | preparar | GET_LOCK; SELECT; INSERT do lote; RELEASE_LOCK | questoes, questoes_alternativas, questoes_do_dia | SELECT; INSERT apenas no lote |
| responder, clique na alternativa | questoes.php | POST | responder | Bloqueio do perfil; SELECT; INSERT da primeira resposta | estudo_perfis, lote/catálogo/alternativas, respostas_usuario, desempenho_diario | SELECT; INSERT apenas na resposta |
| finalizarDia | questoes.php | POST | finalizar | Bloqueio do perfil; agregação das respostas; INSERT único | estudo_perfis, lote/catálogo/alternativas, respostas_usuario, desempenho_diario | SELECT; INSERT apenas no resultado |
| loadHistorico | historico.php | GET | sem ação | SELECT resultados finalizados e streak | desempenho_diario | SELECT; READ ONLY |
| loadMeta | metas.php | GET | carregar | SELECT meta e agregado semanal em memória | metas_semanais, respostas_usuario | SELECT; READ ONLY |
| loadRevisaoStats | revisao.php | GET | estatisticas | SELECT último erro diário ainda não corrigido por revisão | respostas_usuario, questoes, questoes_alternativas, questoes_respostas_usuario | SELECT; READ ONLY |
| loadRevisao | revisao.php | GET | carregar | Mesmo conjunto, filtro/limite e tentativa própria do dia | mesmas tabelas; alternativas | SELECT; READ ONLY |
| responderRevisao | revisao.php | POST | responder | Bloqueio do perfil; SELECT elegibilidade/alternativa; INSERT de tentativa | estudo_perfis, respostas_usuario, questoes, questoes_alternativas, questoes_respostas_usuario | SELECT; INSERT apenas na tentativa |
| loadEstatisticas | estatisticas.php | GET | geral, evolucao | Agregados diários, matérias, dificuldade, datas; dados de rotina separados | respostas_usuario, questoes, desempenho_diario; tarefas_estudo e simulados_planejados em campos separados | SELECT; READ ONLY |
| loadConquistas | conquistas.php | GET | listar | SELECT catálogo com desbloqueios próprios | conquistas, conquistas_usuario | SELECT; READ ONLY |
| verificarConquistas, fluxo anterior | conquistas.php | POST | verificar | Rotina antiga contém INSERT IGNORE de desbloqueios | conquistas_usuario e fontes de indicadores | Continua bloqueado; não chamado pelo fluxo recuperado |

Separação dos modos:

| Modo | Persistência | Estado nesta etapa |
| --- | --- | --- |
| Diário | respostas_usuario; desempenho_diario | Recuperado |
| Revisão de erros diários | questoes_respostas_usuario, exclusivamente pelo escritor liberado de revisão | Recuperado, sem sobrescrever resposta diária |
| Exercícios planejados | respostas_exercicios_planejados; helper antigo também pode usar a tabela genérica | Endpoint bloqueado |
| Simulados legados | simulado_respostas / simulado_tentativas | Bloqueados |
| Simulados planejados | tabelas próprias de simulados planejados | Escrita/execução bloqueadas |

A tabela genérica não tem coluna de modo. Ela estava vazia neste banco e, no conjunto HTTP autorizado, agora recebe somente revisões. A liberação futura de exercícios que também a utilizem exige rever essa separação antes de habilitar o endpoint. Não se declara migração ou classificação de tentativas históricas.

## 04. Base disponível

A inspeção somente leitura do banco de testes confirmou 32 questões distintas, 160 alternativas, 13 matérias, 42 conteúdos e 22 conquistas. Todas as questões estão aprovadas, têm enunciado/explicação preenchidos e exatamente cinco alternativas, uma correta.

As chaves existentes foram conferidas: alternativa única por questão/letra; lote único por data/questão; resposta diária única por usuário/questão/data; desempenho único por usuário/data; FKs para usuário, questão e alternativa. A FK isolada de alternativa não garante que ela pertença à questão enviada; essa relação passou a ser validada no servidor.

Distribuição: Matemática 8; Português 4; Biologia/Química/Geografia 3 cada; Física/História/Sociologia 2 cada; Artes/Inglês/Literatura/Educação Física/Filosofia 1 cada. Dificuldades: 10 fáceis, 17 médias, 5 difíceis.

O catálogo de recuperação preservado corresponde às 32 questões distribuídas por 32 conteúdos; dez dos 42 conteúdos não possuem questão. Os hashes do catálogo no banco permaneceram iguais ao início.

## 05. Limitações da quantidade de questões

Os 160 registros de alternativas não representam 160 questões. Há apenas 32 questões e, nos conteúdos cobertos, somente uma por conteúdo. Isso não sustenta grandes lotes específicos, variedade extensa ou simulados completos equilibrados.

O lote diário padrão mantém até 20 questões existentes, com seleção aleatória como no fluxo anterior. A preparação aceita quantidade limitada a 1–20 para uso controlado; os testes prepararam somente duas, ambas de Português. Um lote já existente é reutilizado integralmente, sem crescer durante o dia e mudar seu denominador.

Não foram inventadas questões, completadas lacunas, importados seeds ou alterados enunciados/gabaritos. A recuperação funcional não certifica profundidade ou qualidade pedagógica de toda a base.

## 06. GETs avaliados

Foram inspecionados e testados: questões/carregar; histórico; revisão/carregar e estatisticas; estatísticas/geral, evolucao e estudai_geral; conquistas/listar; metas/carregar; preferências/carregar. Plano, tarefas e calendário foram cobertos pelas regressões anteriores pertinentes.

Os GETs permanecem sob transação READ ONLY, com ação decodificada validada no PHP. Nenhum inicializa lote, registra tentativa, materializa resultado, concede conquista ou programa revisão.

Snapshots de conteúdo das tabelas relevantes foram comparados antes/depois das consultas com dados vazios e preenchidos. A regressão de configuração também confirmou erro MySQL 1792 em UPDATE WHERE 1=0 dentro da transação de leitura.

## 07. Correções de efeitos colaterais

Foi removida a preparação automática anterior à resposta/finalização. Agora o lote nasce somente em POST preparar, com autenticação e CSRF. GET vazio continua vazio e mostra o botão explícito de preparação na área já existente.

A criação do lote é transacional e serializada por GET_LOCK nomeado com banco/data. O bloqueio é liberado em finally, inclusive após falha. A tentativa inicial de bloqueio de linha no catálogo foi rejeitada pelas permissões; a solução final evita conceder UPDATE em questões.

A rotina antiga de cron permanece no código, mas seu endpoint continua negado. Ela não é chamada pelo novo fluxo.

## 08. Exposição antecipada de respostas

O contrato anterior anexava resposta_correta, alternativa_correta_id, flags correta nas alternativas e explicacao mesmo sem tentativa. O problema era no JSON, não apenas na visibilidade do DOM.

A consulta inicial diária/de revisão agora remove esses campos de correção e a explicação para questões não tentadas. Gabarito e respostas são filtrados pelo usuário da sessão. Alternativas públicas mantêm somente ID, letra e texto.

Após a tentativa, o servidor devolve acerto, letra correta e explicação. A carga posterior preserva o feedback das questões respondidas; o JSON da outra conta continua sem correção enquanto ela não responder. A revisão correta deixa a fila de pendências; seu resultado imediato foi exibido no Chrome.

## 09. Correções de segurança

Autenticação e CSRF foram exigidos nos quatro comandos liberados. IDs positivos, formato de letra, alternativa inexistente, alternativa de outra questão, inconsistência letra/ID, quantidade, tempo, corpo excessivo e JSON inválido foram validados.

O endpoint aceita apenas o método/ação aprovado e recusa conflito com action do corpo. Identidade vem da sessão, ignorando usuario_id forjado. SQL com dados externos usa parâmetros; o limite SQL provém de inteiro validado e limitado.

Erros de domínio retornam mensagens controladas; falhas de persistência têm rollback e resposta genérica, sem SQLSTATE, credenciais ou stack trace na API. Os POSTs do frontend tratam HTTP, JSON, rede e respostas vazias sem afirmar salvamento.

## 10. Permissões MySQL

Adições somente a estudai_teste_app@localhost em estudai_teste_2026:

| Tabela | Adição | Justificativa |
| --- | --- | --- |
| questoes_do_dia | INSERT | Preparação explícita |
| respostas_usuario | INSERT | Primeira resposta diária |
| desempenho_diario | INSERT | Finalização única |
| questoes_respostas_usuario | SELECT, INSERT | Recuperação/deduplicação de revisão |

Final: 23 instruções SHOW GRANTS, contra 22 no início. Nenhum privilégio administrativo, DELETE, CREATE, ALTER, DROP, GRANT OPTION ou concessão global/schema foi adicionado.

A serialização individual usa a linha única de estudo_perfis e os privilégios já existentes dessa tabela; nenhum UPDATE novo foi concedido para bloquear o catálogo ou usuarios. A aplicação continua sem acesso aos bancos históricos. A credencial administrativa foi usada somente para auditoria/concessões específicas, fora da aplicação e sem persistir senha em arquivo.

## 11. Endpoints liberados

Adições HTTP desta etapa: POST questoes.php?action=preparar|responder|finalizar; POST revisao.php?action=responder; GET estatisticas.php?action=geral|evolucao.

As leituras anteriores de questões, revisão, histórico, metas, conquistas, estatísticas do painel, plano, tarefas e calendário continuam autorizadas. A liberação depende de método e ação; autenticação/CSRF são verificados pelo PHP. O service worker continua excluindo APIs do cache.

## 12. Endpoints bloqueados

Continuam bloqueados cron, OpenRouter/IA remota, diagnóstico remoto, revisão semanal automatizada, simulados completos, exercícios planejados não validados, geração remota/em lote fora do treino diário explícito, administração, exclusões, alterações de tarefas e operações de calendário não autorizadas.

Conquistas/verificar e gravação de metas/anotações também permanecem negadas. Conquistas/listar é leitura: esta etapa não concede badges automaticamente. Os consumidores recuperados deixaram de disparar a verificação bloqueada após resposta/finalização/revisão.

A tela ainda contém controles de outros módulos. Sua presença visual não representa autorização ou validação dessas funcionalidades.

## 13. Testes de recuperação de questões

GET sem lote retornou lista vazia e zero progresso, sem preparar treino. POSTs concorrentes de preparação selecionaram somente duas questões existentes; chamadas repetidas reutilizaram o lote.

A carga retornou cinco alternativas por questão, total real 2 e nenhum gabarito antecipado. Consulta de questão fora do lote foi negada mesmo sendo do catálogo; ID inexistente também foi negado.

O teste real de preparação com falha antes de commit inseriu transitoriamente as associações e fez rollback, deixando todas as tabelas de progresso com as contagens anteriores.

## 14. Testes de envio de respostas

A conta A respondeu por HTTP: uma correta e uma incorreta. A conta B fez o mesmo pelos cliques reais nas alternativas no Chrome. O comportamento existente de envio pelo clique na alternativa foi preservado; não foi criada uma nova etapa visual de seleção/envio.

CSRF foi enviado pelo serviço HTTP existente. Clique duplo é guardado no frontend. Requisições diretas repetidas também são protegidas no servidor.

Cada conta persistiu exatamente duas respostas. A UI permitiu finalizar somente após responder todo o lote; o estado final manteve o botão desabilitado.

## 15. Testes de correção

O servidor confrontou a alternativa selecionada com a correta da questão existente. A resposta correta produziu acertou=true; a errada produziu false, com correção e explicação após a tentativa.

O Chrome exibiu as cores de acerto/erro e a explicação. Foi removido o avanço automático após 600 ms, que impediria ler o feedback imediato. A navegação continua pelos controles existentes.

A revisão correta de B exibiu feedback e retirou sua pendência, preservando os 50% diários originais.

## 16. Testes de persistência

GET após resposta/reload recuperou a marcação, o acerto e a explicação próprios. Finalização persistiu 1 acerto e 1 erro por conta. Histórico, evolução e meta corresponderam às linhas reais.

Repetir a finalização preservou o tempo original da conta A, mesmo enviando outro valor. A persistência utiliza INSERT, sem regravar a primeira resposta ou o resultado finalizado.

Falhas controladas de commit foram exercitadas na preparação, resposta e revisão com PDO real/subclasse de teste. Cada operação encerrou a transação e não deixou linhas parciais. Não existe parâmetro de falha na API nem limpeza DML posterior.

## 17. Testes de duplicação e concorrência

A preparação global é serializada por bloqueio nomeado. Respostas/finalização/revisão são serializadas pela linha do perfil da própria conta dentro da transação.

Foram usadas sessões PHP distintas para a mesma conta, evitando confundir a trava de sessão com proteção de banco. Respostas e finalizações concorrentes produziram somente um registro por chave. As chaves únicas diárias permanecem como proteção adicional.

Repetir a mesma alternativa retorna o resultado original; tentar trocá-la retorna 409. Revisões repetidas/concorrentes da tentativa já persistida não criaram outra linha nem alteraram o resultado. Na revisão, a regra recuperada é uma tentativa por usuário/questão/dia; repetir outra alternativa no mesmo dia retorna 409. Uma revisão errada permanece pendente para dia seguinte.

A tabela de revisão não tem UNIQUE por dia: a garantia aplica-se ao conjunto atual de escritores liberados, que respeita o bloqueio de perfil. Um futuro escritor deve usar o mesmo protocolo ou exigir outra solução validada.

## 18. Isolamento entre usuários

Depois das respostas de A, B continuou recebendo lote sem respostas/gabarito e progresso zero. Os resultados próprios foram preservados após ambos responderem. Parâmetros usuario_id não mudaram a identidade da sessão.

B não pôde revisar o erro de A quando não tinha erro próprio elegível. Depois da revisão correta de B, sua fila ficou vazia e a fila de A continuou com um erro. Forjar usuário no GET não revelou a fila de A.

Plano/evento de A continuaram isolados de B na regressão real da Etapa 09.

## 19. Revisões

A fila usa o último resultado diário de cada questão e remove o erro quando existe revisão correta posterior à resposta diária. A comparação usa timestamp, permitindo resolver o erro no mesmo dia, ao contrário da regra antiga que exigia data estritamente maior.

A tentativa vai para questoes_respostas_usuario, sem UPDATE de respostas_usuario e sem alterar desempenho_diario. O filtro Português/portugues foi corrigido para normalização explícita Unicode; a abordagem de transliteração dependente do ambiente falhou no teste e foi substituída.

A fila é calculada por leitura, não é um agendamento automático. Não foram criados registros em revisoes_programadas, habilitados cron ou revisão semanal. A concessão de conquista “revisador” permanece fora da liberação.

## 20. Estatísticas

Para ambas as contas: total diário 2, acertos 1, erros 1, percentual 50%. A revisão não acrescenta uma terceira questão às estatísticas diárias nem converte o erro original em acerto.

Meta semanal: duas respostas, calculadas sem criar/atualizar meta. Histórico e evolução diária registram o dia finalizado; antes de finalizar, respostas já afetam meta e estatísticas, mas o histórico de dias concluídos ainda não ganha um dia.

O frontend normaliza chaves acentuadas dos agregados por matéria e das pendências de revisão. O indicador de revisão é limpo quando total volta a zero. O denominador do resultado usa o lote real, sem fallback artificial de 20.

O ramo geral deixou de consultar simulado_tentativas legado, que não tem permissão no escopo atual. Indicadores de tarefas e simulados planejados permanecem em campos próprios, separados dos agregados diários. Não houve reescrita geral do módulo de estatísticas.

## 21. Integração com o frontend

Mantidos HTML/CSS, IDs, classes, navegação e componentes existentes. As mudanças funcionais em app.js cobrem preparação explícita no estado vazio, POSTs com ação na query/CSRF, feedback imediato, guarda de clique duplo, mensagens de erro, recuperação após reload e atualização dos módulos de leitura relacionados.

Anotações bloqueadas deixaram de ser consultadas automaticamente ao renderizar questão em modo de recuperação. A presença do botão Anotação não significa que sua gravação esteja liberada.

No viewport estreito, o acesso a Questões é pelo botão existente no dashboard; não existe aba móvel dedicada. O teste foi ajustado a esse fluxo real.

## 22. Validação visual

Executada em Chrome/157.0.8103.39 real, headless, perfis descartáveis, desktop 1365 × 900 e viewport 390 × 844. Foram abertas/inspecionadas 13 capturas de questões antes/depois, resultado, dashboard/progresso, estatísticas, revisão antes/depois, vazio e tela estreita, incluindo capturas completas/roladas abaixo da dobra.

O envio original das respostas de B e da revisão foi feito por cliques reais. As execuções intermediárias foram retomadas sobre os registros existentes, sem apagar respostas. Houve correções do próprio teste: matéria inicialmente presumida errada, expectativa de botão antes/depois da finalização, espera pelo carregamento em vez do placeholder, navegação móvel e variável antiga do relatório. Os relatórios intermediários foram preservados.

A execução funcional final no estado retomado passou 36 verificações; uma execução somente para detalhes visuais passou seis e produziu quatro capturas adicionais. Não se somam os checks intermediários sobrepostos como novos cenários independentes.

Zero exceções não tratadas/erros de worker no fluxo final. Não se observou impedimento visual nas telas testadas. PWA instalado, aparelho físico, outros navegadores e acessibilidade completa não foram certificados. O perfil pessoal não foi aberto, nem seus dados apagados.

## 23. Testes negativos

Cobertos: anônimo; sessão encerrada/expirada; CSRF ausente/incorreto; questão inexistente ou fora do lote; IDs inválidos/array; alternativa inexistente, de outra questão ou incompatível com letra; repetição com outra resposta; finalização incompleta; método/ação indevidos; action duplicado/array/conflitante; corpo excessivo; JSON inválido; matéria vazia; progresso vazio; acesso forjado a dados alheios; falha controlada de persistência.

No frontend: HTTP 401/403/419/409/500 conforme o manipulador; JSON inválido; rede rejeitada; resposta vazia; clique duplo; falha sem resultado falso. Os 29 cenários usam funções reais em DOM simulado, sem rede real.

Todas as 32 questões atuais são aprovadas. Não se arquivou uma questão para fabricar teste negativo; a elegibilidade por status foi inspecionada e a questão existente fora do lote foi testada por HTTP. O caso de catálogo inteiramente vazio não foi criado no banco.

## 24. Testes de regressão

| Conjunto executado | Resultado |
| --- | --- |
| Integração inicial de questões, SQL/HTTP | 387 verificações |
| Conferência preenchida, GETs, isolamento e replays de revisão | 139 verificações |
| Rollback da preparação, antes do lote definitivo | 5 verificações adicionais |
| Rollbacks de resposta e revisão | 5 cada, incluídas na fase inicial |
| Frontend de questões/falhas | 29 cenários |
| Inicialização do dashboard | 23 cenários |
| Planejamento frontend | 12 cenários |
| Service Worker inalterado | 21 cenários |
| HTTP/assets/proteções Etapa 09 | 124 verificações, 19 assets |
| Configuração/READ ONLY/históricos/guardião de IA | 12 verificações |
| Chrome, regressão da Etapa 09 com progresso preenchido | 58 verificações |
| Chrome, questões/revisão no estado retomado | 36 verificações |

Cadastro vazio/senha curta/duplicidade, login/logout, sessão, onboarding de leitura, dashboard, plano existente, rotina, calendário, isolamento e offline foram exercitados pela regressão real. Cadastro positivo e novo salvamento de onboarding não foram repetidos.

Os testes antigos de escrita que exigem parar a instância compartilhada não foram executados, para preservar Little Rio Manager. Não foram gerados novos planos.

## 25. Arquivos alterados

Sete arquivos existentes alterados nesta etapa:

- .htaccess.
- app/src/pages/app.js.
- server/api/questoes.php.
- server/api/revisao.php.
- server/api/estatisticas.php.
- server/api/historico.php.
- server/api/metas.php.

Seis arquivos novos:

- server/services/dailyQuestions.php.
- tests/questions_transaction_stage10.php.
- tests/questions_stage10.py.
- tests/questions_frontend.test.js.
- tests/browser_stage10.mjs.
- docs/etapa10-questoes-revisoes-progresso.md.

O service worker e os demais arquivos anteriores foram preservados por inventário. As evidências privadas estão em C:\Users\player\AppData\Local\EstudAI\Recovery\20261111_185837_stage10_726498d6, fora da área pública e com ACL restrita.

Reexecução segura depois do fluxo persistido:

    python -B tests/questions_stage10.py --verify-only --private-dir <privado10> --accounts <contas06>
    node tests/questions_frontend.test.js <fixture_dashboard08>
    node tests/browser_stage10.mjs <privado10> <contas06> --details-only

O modo inicial sem --verify-only exige estado apropriado e foi usado apenas antes dos registros definitivos. Não repetir preparação/rollback inicial após o lote existente. Os testes foram escritos para a fixture desta etapa/data, não para preencher dias indefinidamente.

## 26. Estado dos bancos

Auditoria administrativa somente leitura comparou todas as tabelas, colunas e DDL normalizado antes/depois. Conteúdo foi comparado por fingerprints de todas as linhas, não só por contagem.

| Banco | Antes | Depois | Resultado |
| --- | --- | --- | --- |
| vestibular_estudos | 42 tabelas / 641 registros | Igual | Conteúdo e estrutura intactos |
| estudai_demo_2026 | 42 tabelas / 641 registros | Igual | Conteúdo e estrutura intactos |
| estudai_teste_2026 | 42 tabelas / 282 registros | 42 tabelas / 291 registros | Somente nove inclusões autorizadas |

Deltas: questoes_do_dia +2; respostas_usuario +4; desempenho_diario +2; questoes_respostas_usuario +1. Todas as demais tabelas do teste ficaram idênticas, incluindo catálogo, usuários, onboarding, plano, tarefas, evento e histórico de IA.

Schema/FKs/índices foram preservados. A comparação de DDL desconsidera somente a opção AUTO_INCREMENT: inserts revertidos podem consumir IDs, sem deixar registros ou reiniciar contadores. Nenhum schema, migration, seed, DROP ou limpeza DML foi executado.

Backup original íntegro. Aplicação sem privilégios históricos; concessões finais coincidiram com as quatro instruções autorizadas. MySQL não foi reiniciado.

## 27. Estado do Git

HEAD permaneceu 67a2fe0, índice vazio, sem commit ou deploy. O estado sujo anterior foi preservado. .env continua ignorado/não rastreado, sem mudança de conteúdo. Nenhuma credencial foi adicionada aos arquivos versionáveis.

Verificações finais: php -l nos PHPs modificados/novos; node --check no JS modificado e testes novos; AST Python; Apache Syntax OK; git diff --check; git status. Os avisos de futura conversão LF/CRLF não motivaram normalização geral.

Os 29 arquivos .conf do Apache mantiveram seus hashes. Portas 80/443/8081 nas interfaces originais e 8082 em loopback preservadas; aviso conhecido de DocumentRoot ausente da porta 8081 permaneceu. Acesso remoto a partir de outro equipamento não foi testado; Require local continua sendo a proteção específica do EstudAI.

## 28. Pendências

Não restou condição obrigatória de conclusão sem comprovação no fluxo executado. Limites mantidos explícitos:

- Base de somente 32 questões, dez conteúdos sem cobertura e pouca diversidade por conteúdo.
- Uma tentativa de revisão por questão/dia; futuras regras pedagógicas precisam ser definidas antes de ampliar tentativas.
- A tabela genérica de revisão não pode receber escritores de outros modos sem rever a separação.
- Concessão automática de conquistas, anotações, alterações de metas, exercícios planejados, simulados e revisão semanal permanecem fora da aprovação.
- Sem teste destrutivo de catálogo vazio/questão arquivada, sem aparelho físico/PWA standalone e sem teste externo de rede.
- Ensaio no Chrome normal e equipamento da apresentação recomendado.

Para 17/11, o lote é por data: uma nova data retorna vazio até o usuário clicar em Preparar treino de hoje. Não foi criado lote futuro nem habilitado cron. As contas já testadas em 11/11 continuam com seus resultados desse dia no histórico.

Como o PWA foi preservado, abrir online e recarregar permite atualizar o app.js pela estratégia existente. Se houver código antigo no perfil pessoal, conferir o registro específico do EstudAI; não limpar todos os dados de localhost ou os caches de outros projetos.

## 29. Próxima etapa recomendada

Analisar este relatório e ensaiar o fluxo aprovado com as contas fictícias no equipamento da apresentação. Nenhuma nova etapa foi iniciada.

Questões diárias, respostas, correção, persistência, histórico, progresso e revisão de erros estão recuperados no escopo descrito. Simulados, exercícios planejados e integração OpenRouter aguardam definição e autorização próprias.
