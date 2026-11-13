# Etapa 13.1 — Investigação e estabilização local da OpenRouter

Execução: 13/11/2026. Projeto: C:\xampp\htdocs\estudAi_1.1. Apresentação: 17/11/2026.

## 01. Resumo executivo

Etapa 13.1 concluída no escopo de investigação, correções locais, testes e proposta de reteste. A integração remota continua pendente: não foi demonstrada a resolução do HTTP 400 nem a geração semanal remota válida.

Nenhuma requisição à OpenRouter, consulta nova de catálogo, consulta de faturamento ou outra operação externa de IA foi executada. ESTUDAI_AI_DISABLED=true permaneceu em .env e .htaccess, ambos inalterados. O orçamento real e seus dois registros anteriores permaneceram idênticos.

Foram corrigidos dois defeitos locais reproduzidos: parser aceitava cercas Markdown sem fechamento e cliente aceitava uma recusa explícita acompanhada de conteúdo e finish_reason=stop. Também foram reduzidas redundâncias do prompt semanal e restringidos/enriquecidos os metadados seguros para um eventual ensaio autorizado.

As causas exatas das duas falhas reais não são recuperáveis a partir das evidências guardadas. A categoria da primeira permite localizar sua rejeição antes do parser de conteúdo; não prova truncamento. A remoção anterior de reasoning não prova a causa do HTTP 400.

## 02. Estado inicial

HEAD 67a2fe0, árvore modificada pelas etapas anteriores, índice vazio. Os 166 arquivos finais da Etapa 13 coincidiram com os hashes atuais antes das alterações desta etapa. O primeiro inventário desta etapa tem 167 arquivos porque seu próprio utilitário de evidências foi criado antes da captura; essa inclusão está explicitamente identificada.

Banco efetivo estudai_teste_2026, conta estudai_teste_app@localhost, MySQL 8.0.45/3306. Auditoria administrativa READ ONLY confirmou 42 tabelas e 334 registros no teste, 641 em cada histórico e 32 entradas de SHOW GRANTS. Nenhum privilégio foi adicionado.

Apache já estava ativo, PID observado 16176, MySQL worker 6968. Little Rio Manager respondeu 200 em 127.0.0.1:8082. Não foi preciso parar, iniciar ou reiniciar serviços.

## 03. Evidências analisadas

Lidos integralmente o relatório da Etapa 13, os trechos pertinentes dos relatórios 08–12, código efetivo, diff acumulado e testes de cliente, serviços, transações, orçamento, HTTP, frontend e Chrome.

Evidência anterior: C:\Users\player\AppData\Local\EstudAI\Recovery\20261113_092526_stage13_20ed838b. Foram examinados catálogo arquivado, ledger, JSONL de resultados, controles de ativação/encerramento, resultados reais, inventários de arquivos/bancos e concessões anteriores. Não havia corpos brutos das respostas reais ou cópia do payload exato transmitido.

Evidência nova, com ACL restrita e fora do DocumentRoot: C:\Users\player\AppData\Local\EstudAI\Recovery\20261113_stage13_1_b296fec1. Contém cópia do código inicial sem .env/log, hashes, comparações, testes, auditorias READ ONLY e capturas. As fixtures novas são sintéticas e identificadas como tais; não representam respostas reais sanitizadas.

## 04. Causa provável ou confirmada do HTTP 200 incompleto

Fatos: modelo solicitado e retornado liquid/lfm-3-1.7b:free; endpoint https://openrouter.ai/api/v1/chat/completions; HTTP 200; aproximadamente 12.800 ms; usage.cost informado zero; categoria incomplete_response. Tokens efetivos, finish_reason e conteúdo não foram guardados nessa chamada.

O cliente em uso lança incomplete_response quando choices[0].finish_reason é diferente de stop ou está ausente. Portanto, essa categoria indica rejeição do envelope/término antes de openrouterFirstText, estudaiDecodeJsonText e estudaiAiPlanoSemanal. Não demonstra que o JSON de planejamento tinha campos faltantes, Markdown, erro sintático ou conteúdo truncado. Esses atributos não podem ser recuperados.

| Hipótese solicitada | Conclusão baseada nas evidências |
| --- | --- |
| A. Limite de geração atingido | Possível; seria confirmado por length/tokens, ausentes no registro. |
| B. Modelo não seguiu o contrato | Possível, mas o conteúdo não chegou ao validador nesta chamada. |
| C. Prompt excessivo/contexto esgotado | Não comprovado; não foram guardados tamanho/tokens da requisição. |
| D. JSON estruturalmente inválido | Não avaliável sem conteúdo; não é significado dessa categoria. |
| E. JSON válido semanticamente incompleto | Não avaliável; não houve validação semântica dessa resposta. |
| F. Defeito no parser de conteúdo | Excluído como ponto de rejeição observado; parser não foi alcançado. |
| G. Defeito de transporte/envelope | A requisição retornou JSON decodificável sem error superior; fim ausente/inesperado continua possível. |
| H. Outros términos, filtro, recusa ou erro de escolha | Não distinguíveis sem finish_reason/envelope original. |

Parâmetros reconstruídos do código e relatório: duas mensagens system/user, temperature=0.25, max_tokens=2600, stream=false, response_format={type:json_object}, provider.max_price={prompt:0,completion:0}, provider.allow_fallbacks=false. Não são uma captura byte a byte do payload histórico. O limite de saída configurado era inferior ao máximo de 8192 informado no catálogo, mas isso não garante que toda resposta solicitada caiba em 2600.

## 05. Causa provável ou confirmada do HTTP 400

Fatos: mesmo endpoint/modelo solicitado, HTTP 400 em aproximadamente 414 ms; nenhum diagnóstico salvo; actual_model, finish_reason e completion_tokens nulos. O corpo de erro não foi preservado. Não é possível distinguir parâmetro incompatível, restrição específica do provedor, combinação de parâmetros ou outra rejeição de payload.

Headers são produzidos no backend: Authorization Bearer, Content-Type application/json, HTTP-Referer e X-OpenRouter-Title. Nenhum valor secreto foi incluído nesta auditoria. O corpo atual contém model, messages com system/user, temperature=0.25, max_tokens=4500, stream=false, provider de custo zero e response_format json_object. A tentativa anterior incluía ainda o parâmetro descrito na seção 06.

Os mocks demonstram que o payload atual é montado conforme o contrato local e que ambos os tipos simulados de HTTP 400 são rejeitados com segurança. Não demonstram sua aceitação pela OpenRouter. A integração externa de diagnóstico permanece pendente.

## 06. Parâmetro removido anteriormente

O parâmetro foi reasoning: {enabled: false}, retirado após o HTTP 400 na Etapa 13. O objetivo técnico registrado na retomada era reduzir raciocínio/consumo de saída após o plano incompleto; trata-se da intenção da tentativa, não de comprovação da causa da primeira falha.

O catálogo arquivado declara reasoning entre os parâmetros do modelo. Isso não comprova aceitação desse objeto e dessa combinação em toda rota/provedor. Não há corpo de erro que prove incompatibilidade. A remoção reduziu uma variável opcional e permaneceu uma medida conservadora sem reteste externo.

O cliente atual constrói uma lista explícita de campos de payload; reasoning não é enviado por diagnóstico ou planejamento, nem pelos demais consumidores. Os testes inspecionam os campos exatos das duas requisições mockadas. Mantidos max_tokens, temperatura, json_object e roteamento gratuito, com os riscos de combinação/provedor ainda sujeitos a reteste. Nenhum parâmetro novo de reasoning ou JSON Schema foi introduzido.

## 07. Compatibilidade do modelo

Modelo configurado continua liquid/lfm-3-1.7b:free. O antigo liquid/lfm-2.5-1.2b-instruct:free estava ausente na consulta arquivada da Etapa 13. Nenhum modelo foi trocado nesta etapa.

O snapshot de 13/11 informa contexto 262144, saída máxima 8192, preço de entrada/saída zero e suporte declarado a max_tokens, max_completion_tokens, temperature, response_format, structured_outputs e reasoning. O relatório anterior registra português entre os idiomas do fabricante; a qualidade dos contratos em português continua sem sucesso real no projeto. Metadados arquivados não certificam disponibilidade atual, quota ou comportamento de cada provedor.

Alternativas gratuitas presentes nesse mesmo snapshot, sem consulta nova e sem seleção automática:

| Modelo | Contexto / saída máxima arquivados | Formato declarado |
| --- | --- | --- |
| liquid/lfm-2.5-2.6b:free | 65536 / 8192 | response_format e structured_outputs |
| google/gemma-4-26b-a4b-it:free | 262144 / 32768 | response_format |
| particle-ai/particle-1:free | 1048576 / 131072 | response_format e structured_outputs |

Não há comparação por inferência ou garantia de qualidade dessas alternativas. A recomendação inicial é isolar o problema com o modelo atual, em vez de trocar simultaneamente modelo, contrato e parâmetros. Um ensaio futuro deve interromper se o modelo não estiver disponível gratuitamente.

## 08. Correções do planejamento

Alterado somente o construtor do prompt semanal em prompts.php. Removidos campos duplicados do perfil/janela, regras de retry legado e exemplo com matéria/conteúdo fixos que podiam divergir do catálogo real. Preservados objetivo, prova, dificuldades, prioridades, preferências, reforços, diagnóstico quando existente, datas obrigatórias/blocos e catálogo permitido.

O contrato conserva título, resumo, estratégia, semana, tarefas completas, observações e alertas. dia_semana é derivado da data pelo normalizador existente e não precisa ser produzido pelo modelo. Textos curtos são solicitados; duração continua inteira, válida e consistente com horários. Preferência por uma tarefa por bloco reduz volume sem dispensar cobertura. Não se reduziu a validação ou inventou campo obrigatório ausente.

Na mesma fixture fictícia, mensagens serializadas caíram de 4702 para 3079 bytes, redução de 34,5%. Isso mede o exemplo local; não estima com precisão o payload ou tokens da chamada real antiga. max_tokens permaneceu 2600.

## 09. Correções do diagnóstico

Prompt agora explicita matérias prioritárias somente de materias_base, os sete campos obrigatórios, listas de textos e até oito próximos passos. Isso alinha instruções ao validador existente, sem mudar resposta pública, banco ou interface.

Mantidos max_tokens=4500, temperature=0.25 e ausência de reasoning. Diagnóstico inválido não é salvo; falha retorna indisponibilidade. O GET continua de leitura e não gera diagnóstico. Nenhum resultado mockado foi deixado no histórico.

## 10. Validação dos prompts

Mocks capturam payload completo em memória com chave sentinela e modelo tests/model:free. Verificam destino, campos exatos, limites, json_object, ausência de reasoning e manutenção de catálogo/data/horários. Prompts são dados educacionais com instrução de sistema para tratar entrada como não confiável.

A minimização de identificadores anterior foi preservada. Não foram enviados dados históricos. Não se afirma que o texto do prompt elimina toda manipulação semântica, nem que o modelo obedecerá apenas porque a fixture obedece.

## 11. Validação dos parsers

Defeito reproduzido antes da correção: abertura de cerca Markdown era removida mesmo sem fechamento. O parser agora aceita somente objeto JSON puro ou cerca completa, com abertura/fechamento em linhas próprias, sem linguagem diferente de json. Texto fora do objeto, JSON truncado, listas e conteúdo excessivo continuam rejeitados.

Também foi reproduzida, na cópia anterior do cliente, aceitação de mensagem com recusa explícita e finish_reason=stop. Agora a recusa ou erro da escolha invalida a resposta, inclusive valor de recusa malformado. Isso não é evidência de recusa nas chamadas antigas.

Nenhum parser preenche conteúdo ausente. Campos extras desconhecidos não são executados; normalização mantém somente o contrato autorizado. HTML executável continua rejeitado/escapado nas fronteiras existentes.

## 12. Tratamento de respostas incompletas

O critério conservador finish_reason=stop foi mantido. length é rejeitado mesmo quando o conteúdo por acaso contém JSON completo. Término ausente, filtro e demais términos inesperados continuam sem aprovação artificial. Resposta textual ou JSON inválido com stop é rejeitada na camada seguinte.

Metadados futuros passaram a incluir prompt_tokens, completion_tokens, reasoning_tokens quando inteiros válidos, tamanho de conteúdo, requisição/mensagens, max_tokens, recusa e presença de erro. finish_reason é restrito a valores conhecidos ou other; modelo tem formato validado. Um teste demonstra que texto arbitrário nesses campos não é registrado como motivo/modelo.

Não são registrados corpo, prompt, mensagem de recusa ou texto livre de erro. error_parameter só admite nomes conhecidos quando explicitamente fornecidos pelo upstream. A ausência desses campos continuará sendo documentada, não inferida. Não há novos resultados reais desses metadados nesta etapa.

## 13. Tratamento de erros HTTP

Fixtures sintéticas cobrem HTTP 200 completo, truncado, sem campo obrigatório, textual, Markdown, limite de saída, recusa, campos extras, vazios e conteúdo inesperado. HTTP 400 por parâmetro e por payload são casos simulados distintos, com o mesmo bloqueio público seguro. Também cobertos 401, 403, 404/modelo indisponível, 429, 500, 503, timeout e conexão falha.

Os erros não expõem corpo do provedor ou chave. HTTP 429 mantém exceção própria. Sem retry automático. Verificações anteriores de TLS, destino HTTPS fixo, redirects negados, entrada até 60000 bytes, saída até 131072 bytes e timeout configurado de 30 segundos foram preservadas. Transporte real não foi exercitado nesta etapa.

## 14. Fallback

Cada falha simulada produz no máximo uma tentativa e mantém o algoritmo local. IA desativada ou chave ausente impede o transporte. Origem fallback permanece explícita no retorno, plano e orientação da redação.

Planos salvos anteriormente foram recuperados sem geração adicional. Chrome mostrou a orientação local da redação e diagnóstico indisponível, sem apresentá-los como IA real. Mensagens públicas permanecem coerentes e sem códigos técnicos brutos.

## 15. Persistência

Mantida transação única para plano, controle, versão, item, tarefa, evento e histórico. Teste anterior reexecutado: 19 verificações, sucesso mockado de plano/diagnóstico/redação inspecionado antes de falha controlada de commit e rollback completo.

Novo ensaio: 23 verificações com campo ausente, data inválida, matéria inexistente, duração inválida e JSON truncado. Cada plano remoto inválido foi substituído por fallback válido dentro da transação, conferido e revertido. Diagnóstico inválido encerrou a transação sem gravação. Fingerprints anteriores permaneceram iguais; nenhum mock foi confirmado.

Semana alternativa existe somente em memória para alcançar o escritor sem substituir o plano persistido; perfil não recebeu UPDATE. AUTO_INCREMENT pode avançar nos rollbacks. Isso não representa registro confirmado nem mudança de schema.

## 16. Integração com tarefas

Ensaios conferiram uma única tarefa de fallback com item e proprietário corretos. HTTP concorrente e nova sessão recuperaram os planos existentes sem duplicar atividades. Plano A continua com quatro tarefas e 50%; plano B continua com uma tarefa local de Redação em 16/11, 18h–19h.

Nenhuma conclusão, edição, adiamento ou cancelamento genérico foi habilitado. As respostas e estados das práticas/simulados anteriores permaneceram idênticos.

## 17. Integração com calendário

Evento e tarefa do fallback temporário foram conferidos juntos antes de rollback. Consultas/repetições dos planos persistidos mantiveram um evento da segunda conta e quatro da primeira. O Chrome conferiu os dois calendários e telas estreitas.

GETs continuam READ ONLY e sem sincronização de escrita. Nenhum novo evento foi confirmado. A origem e as datas persistidas anteriores não foram alteradas para ajustar a apresentação.

## 18. Testes com mocks

| Suíte executada nesta etapa | Resultado |
| --- | --- |
| ai_stage13_1.php, complementos de parser/contrato/erros/metadados | 61 verificações |
| openrouter_stage13.php, cliente | 69 verificações |
| ai_stage13.php, serviços/validadores/fallback | 92 verificações |
| ai_transactions_stage13.php, rollback de sucesso mockado | 19 verificações |
| ai_transactions_stage13_1.php, inválidos/fallback/rollback | 23 verificações |
| ai_budget_stage13.py, escopo/corrupção/concorrência do ledger | 11 verificações |
| ai_frontend_stage13.test.js | 34 cenários |
| Gerador local PHP | 20 verificações |

Matriz complementar: diagnóstico válido/inválido; plano válido/incompleto; datas/matérias/duração; falha seguida de fallback; vínculos; nenhuma gravação parcial; chave ausente/desativação; resposta com campos extras; erros externos simulados. A concorrência de reservas usa ledger de teste separado. A concorrência/idempotência de planos existentes usa duas sessões locais reais, sem geração nova.

Inicialmente o teste novo reproduziu a cerca incompleta aceita. O primeiro executor de regressão passou a fixture de dashboard ao teste de planejamento; foi corrigido somente o argumento, preservando o relatório da falha. Grupo unitário final integralmente aprovado. Não se modificaram expectativas para aceitar uma falha do produto.

## 19. Testes HTTP/API locais

| Suíte | Resultado |
| --- | --- |
| Verificação IA/fallback/resultados existentes | 108 verificações |
| Novos negativos de sessão/CSRF/payload/ações/IA desativada | 133 verificações |
| Exercícios planejados preenchidos | 159 verificações |
| Simulados finalizados | 102 verificações |
| Assets, CSP e proteções HTTP | 124 verificações / 19 assets |
| Configuração, READ ONLY, históricos negados, guardião de IA | 12 verificações |

Autenticação, logout, sessão expirada, posse, CSRF ausente/inválido, JSON inválido, corpo excessivo e ações conflitantes foram conferidos sem novas inclusões. Escritas fora do escopo, cron, IA genérica, replanejamento, anual e aplicação da revisão seguem bloqueados. Diagnóstico com IA desativada retornou 503 sem diagnóstico salvo.

Reenvios concorrentes recuperaram plano próprio. Estatísticas diárias continuam 2 questões, 1 acerto, 1 erro e 50% por conta; lote do dia 13 está vazio, preservando histórico de 11/11. Não foi repetido o teste antigo que prepararia lote/revisão para outro dia. Cadastro positivo e salvamento positivo de onboarding não foram repetidos para evitar novos dados; validações e leitura do perfil foram exercitadas.

## 20. Testes de Chrome

Chrome real 157, headless, perfil isolado, sem usar o perfil pessoal. Suíte IA aprovada em 29 verificações/6 capturas; regressão de login/painel/onboarding/PWA aprovada em 58 verificações/10 capturas. Os dois processos foram encerrados pelo teste. Zero exceções não tratadas e zero erros de worker registrados.

As 16 capturas foram abertas e inspecionadas. Cobrem login, onboarding de disponibilidade, dashboard desktop/390×844, planos existentes, calendário, rotina, orientação local de redação, diagnóstico indisponível, isolamento e offline. Sem impedimento visual observado nesses fluxos. Notificações temporárias continuam sobrepondo parte da tela durante sua exibição, como antes; o teste aguarda o alvo ficar acessível antes do clique.

Resultados do Chrome usam exclusivamente dados persistidos, fallback/desativação. Nenhum plano ou diagnóstico remoto novo foi gerado. Os controles antigos de ações bloqueadas continuam visíveis; sua presença não representa liberação. Não se certificam aparelho físico, PWA standalone, outros navegadores ou acessibilidade completa. Registros brutos com visual_inspection_pending foram preservados; inspeção posterior consta em visual_review_final.json.

## 21. Revisão do diff em app.js

| Comparação com HEAD 67a2fe0 | Adições / remoções |
| --- | --- |
| git diff --numstat | 203 / 148 |
| git diff --ignore-space-at-eol --numstat | 202 / 147 |
| git diff --ignore-all-space --numstat | 200 / 145 |

HEAD: 3339 linhas, 141972 bytes, UTF-8 sem BOM, LF. Arquivo local: 3394 linhas, 153495 bytes, UTF-8 sem BOM, CRLF em todas as linhas. core.autocrlf=true e Git informa i/lf w/crlf. O Git normaliza os terminadores; não há diff atual de 3394/3378 linhas.

O número informado de todas as linhas é compatível com comparação bruta de versões com finais diferentes. Entretanto, o inventário anterior à Etapa 13 guarda somente hash/tamanho, 148447 bytes, sem cópia fonte; não se pode demonstrar retrospectivamente o EOL exato dessa versão nem reproduzir o algoritmo que originou aquela estatística. Não se atribui toda a diferença a EOL ou toda a lógica à Etapa 13.

Mudanças semânticas acumuladas identificadas: datas locais e planejamento/calendário da Etapa 08; questões/revisão/progresso da 10; práticas da 11; estado/botões/retomada/correção dos simulados da 12; leitura/contrato do diagnóstico e guardas/mensagens de redação da 13. Parte do tratamento de geração semanal já era da 08. O histórico documental e os hashes delimitam arquivos por etapa, mas não permitem uma contagem exata de linhas exclusivas da 13.

Nesta Etapa 13.1 app.js ficou byte a byte idêntico à versão inicial. Nenhuma reescrita, normalização global ou reversão foi aplicada. As regressões passaram sobre o arquivo preservado.

## 22. Análise dos demais arquivos modificados

Inventários da Etapa 13 identificam 19 arquivos existentes alterados, incluindo .env e log locais, e 10 novos. São 17 existentes versionáveis mais 10 novos; a estimativa de aproximadamente 23 arquivos não deve ser tratada como inventário exato. O diff geral do Git também inclui as etapas 08–12 e não conta automaticamente arquivos novos ainda não rastreados.

| Arquivos da Etapa 13 revisados | Finalidade e avaliação |
| --- | --- |
| .env.example, server/config/openrouter.php | Default gratuito, desativação e controles de recuperação; sem alteração nesta etapa. |
| .htaccess | Lista explícita de ações, Require local, CSP e IA desativada; preservado. |
| app/src/pages/app.js, app/src/services/ia.js | Contratos, guardas e mensagens; preservar mudanças acumuladas e serviço semanal correto. |
| server/api/diagnostico.php | Leitura sem geração, cache por perfil, transação e posse; preservado. |
| server/api/plano-estudos.php | Reutilização antes da IA e persistência conjunta; código anual/replanejamento legado continua bloqueado. |
| server/api/redacao.php | Validação, posse, reutilização e origem; há ramo antigo redundante de tabela ausente, sem defeito funcional demonstrado, não removido. |
| server/helpers/helpers.php | Duas linhas para timezone da aplicação; regressões de datas aprovadas. |
| server/services/dailyQuestions.php | Limite de corpo opcional, default 8000 preservado; necessário à redação. |
| server/services/weeklyReview.php | Janela semanal corrigida; aplicação/cron continuam bloqueados. |
| server/services/ai/openrouterClient.php | Transporte, orçamento e validação; duas correções e metadados seguros nesta etapa. |
| server/services/ai/estudaiService.php | Parser e serviços; somente parser alterado nesta etapa. |
| server/services/ai/prompts.php | Somente instruções de diagnóstico e construtor semanal alterados nesta etapa. |
| server/services/ai/validation.php | Contratos estritos; preservado, inclusive recursos bloqueados. |
| tests/browser_stage09.mjs, dashboard_initialization.test.js, planning_frontend.test.js | Adaptação explícita ao diagnóstico GET/plano da conta B, sem autorizar geração remota automática. |
| tests/openrouter_stage13.php, ai_stage13.php, ai_stage13_bootstrap.php, ai_transactions_stage13.php | Fixtures, transporte injetado, validação e rollback; sem falha de produto escondida pelo teste. |
| tests/ai_budget_stage13.py, ai_stage13.py, ai_frontend_stage13.test.js, browser_stage13.mjs | Orçamento independente, HTTP controlado e UI; executar somente verify nos dados preenchidos. |
| docs/etapa13-integracao-ia-validacao.md | Mantido como registro histórico; limitações esclarecidas neste relatório. |
| .env e storage/logs/app.log | .env inalterado; log somente acrescido, privado e ignorado pelo Git. |

Arquivos novos desta etapa: tests/ai_stage13_1.php, tests/ai_transactions_stage13_1.php, tests/ai_http_stage13_1.py, tests/run_stage13_1.py, tests/evidence_stage13_1.py e este relatório. Três arquivos de produção receberam correções pontuais. Nenhum HTML, CSS, service worker, API dispatcher ou schema foi alterado nesta etapa. Não foram removidos trechos legados apenas para reduzir o diff.

## 23. Segurança da chave

Chave permanece somente no backend privado. .env não rastreado, bloqueado por HTTP, bytes preservados. Valores reais da chave e senha da conta PHP foram comparados em memória contra arquivos versionáveis e log, sem exposição ou achado. Auditoria também examinou 245 objetos alcançáveis do Git, 176 blobs, sem essas credenciais ou chave no formato real pesquisado. Isso não pretende certificar segredos desconhecidos ou dados externos ao repositório.

Autenticação/CSRF/isolamento e validação permanecem. Transporte injetado existe somente como argumento interno dos testes; payload HTTP não escolhe transportador. Processos de mocks usam flag falsa apenas em memória, modelo sentinela e callbacks locais; configuração global nunca foi ativada. Corpos externos não são executados como SQL/HTML/comandos.

Proteções .env/.git/backups/logs/SQL/código privado/testes/documentos responderam 403. CSP preservada: connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none', com nosniff. Não é uma certificação de CSP completa contra todos os scripts inline.

## 24. Estado dos bancos

| Banco | Antes / depois | Resultado |
| --- | --- | --- |
| vestibular_estudos | 42 tabelas / 641 registros | Conteúdo e estrutura idênticos |
| estudai_demo_2026 | 42 tabelas / 641 registros | Conteúdo e estrutura idênticos |
| estudai_teste_2026 | 42 tabelas / 334 registros | Todas as linhas anteriores idênticas |

Comparação integral de DDL normalizado, colunas, hashes de tabelas e linhas, inclusive com o final da Etapa 13. Nenhum schema, migration, seed, DELETE, DROP ou GRANT. Apenas escritas transitórias com rollback no teste; auto_increment é excluído da comparação de DDL por poder avançar nesses ensaios.

Backup original de 204337 bytes teve SHA-256 conferido e preservado. Conta PHP continua com 32 entradas de SHOW GRANTS, sem privilégios globais administrativos, DELETE/DDL ou acesso aos históricos. SELECT nos dois históricos pela conta PHP continua negado; auditoria administrativa foi exclusivamente READ ONLY.

## 25. Estado do Apache

Apache preservado, Syntax OK; 29 arquivos .conf comparados por hash e inalterados. .htaccess do projeto também inalterado. Sem mudança de VirtualHost ou restrições globais de outros projetos.

Listeners observados: 80/443/8081 em 0.0.0.0 e ::; 8082 somente 127.0.0.1. A proteção específica do EstudAI permanece Require local. Não se afirma que todo o Apache está restrito a loopback. Nenhum teste remoto de outro equipamento foi feito.

Permanece o aviso conhecido de DocumentRoot ausente em C:/xampp/htdocs/Setor9/implacavel-concursos-main/public, fora do escopo. Não houve parada/reinício do Apache ou MySQL nesta etapa.

## 26. Estado do Little Rio Manager

HTTP 200 em 127.0.0.1:8082 no início e na conferência final. Nenhum arquivo desse projeto foi alterado. MySQL80 permaneceu ativo, sem iniciar MariaDB do XAMPP ou alterar configuração de serviços.

## 27. Estado do Git

HEAD 67a2fe0 preservado, índice vazio, sem commit/deploy. Alterações válidas e arquivos novos das etapas anteriores mantidos. app.js preservado exatamente. Não foram usados reset, checkout de descarte ou limpeza.

Sintaxe aprovada: php -l em 46 arquivos de server/testes; node --check em 19 arquivos JS/MJS; AST em 13 testes Python. httpd -t e git diff --check aprovados. Avisos LF/CRLF não motivaram reformatação. Git status final foi registrado na evidência privada.

Reexecução local, sem preparar fixtures:

    python -B tests/run_stage13_1.py unit --private-dir <evidencia13_1>
    python -B tests/run_stage13_1.py transactions --private-dir <evidencia13_1>
    python -B tests/run_stage13_1.py http --private-dir <evidencia13_1>
    python -B tests/run_stage13_1.py browser --private-dir <evidencia13_1>

Requer acesso às fixtures privadas e .env protegido. Não substitua indiscriminadamente variáveis de banco quando esse acesso faltar. Os guardas conferem banco/conta antes de conectar e a identidade real antes de testar.

## 28. Problemas ainda existentes

Integração remota não aprovada: faltam diagnóstico real válido e saída semanal real aceita. Causa detalhada do HTTP 400 e término da primeira geração não foram guardados. Nenhum teste local pode recuperar esses dados ou provar a correção remota.

Qualidade pedagógica, idioma e obediência do modelo não são certificados por tipos JSON. Até 30 tarefas extensas podem não caber em 2600 tokens; o prompt menor reduz volume, mas não garante término. Disponibilidade/quota dos modelos gratuitos é externa. Não se aumentou limite ou trocou modelo sem evidência de necessidade.

Replanejamento, aplicação de revisão, cron, anual e IA genérica continuam bloqueados. Telas de apresentação em perfil/aparelho real ainda exigem ensaio próprio. Nenhuma funcionalidade nova foi iniciada.

## 29. Riscos de regressão

Parser mais estrito passa a rejeitar cercas incompletas/idioma incorreto que antes eram removidas permissivamente; é uma rejeição intencional, com fallback. Recusa explícita agora invalida conteúdo mesmo com stop. Os contratos de diagnóstico, redação, revisão e anual foram reexecutados com mocks, pois compartilham parser/cliente.

Prompt compacto pode afetar qualidade de distribuição pedagógica; preservou todos os dados necessários à validação de data/blocos/catálogo e não alterou o validador. Isso precisa de avaliação no reteste, sem tratar mock como garantia. Novos metadados têm lista/tipos restritos e não preservam texto livre, com testes de segredo sentinela.

Garantia de concorrência segue dependendo do bloqueio de perfil; não foi criada UNIQUE de plano/evento. Transação retém o bloqueio durante geração remota até o timeout, comportamento anterior mantido. Nenhuma refatoração geral foi feita para esses riscos.

## 30. Preparação para reteste externo

Proposta somente, não executada e dependente de nova autorização explícita após este relatório.

| Item | Proposta |
| --- | --- |
| Modelo | Manter liquid/lfm-3-1.7b:free; não mudar para pago nem selecionar substituto automaticamente. |
| Destino | POST https://openrouter.ai/api/v1/chat/completions, HTTPS verificado. |
| Limite | No máximo 2 chamadas, sequenciais: 1 diagnóstico e 1 plano semanal. Sem catálogo/faturamento extra. Interromper na primeira falha, mesmo se restar orçamento. |
| Contexto | Somente perfil fictício existente, validado em estudai_teste_2026 com estudai_teste_app e proprietário permitido. |
| Execução inicial | Invocar os serviços/validadores existentes em processo CLI isolado, sem persistir resultados. Override de IA apenas nesse processo autorizado; .env e .htaccess globais permanecem true. |
| Orçamento | Novo ledger privado separado, cap=2 e recursos/dono exatos; não apagar/resetar/alterar ledger da Etapa 13. |
| Diagnóstico | Sete campos, listas/textos válidos e matérias do perfil; recusa/erro/término inesperado não contam como sucesso. |
| Plano | Mesmas datas/blocos/catálogo de entrada; tarefas completas, duração, cobertura e ausência de sobreposição validadas no servidor. |
| Evidências | HTTP, duração, modelo, finish_reason, tokens, bytes e campos seguros de erro; sem chave, prompt ou corpo bruto em logs/Git. Registrar ausência de evidência quando o upstream não fornecer. |
| Fallback | Reproduzir a falha em transporte mockado e confirmar o gerador local; não gastar uma terceira chamada para testar fallback. |
| Duplicação | Geração remota inicial sem INSERT, sem nova semana/perfil ou arquivamento; plano persistido da semana é preservado. POST normal reutiliza plano e não serve para demonstrar geração externa nova. |
| Encerramento | Zero retries, encerramento do processo em finally, ledger preservado e checagem explícita de IA global desativada e hashes/dados inalterados. |

Antes: verificar acesso à chave sem imprimi-la, configuração exata, gratuidade exigida pelo cliente/roteamento, ledger novo e ACL, autorização de duas chamadas, serviços e baseline de dados. A existência de modelo no catálogo arquivado não garante rota atual; indisponibilidade implica parar e relatar.

Se for necessário comprovar persistência de origem ia pelo endpoint depois de obter conteúdo real válido, isso deve ser uma decisão separada: os planos atuais já existem e são reutilizados corretamente. Não excluir/substituir plano ou falsificar perfil para forçar nova chamada. Sucesso de contrato sem gravação não será descrito como teste remoto de persistência ponta a ponta.

## 31. Próxima ação recomendada

Analisar este relatório e autorizar, se desejado, o reteste externo restrito da seção 30. A Etapa 13.1 local está concluída; a Etapa 13 remota permanece parcialmente concluída.

Estado entregue: aplicação funcional com IA desativada e fallback preservado, duas causas reais investigadas com incertezas explícitas, correções locais testadas, diff auditado e bancos/backup/Apache/Little Rio Manager preservados. Aguardando análise e nova autorização; nenhuma chamada externa será executada automaticamente.
