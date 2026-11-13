# Etapa 08 — planejamento local, tarefas e calendário

Data: 11/11/2026. Apresentação: 17/11/2026.

## 01. Resumo executivo

**Etapa tecnicamente concluída no escopo autorizado. Validação visual pendente.**

Um único plano semanal local foi criado e recuperado para uma conta fictícia existente. Plano, versão, controle, item, tarefa e evento foram persistidos em transação. Repetições sequenciais e concorrentes recuperaram o mesmo plano sem mudar os registros. Os GETs do calendário passaram a ser somente de leitura.

Aprovados: **185 verificações HTTP/API da Etapa 08; 337 de regressão HTTP/API; 23 cenários JavaScript anteriores; 12 cenários JavaScript de planejamento/calendário; 20 verificações PHP do fallback; 11 do ensaio de rollback; 12 de configuração/proteções.**

Nenhuma chamada externa de IA, schema, migration, seed, DELETE, DROP, commit ou deploy. Nenhuma mudança de HTML, CSS, identidade visual ou dependências.

## 02. Estado inicial

Git limpo no HEAD 67a2fe0, contendo as mudanças anteriores. Foram lidos os relatórios das Etapas 06/07 e os metadados do instalador, sem executá-lo.

Configuração real: estudai_teste_2026, estudai_teste_app@localhost, MySQL 8.0.45/3306, ESTUDAI_AI_DISABLED=true. Havia 18 instruções SHOW GRANTS e nenhum plano, versão, controle, item, tarefa ou evento.

A estrutura real coincidiu com o DDL da Etapa 06 após normalizar terminadores de linha e excluir somente a opção dinâmica AUTO_INCREMENT. Os bancos históricos mantinham os fingerprints da Etapa 07.

Nova pasta de evidências com ACL restrita:
C:\Users\player\AppData\Local\EstudAI\Recovery\20261111_090948_2bb36209.

## 03. Fluxo de planejamento identificado

Onboarding salva perfil/disponibilidade/matérias/reforços em estudo_perfis e snapshots em onboarding_respostas. planoPerfil recupera os dados do usuário da sessão.

normalizarJanelaPlanoSemanal encontra dias disponíveis em uma semana civil, respeitando a prova. planoGerarBasicoSemanal reutiliza o algoritmo existente de matérias ponderadas e blocos de horários. validarPlanoSemanalGerado confere datas, matérias, duração, disponibilidade e cobertura dos dias.

planoSalvarSemanal bloqueia a linha do perfil com SELECT FOR UPDATE e verifica plano ativo na semana civil. Se existir, recupera-o; senão, mantém a substituição original dos planos/controles ativos e grava as seis entidades em uma transação.

| Função frontend | Endpoint/ação | Método | SQL/tabelas | Efeito |
| --- | --- | --- | --- | --- |
| loadOnboarding | onboarding:status | GET | SELECT estudo_perfis | Perfil próprio |
| gerarPlanoEstudos | plano-estudos:gerar_semana | POST + CSRF | SELECT perfil/plano; INSERT seis tabelas; UPDATE(status) planos/controle | Criar localmente ou recuperar |
| loadPlanoAtivo | plano-estudos:carregar_ativo | GET | SELECT planos_estudo, estudo_perfis, tarefas_estudo | Plano/tarefas com item_id |
| loadRotina | tarefas-estudo:hoje/semana/atrasadas/recentes | GET | SELECT tarefas_estudo, planos_estudo, sessoes_estudo | Rotina própria |
| loadCalendarMonth | calendario-estudai:mes | GET | SELECT eventos_calendario_estudai, planos_estudo | Eventos persistidos |
| loadCalendarYear | calendario-estudai:ano | GET | Mesmas tabelas | Visão anual |
| Leitura API adicional testada | calendario-estudai:dia/eventos | GET | Mesmas tabelas | Dia ou intervalo |
| renderPlano/renderDashboardPlan/renderCalendar | Sem chamada adicional | Local | Nenhum SQL | HTML existente |

O histórico local está em plano_versoes, versão 1. No caminho local não se consulta diagnóstico nem se grava ia_historico. rotina_semanal, historico_estudos e metas_semanais foram inspecionadas; não são necessárias à persistência desse fluxo e não receberam novas permissões.

## 04. Arquivos envolvidos

Revisados: plano, tarefas, calendário, onboarding, helpers, configurações de banco/IA, estudaiService.php, openrouterClient.php, app.js, ia.js, serviço HTTP, service worker, .htaccess e testes.

O frontend já chamava gerar_semana. Não foi criada arquitetura alternativa. ia.js e serviços remotos foram preservados. A flag de recuperação continua impedindo escritas automáticas no boot; a criação semanal é POST explícito.

## 05. Estrutura do banco utilizada

Confirmadas 42 tabelas, 406 colunas, 139 índices, 65 FKs e nenhum trigger/evento agendado. Colunas, tipos, defaults, ENUMs e chaves reais foram conferidos por SHOW CREATE TABLE/information_schema.

| Tabela | Regras e vínculos |
| --- | --- |
| estudo_perfis | UNIQUE usuario_id; linha usada para serialização |
| planos_estudo | FKs usuário/perfil; origem admite fallback; tipo/escopo semanal |
| planejamento_semanal_controle | FKs usuário/plano; índice usuário/semana/status; origem manual permitida |
| plano_versoes | FKs usuário/plano; UNIQUE plano_id/usuario_id/versao_numero |
| plano_estudo_itens | FKs usuário/plano; data, horas, conteúdo e tipo |
| tarefas_estudo | FKs usuário/plano/item; origem manual, metadata origem_geracao=fallback |
| eventos_calendario_estudai | FKs usuário/plano/tarefa; data, horas e conteúdo correspondentes |

Não há UNIQUE de plano por usuário/semana nem de evento por tarefa. A repetição é controlada pelo bloqueio do perfil único e consulta dentro da transação do único escritor semanal liberado. Não se alterou o schema.

## 06. Diagnóstico do fallback local

O algoritmo existente já ponderava matérias por reforço e usava os horários salvos. Foi preservado.

Defeitos comprovados: entrava no gerador remoto mesmo com IA desativada; mensagem descrevia queda de IA sem chamada; validação aceitava datas inexistentes; PHP usava Europe/Berlin; seleção do dia no frontend usava UTC.

O perfil fictício A só tinha segunda-feira 18h–19h e prova em 17/11. A janela real selecionou 16/11; o onboarding não foi alterado para fabricar uma semana maior.

## 07. Correções no fallback

Com ESTUDAI_AI_DISABLED=true, planoGerarSemanalValidado retorna o fallback antes de chamar estudaiGerarPlanoSemanal. Provider/modelo: local/fallback-local; plano persistido: origem fallback.

Mensagens identificam planejamento local. Datas são verificadas no calendário real. O endpoint fixa America/Sao_Paulo.

A verificação estrutural semanal passou a exigir somente tabelas usadas, dispensando leitura de exercícios/simulados. Não foram concedidas permissões para contornar essa dependência antiga.

Os 20 testes PHP cobrem disponibilidade, matérias, duração, slots únicos, horários, virada de ano e zero chamadas ao gerador remoto substituído por contador no teste.

## 08. Permissões MySQL adicionadas

Privilégios anteriores preservados; adições somente em estudai_teste_2026:

| Tabela | Adição | Motivo |
| --- | --- | --- |
| planos_estudo | INSERT; UPDATE apenas de status | Criar/substituir plano |
| tarefas_estudo | INSERT | Criar tarefas |
| planejamento_semanal_controle | SELECT, INSERT; UPDATE apenas de status | Controle semanal |
| plano_versoes | SELECT, INSERT | Histórico local/verificação |
| plano_estudo_itens | SELECT, INSERT | Itens/vínculos |
| eventos_calendario_estudai | SELECT, INSERT | Persistência e calendário |

Final: 22 SHOW GRANTS, 31 privilégios por tabela e dois por coluna. Nenhum DELETE, privilégio global/schema, papel, GRANT OPTION, CREATE, ALTER ou DROP. Root foi usado apenas para concessões administrativas autorizadas e auditoria; não é a conta PHP.

## 09. Endpoints liberados

Além da autenticação/onboarding e leituras anteriores: POST plano-estudos.php?action=gerar_semana, autenticado e com CSRF; GET calendario-estudai.php?action=mes|ano|dia|eventos.

carregar_ativo e leituras de tarefas permanecem. O plano já inclui tarefas com item_id, sem novo endpoint de itens.

.htaccess filtra método/ação; PHP valida a ação decodificada. Duplicação de parâmetro action ou action como array não amplia a lista. A instância privada também restringe arquivos/métodos.

## 10. Endpoints bloqueados

Cron, diagnóstico, IA remota, revisão automática, plano anual, replanejamento/regeneração/arquivamento, simulados, exercícios planejados, redação com IA, alterações de tarefas, status de calendário e demais ações fora da lista continuam negados.

A tarefa de Redação é um tópico de estudo do perfil. Não foi executado/liberado o endpoint de geração ou correção de redação.

## 11. GETs do calendário corrigidos

calendarioFetch não chama mais calendarioSyncTarefas. A função original de sincronização permanece para etapa futura, sem rota liberada que a execute.

Os quatro GETs usam beginDashboardReadOnlyGet e READ ONLY. O POST semanal já cria eventos junto com tarefas; não foi necessária sincronização POST adicional.

Mês 13, data inexistente e intervalo invertido foram recusados. Consultar mês/ano/dia/intervalo não alterou fingerprints das tabelas visíveis à conta da aplicação.

## 12. Teste de geração de plano

Um plano, ID 2, foi salvo para A. Uma atividade de Redação: **16/11/2026, 18h–19h, 60 minutos, pendente**, dentro da disponibilidade e matérias do perfil.

O ID 1 foi consumido pelo ensaio de rollback sem deixar registro; nenhum contador foi reiniciado.

O POST de criação enviou IDs de B no corpo. Foram ignorados para propriedade; o backend utilizou a sessão e o perfil de A.

## 13. Persistência e recuperação

Confirmados um plano, uma versão, um controle, um item, uma tarefa e um evento. Nova requisição recuperou o mesmo plano e tarefas.

O JSON original do plano não inclui usuario_id/perfil_id no nível superior. O teste passou a conferir propriedade nas tarefas e no SQL, sem ampliar a API. TIME foi comparado como horário, considerando os segundos do formato SQL.

A primeira suíte parou nessa suposição de contrato após persistir. Foi retomada com --resume e o registro privado do plano existente, sem nova geração.

## 14. Testes de duplicação

POST repetido retornou o mesmo ID e reutilizado=true, sem mudanças de registros. Dois POSTs concorrentes com sessões distintas também recuperaram o mesmo plano.

Um processo de teste segurou o bloqueio do perfil, e ambos os escritores aguardaram. Após liberação, contagens/fingerprints continuaram iguais. Não se dependeu da serialização da sessão PHP.

Limite aprovado: plano ativo da mesma semana civil pelo fluxo liberado. Arquivamento/replanejamento continuam bloqueados.

## 15. Testes de transação

Antes da geração definitiva, houve falha controlada imediatamente antes do INSERT do evento, após plano/controle/versão/item/tarefa. planoSalvarSemanal fez rollback, encerrou a transação e não deixou linhas parciais. Passaram 11 verificações.

Usa PDO real de testes e subclasse que provoca falha; não existe parâmetro de falha na API. O modo transaction exige o estado inicial vazio e não deve ser repetido após a criação definitiva. Para repetir a suíte, usar --resume.

## 16. Testes de isolamento entre usuários

B não recebeu plano/eventos não vazios de A, mesmo informando IDs de A. IDs de B enviados por A não criaram registros para B.

SQL conferiu perfil próprio, tarefas ligadas a plano/item do mesmo usuário e eventos ligados à tarefa correta. Operações para modificar/arquivar/concluir permanecem negadas.

Agora o teste de isolamento de plano/calendário utilizou dados não vazios, ao contrário da Etapa 07.

## 17. Integração com tarefas

A tarefa possui plano/item, matéria/conteúdo, data, horário, duração, prioridade e status válidos. É recuperada junto com o plano.

Hoje/semana/atrasadas/recentes continuam referidas ao período atual. Como o horário do perfil levou à próxima segunda-feira, a semana atual pode ficar vazia; a atividade futura aparece no plano/calendário. Não se inventaram tarefas para hoje.

Início de sessão de estudo, conclusão, edição, adiamento e cancelamento não foram liberados.

## 18. Integração com calendário

Mês/ano/dia/intervalo retornaram o evento correspondente, na data/horário esperados e sem duplicação. Outra semana retornou vazio.

SQL verificou proprietário, plano, tarefa, data, horas e conteúdo. Metadados preservam fallback. Virada de ano e horários noturnos foram testados em PHP/JavaScript.

GET não materializa eventos nem atualiza status. O atraso calculado permanece cálculo de leitura.

## 19. Integração com frontend

Mudanças pontuais: bloqueio de clique duplo; POST com CSRF; foco na primeira atividade do plano; remoção da chamada automática de simulados bloqueados; mensagens de origem/reutilização; data local em vez de UTC.

Service worker: somente versão de cache 0.1.0-alpha-recovery-08. HTML, CSS, navegação, IDs, classes e estratégia PWA preservados.

Os 12 cenários novos usam app.js real e respostas HTTP sanitizadas: sucesso, clique duplo, 401/403/500, JSON inválido, rede, reutilização, erro secundário de calendário, duas viradas de dia e vazio. DOM simulado não é validação visual.

## 20. Validação visual ou limitação

**Pendente.** A ferramenta não iniciou o runtime Node e retornou erro de caminho inexistente, os error 3. Nenhuma tela foi aprovada.

Roteiro manual:

1. Abrir http://localhost/estudAi_1.1/app/public/index.html e entrar com A, usando credenciais privadas existentes.
2. Conferir dashboard/perfil e recuperar o plano salvo, sem precisar gerar outro.
3. Conferir origem fallback/local e atividade de 16/11, 18h–19h.
4. Abrir calendário, selecionar 16/11, conferir atividade/visão anual e semana vazia.
5. Conferir console/rede, sem exceções, loops, IA, cron ou simulados automáticos.
6. Conferir cache 0.1.0-alpha-recovery-08, recarga e tela estreita.
7. Sair e entrar com B: plano/eventos de A não podem aparecer.
8. Registrar navegador/resultados antes de aprovar visualmente.

Na apresentação em 17/11, a atividade salva será de 16/11 e continuará consultável. Nenhuma atualização automática de perfil ou cron foi habilitada.

## 21. Testes negativos

Anônimo não gera/lê calendário; CSRF ausente/incorreto: 419; JSON inválido: 400; corpo excessivo: 413; método/ação indevidos e parâmetros ambíguos recusados. Datas/intervalos inválidos recusados.

Falta de disponibilidade/matérias foi testada nos helpers PHP reais com fixtures em memória, sem adulterar perfis. Onboarding incompleto foi testado na inicialização JavaScript; a condição do backend foi revisada estaticamente.

Arquivos privados, SQL, backups, logs, testes e documentação permanecem negados. Cron/IA/rotas fora do escopo continuam bloqueados.

## 22. Testes de regressão

Antes das mudanças: 345 verificações HTTP/API. Depois: 337 com --planning-enabled, que aceita os privilégios explicitamente autorizados e o plano persistido.

A diferença são oito expectativas de tarefas vazias removidas explicitamente. A nova suíte verifica conteúdo/vínculos não vazios; não se concedeu privilégio para satisfazer teste antigo.

Passaram os 23 cenários JavaScript anteriores e 12 testes de configuração, incluindo MySQL 1792 em UPDATE WHERE 1=0 dentro de READ ONLY, SELECT negado nos históricos e guardião de IA anterior à rede.

Login/sessão/logout/onboarding de leitura revalidados. Cadastro: validações e duplicidade testadas, sem terceira conta. Cadastro positivo e salvamento positivo de onboarding não foram repetidos; código inalterado e usuários/perfis/snapshots 2/2/3. Não foi executado recovery_stage06.py --resume, que acrescentaria snapshots.

## 23. Arquivos modificados

Sete existentes: .htaccess; app/public/sw.js; app/src/pages/app.js; server/api/plano-estudos.php; server/api/calendario-estudai.php; tests/dashboard_initialization.test.js; tests/recovery_stage07.py.

Cinco novos: este documento; tests/planning_stage08.php; tests/recovery_stage08.py; tests/planning_frontend.test.js; tests/recovery_configuration.py.

O arquivo de runtime storage/logs/app.log recebeu somente acréscimos dos ensaios. O hash do prefixo anterior foi conferido: nenhum registro antigo foi removido ou sobrescrito. Ele permanece ignorado pelo Git e bloqueado por HTTP.

Configurações privadas/evidências estão fora do repositório. Nenhuma configuração global do XAMPP foi editada; hashes conferidos na restauração.

## 24. Testes de sintaxe

Aprovados: php -l nos dois endpoints e teste PHP; node --check nos dois JS de produção e dois testes JS; AST Python; sintaxe Apache privada/original; git diff --check. Diff e arquivos novos revisados, sem instalação de pacotes.

Reexecução, usando caminhos privados e a fixture sanitizada:

    python -B tests/recovery_stage08.py --resume --private-dir <privado08> --accounts <contas06>
    python -B tests/recovery_stage07.py --planning-enabled --private-dir <privado08> --accounts <contas06>
    node tests/planning_frontend.test.js <planning_fixture>
    node tests/dashboard_initialization.test.js <fixture>
    php tests/planning_stage08.php
    python -B tests/recovery_configuration.py --private-dir <privado08>

As suítes HTTP de escrita exigem Apache em loopback e recusam o compartilhado restaurado. Preparar novamente a configuração privada antes de reexecutar. Não executar transaction depois da criação nem installer/migration/seed.

## 25. Estado dos três bancos

| Banco | Antes | Depois | Evidência |
| --- | --- | --- | --- |
| vestibular_estudos | 42 tabelas, 641 linhas | Igual | DDL normalizado, colunas e fingerprints iguais |
| estudai_demo_2026 | 42 tabelas, 641 linhas | Igual | DDL normalizado, colunas e fingerprints iguais |
| estudai_teste_2026 | 42 tabelas, 276 linhas | 42 tabelas, 282 linhas | Um registro em cada uma das seis tabelas autorizadas |

Schema preservado: 406 colunas, 139 índices, 65 FKs. A comparação estrutural exclui somente o contador AUTO_INCREMENT, que pode avançar em rollback. Todos os outros registros do teste mantiveram seus hashes.

Catálogos: 13/42/32/160/22. Usuários/perfis/snapshots: 2/2/3. ia_historico vazio. Backup original: 204.337 bytes e SHA-256 anterior idêntico. Grants do usuário histórico preservados. MySQL80 não foi reiniciado; worker PID 7032 permaneceu.

## 26. Estado do Git e restauração do Apache

Nenhum commit/deploy ou arquivo staged. .env ignorado, não rastreado e com hash inicial preservado. Segredos atuais conferidos sem impressão, ausentes dos arquivos versionáveis.

Durante ensaios: Apache privado somente 127.0.0.1/::1:80, sem SSL, novas portas ou CORS amplo.

O Apache elevado PID 27328 interrompeu a disponibilidade da instância privada e foi identificado como XAMPP também servindo Little Rio Manager. Testes HTTP foram suspensos até o usuário autorizar parada temporária e pedir reabertura ao final.

A parada usou elevação normal do Windows. Após os testes, o Apache privado foi encerrado e o compartilhado **restaurado** com os mesmos hashes de configuração e listeners: 80/443/8081 em todas as interfaces e 8082 em 127.0.0.1. Novo pai: PID 25536.

**Ao final, o Apache compartilhado não está globalmente restrito a loopback**, conforme autorização posterior do usuário de restaurar seus sites. EstudAI conserva Require local em seu .htaccess, além de CSP, nosniff, IA desativada e display_errors Off, independentemente da instância privada.

Após restauração: raiz geral 200; Little Rio Manager/8082 200 com Sign In; EstudAI app.html 200 com CSP; .env/.git 403; calendário anônimo 401. Porta 8081 continua 404: o diretório de Implacável Concursos já não existia antes. HTTPS/443 e a condição anterior de certificado local foram preservados, sem bypass.

## 27. Problemas encontrados

Corrigidos: tentativa remota desnecessária; aviso de origem; repetição não idempotente; rollback dependente do chamador; escrita GET no calendário; validação de mês/data; fuso incompatível; UTC na seleção do dia; simulados automáticos após geração; clique duplo; dependência de tabelas não usadas.

Problemas do teste, investigados e corrigidos: pasta de sessões ausente na nova configuração privada; campos de proprietário supostos no contrato; TIME com segundos. Relatórios intermediários preservados; geração persistida não repetida.

O conflito de Apache foi tratado com identificação, autorização específica e restauração verificada.

## 28. Pendências

Conferência visual real de dashboard/plano/calendário/acessibilidade/responsividade/PWA. Escritas de tarefas, replanejamento, exclusão, exercícios, simulados, redação e OpenRouter permanecem fora da aprovação. Escritores futuros devem respeitar o protocolo de bloqueio.

O compartilhado mantém as interfaces originais dos outros projetos. Pasta ausente da porta 8081 e certificado local são condições anteriores, fora do escopo.

Evidências na pasta privada desta etapa: databases_before.json; databases_after.json; planning_grants.json; created_plan.private.json; planning_api_20261111_094611_223542.json; planning_fixture_20261111_094611_223542.json; configuration_20261111_094925_653842.json; dashboard_api_20261111_094925_795626.json; apache_restore_plan.json; apache_restored.json; restored_http_validation.json; validation_final_stage08.json.

## 29. Próxima etapa recomendada

Analisar este relatório e concluir o roteiro visual da própria Etapa 08. Só depois definir o próximo fluxo. **Não foram iniciados exercícios, simulados, redação com IA nem integração OpenRouter.**

