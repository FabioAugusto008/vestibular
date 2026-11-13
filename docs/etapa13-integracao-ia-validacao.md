# Etapa 13 — Integração de IA e validação controlada

Data de execução 13/11/2026. Projeto C:\xampp\htdocs\estudAi_1.1.

## 01. Resumo executivo

Etapa parcialmente concluída. Cliente, validações, transações, limites, contratos frontend e fallback foram recuperados e testados. As tentativas reais alcançaram a OpenRouter, porém as saídas obtidas impediram validar uma funcionalidade completa como produzida por IA real.

Foram feitas exatamente três solicitações à API OpenRouter, contando conservadoramente a consulta pública ao catálogo. As outras duas foram gerações sequenciais. O planejamento retornou HTTP 200 com saída incompleta e acionou fallback. O diagnóstico retornou HTTP 400. Redação remota ficou nos mocks.

O banco de testes recebeu um plano local para a segunda conta fictícia, seus cinco vínculos/históricos de planejamento, um registro de histórico técnico do plano e uma redação fictícia com orientação local. As 326 linhas anteriores permaneceram idênticas. O teste passou a ter 334 linhas. Os bancos históricos e o backup permaneceram intactos.

O estado final usa ESTUDAI_AI_DISABLED=true. O limite privado também registra as duas gerações consumidas. Retomar chamadas exige análise dos resultados e nova autorização de orçamento.

## 02. Estado inicial do repositório

Foram lidos os relatórios das Etapas 07–12, documentação anterior de segurança/OpenRouter/IA, testes, Git e código efetivo. Os 156 arquivos iniciais coincidiram com o inventário final da Etapa 12. HEAD 67a2fe0, alterações anteriores presentes e índice vazio.

Configuração efetiva e PDO confirmaram estudai_teste_2026, estudai_teste_app@localhost, MySQL 8.0.45/3306 e 29 entradas de SHOW GRANTS. Histórico de IA e redações estavam vazios. O catálogo continuava com 32 questões e 160 alternativas.

O Apache estava desligado. A configuração existente passou em httpd -t e a instância compartilhada foi iniciada para recuperar os acessos locais. MySQL80 já estava ativo.

## 03. Arquitetura real da IA

O navegador chama APIs PHP locais pelo serviço HTTP com sessão e CSRF. estudaiService.php prepara prompts e valida JSON. openrouterClient.php concentra o transporte. A propriedade dos registros vem da sessão e das consultas PDO.

| Consumidor | Endpoint / método / ação | Serviço e prompt | Entrada e saída | SQL / tabelas | Fallback, timeout e repetição |
| --- | --- | --- | --- | --- | --- |
| gerarDiagnostico | diagnostico.php POST gerar | estudaiGerarDiagnostico / estudaiDiagnosticoPrompt | Perfil salvo → sete campos educacionais validados | SELECT perfil e histórico, bloqueio do perfil, INSERT ia_historico em sucesso | Indisponibilidade explícita, até 30 s, sucesso reutilizado por impressão do perfil |
| Inicialização do painel | diagnostico.php GET carregar_ultimo | diagnosticoUltimo | Resultado próprio ou null | SELECT ia_historico, READ ONLY | Sem geração ou gravação |
| gerarPlanoEstudos e EstudAIApi.planoEstudos | plano-estudos.php POST gerar_semana | planoGerarOuRecuperarSemanal / estudaiPlanoSemanalPrompt | Perfil, disponibilidade, catálogo, janela e diagnóstico salvo → plano validado | SELECT e bloqueio do perfil, INSERT plano/controle/versão/item/tarefa/evento/histórico | Gerador local existente, até 30 s, plano da semana recuperado antes da chamada |
| replanejarSemana | plano-estudos.php POST replanejar_semana | Contexto e gerador semanal antigos | Motivo, execução e plano atual → proposta | Escritor antigo reutiliza o plano da mesma semana | Escrita bloqueada |
| Sem interface anual completa | plano-estudos.php POST gerar_anual | estudaiGerarPlanoAnual / estudaiPlanoAnualPrompt | Perfil, meses, semanas e tarefas → JSON anual | Persistência legada em planos/itens/tarefas/eventos | Contrato testado com mocks, rota bloqueada |
| Backend de revisão semanal | revisao-semanal-ia.php | weeklyReview.php / estudaiRevisaoSemanalPrompt | Agregados próprios e período → análise/decisão | Rotina legada combina histórico, versões, ajustes e próxima semana | Aplicação e cron bloqueados |
| salvarRedacao / analisarRedacao | redacao.php POST salvar / analisar | redacaoProcessar / prompt orientativo existente | Tema e texto fictícios → cinco competências e sugestões | SELECT com dono, bloqueio do perfil, INSERT ou UPDATE restrito em redacoes_enem | Orientação local identificada, até 30 s se recurso remoto aprovado, resultado idêntico reutilizado |
| loadRedacoes | redacao.php GET listar | redacaoMap | Até 30 redações próprias | SELECT redacoes_enem, READ ONLY | Sem análise automática |
| EstudAIApi.mensagem | ia.php POST mensagem | Cliente e prompt geral antigos | Texto livre | Sem fluxo liberado | Bloqueado |
| Exercícios, revisões de conteúdo e simulados | Endpoints recuperados anteriores | Base aprovada local | Questões existentes | Tabelas dos modos anteriores | Geração remota permanece desativada |

As rotas remotas dependem de internet. As leituras persistidas e o gerador local dependem do backend local. Nenhuma ferramenta de banco ou execução de comandos é oferecida ao modelo.

## 04. Recursos de IA identificados

Categorias solicitadas pelo usuário são A, validado com OpenRouter real, B, validado apenas com mocks, C, validado somente com fallback, D, parcialmente funcional, E, bloqueado por dependência externa, e F, não implementado.

| Recurso | Categoria final | Comprovação |
| --- | --- | --- |
| Cliente e transporte OpenRouter | D | Mocks aprovados e dois contatos reais, sem saída final utilizável |
| Diagnóstico educacional | D | Contrato e persistência transacional com mocks, tentativa real HTTP 400 |
| Planejamento semanal no produto | C | Plano local persistido após rejeição da resposta remota |
| Geração semanal por IA | D | Contrato válido nos mocks, saída real incompleta |
| Replanejamento completo | D | Backend existente sem substituição/versionamento seguro demonstrado |
| Planejamento anual de backend | B | Gerador e validação com mocks |
| Interface anual integrada | F | Interface completa ausente |
| Análise de revisão semanal | B | Contrato, decisões e datas testados com mocks |
| Aplicação automática de revisão | D | Fluxo legado composto permanece bloqueado |
| Redação no produto | C | Salvamento, histórico, isolamento e orientação local validados |
| Feedback remoto de redação | B | Resposta estruturada validada com mocks |
| Geração de exercícios/simulados por IA | F | Desativada no produto que usa base aprovada |

Nenhum recurso recebeu categoria A. A obtenção de HTTP 200 confirmou o contato com o provedor, enquanto a validação do conteúdo rejeitou a saída.

## 05. Configuração OpenRouter

Endpoint confirmado em https://openrouter.ai/api/v1/chat/completions. Mantida autenticação Bearer exclusivamente no PHP. O cliente usa max_tokens, JSON não streaming, temperature e response_format json_object. A documentação oficial confirma o endpoint e o parâmetro de limite de saída. [API de chat](https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion)

Precedência verificada no código segue ambiente do processo, $_ENV, $_SERVER e .env. Chave explicitamente vazia no ambiente agora mascara o valor do arquivo. A flag de desativação aceita true, TRUE, 1, yes e on. A configuração padrão é desativada.

## 06. Modelo configurado

O identificador anterior era liquid/lfm-2.5-1.2b-instruct:free. O catálogo vivo deixou de listá-lo. Foi selecionado liquid/lfm-3-1.7b:free, da mesma família, com preços de entrada e saída iguais a zero, suporte aos parâmetros usados e ausência de expiração indicada no catálogo consultado.

A troca foi registrada como configuração reversível. O valor anterior e os controles alterados estão em evidência privada sem chave. .env.example e o default PHP refletem o identificador verificado.

## 07. Disponibilidade do modelo

Uma consulta GET pública ao catálogo retornou HTTP 200 em aproximadamente 129 ms. Foram examinadas alternativas gratuitas e seus parâmetros. O selecionado declarava 262.144 tokens de contexto e até 8.192 tokens de saída no provedor listado. [Catálogo oficial](https://openrouter.ai/api/v1/models)

O fabricante inclui português entre os 16 idiomas do LFM3-1.7B. A resposta estruturada em português ainda precisa de comprovação real neste projeto. [Ficha oficial da Liquid AI](https://huggingface.co/LiquidAI/LFM3-1.7B)

A geração semanal retornou o modelo selecionado e HTTP 200. A disponibilidade de uma rota de inferência para a chave ficou demonstrada nessa requisição. Qualidade, continuidade de disponibilidade e quota restante da conta permanecem sem certificação.

## 08. Política de custos e limites

Foram contabilizadas três solicitações à API, incluindo o catálogo público, e duas gerações. Páginas públicas de documentação foram consultadas pela ferramenta de pesquisa, sem autenticação ou inferência.

O cliente aceita somente o modelo configurado com sufixo :free, recusa substituição por modelo pago e impõe preço máximo zero de entrada/saída no roteamento. O limite privado persistido é de duas gerações nesta etapa. O bloqueio usa flock e reserva antes da rede, preservando o consumo em caso de falha ou interrupção.

Os limites gerais de modelos gratuitos são adicionais ao limite local. A quota individual da chave não foi consultada por uma requisição extra. [Limites oficiais](https://openrouter.ai/docs/api/reference/limits) [Roteamento e preço máximo](https://openrouter.ai/docs/guides/routing/provider-selection)

## 09. Segurança da chave

A chave continua somente no arquivo privado de configuração do backend. Nenhum valor foi incluído em JS, HTML, URLs, Git, mensagens de erro ou capturas.

A ativação temporária exigiu banco e conta PHP exatos, usuário fictício autorizado, recurso aprovado, modelo gratuito e arquivo de orçamento fora do DocumentRoot. A aprovação temporária foi limitada à segunda conta fictícia e a plano/diagnóstico.

Ao terminar as três solicitações, .env e .htaccess voltaram a desativar IA. As reservas reais permaneceram registradas. Os testes de orçamento usam arquivos separados e modelo sentinela.

## 10. Correções no cliente HTTP

O cliente limita mensagens, tamanho da requisição e resposta, tokens, temperatura e parâmetros opcionais. Aceita somente o destino oficial HTTPS, recusa redirects e mantém verificação TLS. Conexão limitada a oito segundos e duração configurada em 30 segundos, com teto de 45.

Respostas vazias, não JSON, incompletas, sem conteúdo e HTTP fora de 2xx geram categorias controladas. HTTP 429 conserva exceção específica. Corpo ou mensagem bruta do provedor não são devolvidos ao estudante.

Não há retry automático. Falhas de autenticação, transporte ou provedor interrompem novas reservas. Conteúdo inválido segue para o fallback previsto, sem repetir a mesma geração. Metadados privados registram recurso, modelo, status, duração, categoria e custo quando informado.

## 11. Validação das respostas

validation.php valida textos, listas, tipos, limites, datas reais, horários, duração, matérias do perfil, conteúdo cadastrado e enumerações. HTML executável é rejeitado. IDs gerados pelo modelo são descartados.

Plano semanal exige tarefas sem sobreposição, dentro dos blocos reais e cobrindo os dias obrigatórios. Campos inválidos rejeitam o conjunto remoto inteiro. A normalização ocorre depois da validação.

O parser aceita objeto JSON e cerca Markdown bem formada. JSON parcial, texto adicional, arrays no lugar do objeto e conteúdo excessivo são rejeitados. A validação estrutural não certifica qualidade pedagógica ou idioma de todos os textos livres.

## 12. Diagnóstico educacional

O perfil agora inclui disponibilidade, matérias e reforços salvos. A resposta exige os campos educacionais previstos e matérias prioritárias pertencentes ao perfil.

Geração bem-sucedida e histórico são transacionais. O bloqueio do perfil e a impressão da entrada permitem reutilizar resultado válido sem nova chamada. O teste inspecionou o registro ainda não confirmado e provocou rollback, evitando deixar diagnóstico mockado na aplicação.

A tentativa real retornou HTTP 400. Um parâmetro opcional de reasoning havia sido usado nessa tentativa e foi removido depois. A relação causal exata com o 400 não ficou demonstrada. O payload final sem esse parâmetro passou pelos mocks e permanece sem novo ensaio externo por esgotamento do orçamento.

Não há diagnóstico remoto salvo. A interface apresenta indisponibilidade. A inicialização passou a consultar o último diagnóstico por GET, totalizando 15 leituras na fixture completa. Esse GET não gera conteúdo.

## 13. Planejamento semanal inteligente

A coordenação bloqueia o perfil e procura o plano da semana antes de chamar IA. Um plano existente é recuperado imediatamente. O catálogo de conteúdos ativos acompanha a entrada e restringe os pares matéria/conteúdo aceitos.

Uma resposta remota inválida aciona o algoritmo local já recuperado. A segunda conta fictícia recebeu o plano ID 4, origem fallback, com uma tarefa de Redação em 16/11, 18h–19h. O plano ID 2 da primeira conta permaneceu igual.

A chamada real semanal retornou HTTP 200, cerca de 12,8 segundos, custo informado zero e categoria incomplete_response. O motivo exato de término não foi guardado nesse primeiro ensaio. Metadados posteriores passaram a incluir finish_reason e quantidade de tokens quando disponíveis.

## 14. Persistência do plano

Plano, controle, versão, item, tarefa, evento e histórico são gravados na mesma transação. A função de salvamento participa da transação externa quando chamada pelo coordenador.

Falha controlada de commit demonstrou rollback completo, inclusive quando já existia outro plano da mesma conta. Uma semana diferente foi construída somente em memória nesse ensaio. Nenhum registro desse teste ficou confirmado.

Repetições concorrentes por sessões diferentes recuperaram o plano persistido e conservaram os fingerprints. Atualização da página e nova autenticação preservaram o resultado local.

## 15. Integração com tarefas

A tarefa nova pertence à segunda conta e ao seu plano/item. Matéria, conteúdo, data, horário e duração são compatíveis com o perfil e com o gerador local anterior.

As quatro tarefas da primeira conta mantiveram seus estados, incluindo prática e simulado concluídos. Seu progresso continuou em 50%. A nova tarefa permanece pendente.

## 16. Integração com calendário

Um evento foi criado junto com a nova tarefa. Calendário da segunda conta mostra uma atividade própria em 16/11, enquanto o da primeira mantém seus quatro eventos.

As consultas continuam READ ONLY. Recarregar, navegar e repetir a recuperação do plano não duplicou eventos.

## 17. Fallback local

Mocks cobriram IA desativada, chave vazia, modelo pago recusado, timeout, rede, 401/403/404/408/429/500/503, JSON inválido e resposta vazia. Cada falha de transporte produziu no máximo uma tentativa simulada.

O fallback também foi acionado pela resposta real incompleta e persistido com origem fallback. A orientação local de redação foi persistida e recuperada com origem explícita.

Códigos técnicos ficaram fora das mensagens públicas. A leitura sanitiza o aviso técnico do novo plano já salvo, conservando os dados persistidos e o histórico privado.

## 18. Replanejamento

O frontend e o endpoint existem. O backend recolhe motivo, tarefas executadas e pendentes, porém o escritor semanal reutiliza o plano da mesma semana. Isso não demonstra um ajuste efetivo e versionado do planejamento.

A rota continua respondendo 403. Nenhuma tarefa concluída, evento ou histórico foi substituído para forçar a aprovação desse recurso. O teste HTTP confirmou o bloqueio.

## 19. Planejamento anual

Gerador, prompt, normalizador e persistência legada existem no backend. A validação passou a conferir meses, semanas, datas, tarefas e disponibilidade, reaproveitando o contrato semanal.

Contrato válido e mês inválido foram testados com mocks. Não houve chamada real, persistência anual ou interface nova. A rota anual permanece bloqueada, e a integração completa continua pendente.

## 20. Revisão semanal

Foram revisados agregados, período, origem, histórico, decisão e aplicação. A rotina antiga combina gerar análise, aplicar ajustes e criar a próxima semana. A aplicação continua bloqueada por esse acoplamento e pelas garantias transacionais insuficientes do escritor legado.

Contrato de análise e enumerações foram testados com mocks. A janela foi corrigida para analisar a semana que termina no domingo e a última semana completa nos demais dias. A semana recomendada começa após o período analisado.

Nenhum cron, ajuste automático, registro de revisão ou novo planejamento dessa rotina foi executado.

## 21. Redação com IA

O fluxo recuperado valida tema e texto, limita tema a 180 caracteres e texto a 12.000, exige ao menos 300 para análise e limita o corpo HTTP a 65.536 bytes. A correção não atribui nota oficial.

O contrato remoto exige cinco competências distintas C1–C5, comentários e listas. Resposta remota inválida gera orientação local identificada. Esse contrato remoto passou com mocks.

Uma redação fictícia foi salva e analisada localmente para a segunda conta. Reenvio idêntico, incluindo duas sessões concorrentes, recuperou o mesmo registro. A primeira conta recebeu 404 nas tentativas de alteração e lista vazia nas consultas.

Tema/texto alterados invalidam a análise anterior ao salvar um novo rascunho. Posse é validada antes de qualquer chamada. Falha de commit foi testada com rollback. A redação remota não foi chamada após o orçamento se encerrar.

## 22. Timezone e datas

O CLI PHP ainda tinha default Europe/Berlin. Os helpers da aplicação passaram a carregar America/Sao_Paulo de app.php, sem alterar php.ini ou outros projetos.

MySQL estava em SYSTEM com diferença de três horas entre NOW e UTC_TIMESTAMP, compatível com o contexto brasileiro observado. Não houve conversão de registros históricos.

Mocks cobriram sexta-feira 13/11, domingo 15/11 e domingo 03/01/2027 na revisão semanal. Testes locais anteriores de virada de dia/ano continuaram aprovados.

## 23. Segurança dos prompts

Prompts preservam a mensagem de sistema e serializam entradas como dados. Identificadores, email, nome, senha, tokens e cookies são removidos recursivamente das estruturas transmitidas.

O modelo escolhe somente conteúdo educacional sujeito a validação. A aplicação escolhe o usuário e os vínculos. Respostas não são executadas como SQL, JavaScript, HTML ou comandos.

## 24. Proteção de dados

Foram usadas somente as duas contas fictícias existentes. Os prompts reais vieram da segunda conta e de seu perfil de testes. Dados históricos não foram copiados nem enviados.

O corpo completo das respostas externas não foi registrado em log técnico. Metadados e evidências ficam em diretório privado fora do DocumentRoot. O log local manteve seu prefixo anterior e recebeu somente acréscimos dos ensaios.

## 25. Endpoints habilitados

Foram adicionados ao .htaccess somente GET diagnostico.php?action=status ou carregar_ultimo, POST diagnostico.php?action=gerar, GET redacao.php?action=listar e POST redacao.php?action=salvar ou analisar.

Autenticação, CSRF, método, ação e payload são verificados no backend. Os GETs usam READ ONLY. O POST semanal anterior continua autorizado com coordenação e reutilização corrigidas.

## 26. Endpoints bloqueados

Continuam bloqueados IA genérica, cron, replanejamento, geração anual, execução/aplicação de revisão semanal, ações administrativas e operações não auditadas.

.env, .git, backups, logs, SQL, código privado, testes e documentação continuam negados por HTTP. CSP, origem local e restrições de métodos foram preservadas.

## 27. Privilégios MySQL

Três concessões específicas foram adicionadas somente em estudai_teste_2026.

| Tabela | Privilégio |
| --- | --- |
| conteudos | SELECT |
| ia_historico | SELECT, INSERT |
| redacoes_enem | SELECT, INSERT e UPDATE de tema, texto, status, analise_json, atualizada_em |

SHOW GRANTS passou de 29 para 32 entradas. A auditoria final confirmou correspondência às instruções registradas. Não houve DELETE, DDL, privilégio administrativo, concessão global ou acesso aos históricos para o PHP.

## 28. Testes automatizados

| Suíte | Resultado |
| --- | --- |
| Cliente e respostas de transporte simuladas | 69 verificações |
| Serviços, contratos, datas e fallback | 92 verificações |
| Persistência não confirmada e rollback de três fluxos | 19 verificações |
| Orçamento, concorrência, corrupção e escopo | 11 verificações |
| Frontend de IA e contratos em DOM simulado | 34 cenários |

Os testes automatizados de IA usam transporte injetado em memória. Eles não chamam a OpenRouter. Resultados mockados de diagnóstico e plano remoto foram inspecionados antes de rollback e não ficaram apresentados como resultados reais na aplicação.

## 29. Testes HTTP/API

Preparação com IA desativada aprovou 150 verificações. Conferência preenchida aprovou 108, incluindo reenvio concorrente do plano e leituras dos modos anteriores.

Cobertura inclui anônimo, sessão, CSRF, métodos, ações conflitantes, JSON inválido, IDs, tamanhos, textos insuficientes, posse, leitura sem efeitos e recuperação de resultado local.

Em 13/11, o treino diário está vazio porque o lote anterior é de 11/11. O teste conferiu esse vazio e o histórico anterior por leitura, evitando criar novo lote ou tentativa de revisão em outro dia.

## 30. Chamadas reais à OpenRouter

| Ordem | Recurso | HTTP | Duração aproximada | Resultado |
| --- | --- | --- | --- | --- |
| 1 | GET público de modelos | 200 | 0,129 s | Modelo antigo ausente, alternativas gratuitas verificadas |
| 2 | Planejamento semanal | 200 | 12,8 s | incomplete_response, plano remoto rejeitado, fallback salvo |
| 3 | Diagnóstico | 400 | 0,414 s | Rejeição do provedor, nenhum diagnóstico salvo |

O bloqueio inicial após conteúdo incompleto foi ajustado para permitir o teste independente de diagnóstico ainda dentro do orçamento original. Não houve repetição da geração semanal.

Nenhuma chamada real de redação, revisão, replanejamento ou anual foi feita. O contador de geração permaneceu em dois durante Chrome e regressões.

## 31. Custo efetivamente observado

A geração semanal informou usage.cost igual a zero. A resposta 400 de diagnóstico não informou custo. Os modelos selecionados e o roteamento foram restritos a preço zero.

Custo observado informado foi US$ 0. Não foi executada uma consulta de faturamento adicional. Não houve uso de modelo pago.

## 32. Modelo efetivamente utilizado

A resposta semanal identificou liquid/lfm-3-1.7b:free. A tentativa de diagnóstico solicitou esse mesmo modelo, mas a resposta de erro não trouxe identificação efetiva de uma geração.

Não foi feita comparação de modelos por inferência. O identificador antigo pode ser restaurado como configuração, porém estava ausente do catálogo consultado.

## 33. Testes no Chrome

Chrome real em perfil privado isolado, modo headless. O teste novo aprovou 29 verificações e produziu seis capturas finais inspecionadas visualmente.

Cobertos login fictício, plano local próprio, recuperação pelo botão de geração, calendário, abertura da redação salva, análise local reutilizada, reload, diagnóstico indisponível, viewport 390×844, conta anterior e isolamento. Console e worker sem exceções não tratadas.

As tentativas intermediárias do teste foram preservadas. Uma notificação temporária cobria o botão Abrir da redação. O teste passou a aguardar o alvo ficar acessível antes de enviar o clique real. Outro ajuste levou o teste ao cartão de diagnóstico no dashboard.

Não houve mudança de CSS/HTML para resolver esses casos. As APIs observadas ficaram fora do cache do worker. Aparelho físico, instalação standalone e perfil pessoal permanecem sem certificação.

## 34. Testes de regressão

| Conjunto | Resultado |
| --- | --- |
| Inicialização do dashboard | 23 cenários |
| Planejamento frontend | 12 cenários |
| Questões frontend | 29 cenários |
| Exercícios planejados frontend | 22 cenários |
| Simulados frontend | 49 cenários |
| PWA | 21 cenários |
| Gerador local PHP | 20 verificações |
| HTTP de simulados já finalizados | 102 verificações |
| HTTP de exercícios já preenchidos | 159 verificações |
| HTTP/assets/proteções | 124 verificações, 19 assets |
| Configuração, READ ONLY e históricos negados | 12 verificações |
| Chrome de autenticação/painel/PWA | 58 verificações |
| Chrome de exercícios planejados | 32 verificações |
| Chrome de simulados | 25 verificações |

O teste de inicialização agora espera 15 GETs, incluindo a leitura de diagnóstico autorizada. O teste de planejamento exclui somente essa leitura da lista de chamadas proibidas.

O Chrome anterior recebeu --ai-planning-enabled para esperar um plano próprio da segunda conta em vez de supor que ela continua sem plano. As verificações de isolamento das contas foram mantidas. Nenhuma geração externa ocorreu nas regressões.

Cadastro positivo e salvamento de novo onboarding não foram repetidos. Validações de cadastro, duplicidade, login, sessão, logout, leitura do perfil, dashboard, tarefas, calendário e offline foram exercitadas.

## 35. Arquivos modificados

Dezessete arquivos existentes versionáveis foram alterados nesta etapa.

- .env.example e .htaccess.
- app/src/pages/app.js e app/src/services/ia.js.
- server/config/openrouter.php e server/helpers/helpers.php.
- server/api/diagnostico.php, plano-estudos.php e redacao.php.
- server/services/ai/openrouterClient.php, estudaiService.php e prompts.php.
- server/services/dailyQuestions.php, somente limite de payload opcional preservando default anterior.
- server/services/weeklyReview.php, somente cálculo da janela.
- tests/browser_stage09.mjs, dashboard_initialization.test.js e planning_frontend.test.js.

Dois arquivos locais também mudaram, .env para os controles reversíveis e storage/logs/app.log por acréscimos de execução. Ambos permanecem ignorados e protegidos.

Dez arquivos novos incluem este relatório, server/services/ai/validation.php e oito arquivos de testes, ai_budget_stage13.py, ai_frontend_stage13.test.js, ai_stage13.php, ai_stage13.py, ai_stage13_bootstrap.php, ai_transactions_stage13.php, browser_stage13.mjs e openrouter_stage13.php.

app.html, CSS, service worker, catálogo e demais mudanças anteriores foram preservados. Evidências ficam em C:\Users\player\AppData\Local\EstudAI\Recovery\20261113_092526_stage13_20ed838b.

## 36. Estado dos bancos históricos

| Banco | Antes | Depois |
| --- | --- | --- |
| vestibular_estudos | 42 tabelas, 641 linhas | Idêntico |
| estudai_demo_2026 | 42 tabelas, 641 linhas | Idêntico |
| estudai_teste_2026 | 42 tabelas, 326 linhas | 42 tabelas, 334 linhas |

Oito inclusões confirmadas, uma em cada tabela de plano, controle, versão, item, tarefa, evento, histórico de IA e redação. Todas as linhas preexistentes das 42 tabelas de testes permaneceram idênticas.

A comparação cobriu conteúdo completo, colunas e DDL normalizado. Estruturas e backup original permaneceram iguais. O contador AUTO_INCREMENT pode avançar em rollback. Nenhum schema, migration, seed, DROP ou DELETE foi executado.

## 37. Estado do Apache

A instância compartilhada foi iniciada com a configuração original validada. Os 29 arquivos .conf mantiveram seus conteúdos.

O EstudAI continua protegido por Require local. Os listeners globais originais atendem os outros projetos, com 80/443/8081 nas interfaces gerais e 8082 em loopback. Não houve mudança de VirtualHost, CORS ou exposição adicional.

Permanece o aviso anterior do DocumentRoot ausente de outro projeto na porta 8081. A revisão de sintaxe retornou Syntax OK.

## 38. Estado do Little Rio Manager

Depois de iniciar o Apache, o Little Rio Manager voltou a responder HTTP 200 em 127.0.0.1:8082 e foi mantido disponível. Nenhum arquivo desse projeto foi modificado.

MySQL80 permaneceu ativo com o worker observado no início desta etapa. MariaDB do XAMPP não foi iniciado.

## 39. Estado do Git

HEAD 67a2fe0 preservado, nenhum arquivo staged, commit ou deploy. O diff acumulado das etapas anteriores permanece na árvore de trabalho.

Verificações finais incluem php -l, node --check, AST Python, httpd -t, git diff --check e git status. .env e log continuam fora do rastreamento. A varredura dos arquivos da etapa não encontrou chave ou credenciais reais.

## 40. Problemas corrigidos

Corrigidos tratamento de transporte e conteúdo inválido, limite persistido de chamadas, seleção paga indevida, validação permissiva de JSON, duração inválida normalizada silenciosamente, matéria/conteúdo inventados, sobreposição e vazamento de detalhes técnicos nos avisos.

Corrigidos chamada remota anterior à verificação de plano existente, retry remoto em cascata, histórico fora da transação, origem ambígua da redação, sucesso falso em atualização de ID alheio, duplicação em reenvios e análise antiga preservada após editar texto.

Também foram corrigidos o contrato do helper JS de planejamento, erros JSON tratados como sucesso, carregamento do diagnóstico salvo no painel, uso do fuso da aplicação e janela semanal no domingo.

## 41. Limitações restantes

As duas gerações reais não produziram saída aprovada. O motivo exato de término da primeira e a causa detalhada do HTTP 400 não foram preservados em corpo bruto. O parâmetro opcional removido do diagnóstico não foi retestado externamente.

Replanejamento, aplicação de revisão e persistência anual exigem trabalho próprio antes de liberação. A validação de texto não garante idioma, adequação pedagógica ou ausência de toda tentativa semântica de manipulação.

A garantia de concorrência depende do protocolo de bloqueio por perfil. A base reduzida de questões e as limitações dos simulados descritas na Etapa 12 permanecem.

## 42. Recursos prontos para demonstração

Podem ser demonstrados os recursos aprovados das Etapas 05–12, o plano local preservado da primeira conta, o plano fallback da segunda, calendário, redação fictícia salva e orientação local identificada.

O estado de indisponibilidade do diagnóstico também foi verificado. Nenhum desses estados deve ser apresentado como diagnóstico ou planejamento gerado com sucesso pela OpenRouter.

## 43. Recursos que devem continuar bloqueados

Novas gerações externas ficam desativadas após o limite autorizado. Permanecem bloqueadas aplicação automática de revisão, cron, replanejamento, plano anual, IA genérica e operações administrativas não auditadas.

A recuperação de resultados locais já persistidos continua disponível sem consumir chamadas externas.

## 44. Próxima etapa recomendada

Analisar este relatório e decidir uma continuação restrita da Etapa 13 para diagnosticar a saída incompleta e o HTTP 400, com novo orçamento explícito de chamadas gratuitas. Essa continuação deve comprovar plano semanal e diagnóstico reais antes de anunciar IA operacional.

A Etapa 13 fica parcialmente concluída. Hospedagem, deploy, preparação final da apresentação e outra etapa não foram iniciados. Aguardando análise do usuário.
