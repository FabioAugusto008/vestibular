# Etapa 07 — retomada e validação final

Data da retomada: 09/11/2026. Apresentação prevista: 17/11/2026.

## 01. Resumo executivo

A inicialização de leitura do painel passou nos testes HTTP/API e no teste do JavaScript real com DOM simulado. Resultado final: **345 verificações HTTP/API, 23 cenários JavaScript e 12 verificações de configuração/proteções aprovados**. A interface **não está visualmente aprovada**: o host de navegador e a conexão nativa estavam indisponíveis nesta retomada.

As alterações anteriores foram preservadas. Nesta retomada, apenas os dois testes da Etapa 07 foram ampliados e este relatório foi criado. Não houve nova funcionalidade, mudança de HTML/CSS, commit, deploy, schema, migration, seed, geração de questões/planos, cron ou requisição de IA.

## 02. Estado encontrado

O Git continha alterações acumuladas das Etapas 05–07; compará-las somente com HEAD confundiria trabalhos de etapas diferentes. A referência da Etapa 07 é o inventário privado `project_before.json`, no diretório de evidências indicado abaixo.

O Apache estava parado. Foi iniciado usando a configuração privada existente `apache-loopback-stage07.conf`, após `httpd -t`, sem modificar sua configuração. Escuta confirmada somente em `127.0.0.1:80` e `[::1]:80`. O MySQL80 já estava em execução e não foi reiniciado por esta retomada.

A configuração efetiva aponta para `estudai_teste_2026`, conta `estudai_teste_app@localhost`, MySQL 8.0.45, porta 3306. `ESTUDAI_AI_DISABLED=true` e a configuração de IA desativada foram verificados sem imprimir segredos. O hash de `.env` permanece igual ao inventário anterior à Etapa 07.

A implementação estava presente; faltavam finalizar/revalidar testes e consolidar auditoria/documentação. Não foi necessário alterar novamente código de produção.

## 03. Arquivos modificados

Comparação com o início da Etapa 07: **15 arquivos existentes modificados e 3 novos**.

| Arquivos existentes | Alteração da Etapa 07 |
| --- | --- |
| `.htaccess` | Lista explícita por método e ação; preserva negações de arquivos privados |
| `app/src/pages/app.js` | Inicialização isolada por módulo, sessão, armazenamento e estados vazios/indisponíveis |
| `app/src/config/api.js` | Flag explícita de recuperação para inicialização de leitura |
| `app/src/services/http.js` | Evento de sessão expirada em HTTP 401 |
| `app/public/sw.js` | Versão de cache atualizada, mantendo estratégia PWA |
| `server/helpers/helpers.php` | Validação da ação GET e transação MySQL READ ONLY |
| `server/api/preferencias.php`, `metas.php`, `questoes.php` | Retirada das escritas automáticas de GET e uso da proteção de leitura |
| `server/api/historico.php`, `revisao.php`, `estatisticas.php`, `conquistas.php`, `plano-estudos.php`, `tarefas-estudo.php` | Uma chamada à proteção de leitura em cada endpoint |

Novos: `tests/recovery_stage07.py`, `tests/dashboard_initialization.test.js` e este documento.

As mudanças anteriores em `database.php`, `openrouter.php`, `openrouterClient.php`, `weeklyReview.php` e `onboarding.php` foram revisadas e preservadas, sem nova alteração nesta etapa. A correção anterior de HTTP 419 em `helpers.php` também foi preservada. Arquivos de instalação, catálogos, backups, documentação anterior e credenciais privadas permanecem preservados.

## 04. GETs corrigidos e bloqueados

Referência histórica: [inspeção da Etapa 06](etapa06-gets-com-escrita.md).

| GET | Efeito anterior | Resultado atual |
| --- | --- | --- |
| `preferencias?action=carregar` | INSERT quando não havia preferências | Retorna defaults transitórios; nenhum registro criado |
| `metas?action=carregar` | INSERT da meta ou UPDATE de progresso | Agrega respostas em memória; mantém default original de 100 questões sem persistir |
| `questoes?action=carregar` | Preparava lote em `questoes_do_dia` antes do switch | Consulta somente o lote existente; retorna vazio e “treino ainda não preparado” |
| `calendario-estudai?action=mes/ano/dia/eventos` | Sincronização com INSERT | Continua bloqueado; fonte preservada |
| `simulados-planejados?action=listar/carregar` | Liberação automática com UPDATE | Continua bloqueado; fonte preservada |

A preparação original de questões permanece condicionada a POST reconhecido e CSRF válido, mas esses POSTs continuam negados pelo HTTP nesta etapa. Não foi habilitado um fluxo novo de escrita.

## 05. Testes JavaScript

`tests/dashboard_initialization.test.js` executa `api.js`, `http.js` e `app.js` reais no Node VM, com IDs obtidos do HTML real e respostas sanitizadas das APIs reais. Não usa navegador nem rede real.

Os 23 cenários cobrem: conta autenticada com dados vazios; cinco formas de localStorage corrompido/inadequado; armazenamento inacessível; questões 403, histórico/metas 500; JSON inválido em estatísticas e autenticação; falha de rede; múltiplas promises rejeitadas; falha de rotina; perfil 500; sessão anônima; autenticação 401/500; perfil 401; 401 secundário; vários 401 simultâneos; onboarding incompleto; somas de números PDO retornados como strings; denominador real de questões e matéria acentuada.

Cada cenário verifica ausência de POST automático, IA, diagnóstico, cron, calendário e simulados; no máximo 14 requisições; nenhuma escrita/remoção de localStorage durante a inicialização; no máximo um redirecionamento. O processo terminou sem rejeição não tratada.

## 06. Tratamento de falhas e fluxo de inicialização

`app.html` carrega configuração, serviço HTTP e `app.js` na estrutura existente. `initializeDashboard()` verifica autenticação antes dos módulos privados, lê identidade/CSRF, prepara a rotina local e carrega onboarding.

Sessão anônima/401 redireciona uma vez. Falha 500 ou JSON inválido na autenticação interrompe a inicialização privada e apresenta indisponibilidade. Onboarding incompleto abre o assistente e retorna antes dos módulos. Perfil indisponível não é confundido com perfil inexistente.

Com sessão válida, `loadDashboardReadModules()` usa `Promise.allSettled` sobre nove carregadores. `window.EstudAIDashboardReady` permite aguardar o resultado. Falhas secundárias marcam apenas o módulo correspondente, exibem indisponibilidade e mantêm as demais leituras. HTTP 401 continua crítico mesmo dentro dos catches dos módulos.

Dados vazios são tratados como vazios: não há plano, tarefas, respostas ou treino diário preparado. Os 32 registros do catálogo de questões não significam que exista lote do dia. Progresso diário vazio mostra 0/0; não se inventa um lote de 20 questões. Erro de histórico/rotina não deve ser mostrado como progresso zero válido.

### Matriz real de dependências

Todos os endpoints abaixo usam GET. Nos nove endpoints de dashboard há validação de ação e transação READ ONLY; as consultas pessoais usam o ID da sessão. Metadados de tabelas/colunas também são consultados quando o código original verifica compatibilidade.

| Função / dependência | Endpoint e ação | Tabelas lidas | Vazio e falha |
| --- | --- | --- | --- |
| `initializeDashboard`, obrigatória | `auth.php?action=status` | Sessão PHP | Anônimo/401 redireciona; 500/JSON inválido interrompe |
| `loadOnboarding`, após sessão | `onboarding.php?action=status` | estudo_perfis | Incompleto abre assistente; 500 sinaliza perfil indisponível |
| `loadPreferencias` | `preferencias.php?action=carregar` | preferencias_usuario | Defaults sem INSERT; erro isolado |
| `loadQuestoes` | `questoes.php?action=carregar` | questoes_do_dia, questoes, questoes_alternativas, respostas_usuario, desempenho_diario | Lote ausente retorna []; erro isolado |
| `loadHistorico` | `historico.php`, sem ação | desempenho_diario | Histórico []; falha marca indisponível |
| `loadMeta` | `metas.php?action=carregar` | metas_semanais, respostas_usuario | Meta calculada sem registro; erro isolado |
| `loadRevisaoStats` | `revisao.php?action=estatisticas` | questoes, respostas_usuario | Sem pendências; erro isolado |
| `loadEstudaiStats` | `estatisticas.php?action=estudai_geral` | estudo_perfis, planos_estudo, tarefas_estudo, sessoes_estudo, respostas_exercicios_planejados, simulados_planejados | Agregados vazios; erro isolado |
| `loadPlanoAtivo` | `plano-estudos.php?action=carregar_ativo` | planos_estudo, estudo_perfis, tarefas_estudo | Plano null; falha não significa “nenhum plano” |
| `loadRotina` | `tarefas-estudo.php?action=hoje/semana/atrasadas/recentes&limite=6` | tarefas_estudo, planos_estudo, sessoes_estudo | Quatro consultas, listas []; falha marca rotina indisponível |
| `loadConquistas` | `conquistas.php?action=listar` | conquistas, conquistas_usuario | Catálogo 22, nenhuma desbloqueada; nenhum POST verificar |

O modo de recuperação evita diagnóstico remoto e concessão automática de conquistas. Calendário e simulados são acionados ao abrir suas seções, continuam bloqueados e não fazem parte da inicialização aprovada.

## 07. APIs liberadas

Autenticação/onboarding mantêm os métodos e contratos previamente autorizados. Novas liberações do dashboard são exclusivamente GET:

- `preferencias:carregar`, `questoes:carregar`, `metas:carregar`.
- `historico` sem ação.
- `revisao:estatisticas/carregar` (carregar é leitura do módulo existente, também testada).
- `estatisticas:estudai_geral`, `conquistas:listar`.
- `plano-estudos:carregar_ativo`.
- `tarefas-estudo:hoje/semana/atrasadas/recentes`.

A lista HTTP filtra método e query string; o helper PHP valida a ação já decodificada, inclusive contra sobrescrita/array de parâmetros. O Apache também restringe os nove arquivos a GET. Alterar a flag JavaScript não amplia permissões no servidor.

POST/PUT nos endpoints novos, cron, IA, diagnósticos, geração/arquivamento de planos, respostas/finalização de treino, atualização de metas, concessão de conquistas, alterações de tarefas, calendário, simulados e demais ações fora da lista continuam bloqueados.

## 08. Privilégios MySQL

A auditoria confirmou 18 instruções SHOW GRANTS: USAGE e 17 tabelas; 21 privilégios por tabela, nenhum privilégio global efetivo, por schema, papel ou GRANT OPTION.

Preservados: usuarios SELECT/INSERT; estudo_perfis SELECT/INSERT/UPDATE; onboarding_respostas SELECT/INSERT. As 14 adições da Etapa 07 são somente SELECT:

| Tabelas | Uso |
| --- | --- |
| preferencias_usuario | Preferências |
| questoes, questoes_alternativas, questoes_do_dia | Treino existente e revisão |
| respostas_usuario, desempenho_diario | Respostas próprias, histórico, metas e revisão |
| metas_semanais | Leitura da meta |
| conquistas, conquistas_usuario | Catálogo e desbloqueios próprios |
| tarefas_estudo, planos_estudo | Plano ativo e rotina |
| sessoes_estudo | Agregados/sessão da tarefa |
| respostas_exercicios_planejados, simulados_planejados | Agregados de estatísticas, sem geração |

A conta da aplicação recebeu erro de permissão ao tentar SELECT nos dois bancos históricos. Root foi utilizado somente para auditoria administrativa de leitura nesta retomada; não é a conta da aplicação.

## 09. HTTP/API

Suíte final: **345 verificações aprovadas**. Duas contas fictícias preexistentes, contratos JSON, leituras, estados vazios, parâmetros de usuário forjados, ações duplicadas/array, métodos indevidos, arquivos privados e assets públicos.

As respostas de recursos públicos foram comparadas byte a byte com os arquivos em disco, inclusive nomes com espaços. CSP e nosniff foram verificados nas respostas. O teste foi ampliado nesta retomada; um erro inicial do próprio teste ao localizar nomes URL-encoded foi corrigido com decodificação do caminho local, sem alterar assets.

As execuções intermediárias e seus relatórios foram preservados, inclusive as tentativas de auditoria que precisaram distinguir metadados em cache dos contadores retornados por SHOW CREATE TABLE. O relatório final identifica a coleta válida.

## 10. Autenticação e onboarding

Login válido das duas contas, senha incorreta 401, rotação de ID de sessão, status autenticado, cookies HttpOnly/SameSite=Lax, perfil completo, logout e acesso posterior 401 passaram.

Onboarding status/carregar anônimo retorna 401; salvamento sem CSRF retorna 419; tentativa de salvar por GET retorna 405. As duas contas, os dois perfis e os três snapshots anteriores foram preservados. Não se repetiu o teste da Etapa 06 que salva novos snapshots, nem foram refeitos cadastro ou onboarding válidos.

HTTP é local, portanto o cookie não exige Secure neste ambiente. O contrato anterior de logout por GET altera a sessão PHP, sem escrita SQL, e foi preservado.

## 11. Isolamento de usuários

Perfis autenticados são distintos. Forjar `usuario_id` de outra conta não altera o perfil retornado. Nos 13 caminhos de leitura do teste, a resposta com ID forjado é comparada integralmente com a resposta original da mesma sessão.

As consultas pessoais revisadas usam `currentUserId()` e parâmetros preparados; catálogos são compartilhados. Limite da evidência: progresso/tarefas/planos estão vazios. Não foi criado conjunto não vazio de progresso para alegar isolamento dinâmico desses registros; seus filtros foram inspecionados.

## 12. Segurança e GET sem escrita

A prova combina inspeção do SQL, permissões mínimas, transação READ ONLY e fingerprints de conteúdo das 42 tabelas de cada banco antes/depois. Não se limita a contagens iguais.

Foi tentado UPDATE com WHERE 1=0 dentro da proteção de leitura, em tabela que possui UPDATE anterior autorizado: MySQL rejeitou com erro 1792. A transação foi encerrada com rollback; nenhum registro foi atualizado. Os GETs novos não possuem privilégio de escrita nas suas tabelas de progresso.

.env, .env.example, .git, .htaccess, diretório/arquivo de backup, logs, SQL, catálogo de recuperação, testes, documentação e arquivos PHP privados foram negados. Cron, IA e APIs fora do escopo retornaram 403 antes de executar suas operações.

CSP: `connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'`. Require local, negação de listagem e restrições de PHP/arquivos privados foram preservados. Essa CSP não é uma política completa de todos os tipos de recurso. Os HTMLs originais ainda referenciam Google Fonts; nada foi redesenhado ou baixado nesta etapa. O teste HTTP verifica recursos locais, não a disponibilidade dessas fontes externas.

A configuração de IA permanece desativada; o teste do guardião confirmou rejeição anterior ao caminho de rede. Não foi feita chamada remota de IA. .env é ignorado e não rastreado pelo Git; segredos reais foram verificados sem divulgação.

## 13. Bancos preservados

| Banco | Tabelas | Total de linhas | Conteúdo antes/depois |
| --- | --- | --- | --- |
| vestibular_estudos | 42 | 641 | Fingerprints de todas as tabelas idênticos |
| estudai_demo_2026 | 42 | 641 | Fingerprints de todas as tabelas idênticos |
| estudai_teste_2026 | 42 | 276 | Fingerprints de todas as tabelas idênticos |

Metadados de schema/tabelas/colunas/índices/FKs/rotinas/parâmetros/migrations e contagens foram comparados. Os catálogos do banco de testes continuam com 13 matérias, 42 conteúdos, 32 questões, 160 alternativas e 22 conquistas; usuários/perfis/snapshots continuam 2/2/3.

Limitação explícita: a coleta antiga da cópia histórica retornava AUTO_INCREMENT nulo para 16 tabelas vazias. SHOW CREATE TABLE agora informa início 1. Somente esse caso “contador antes indisponível em tabela vazia; agora 1” foi excluído da igualdade estrita do contador; os demais campos e todos os hashes foram comparados. Não se tentou alterar contadores. Para contadores disponíveis, foi usado SHOW CREATE TABLE para evitar divergências da consulta inicial a information_schema.

O backup original privado mantém tamanho de 204.337 bytes e SHA-256 registrado anteriormente. Nenhum banco histórico foi usado pela aplicação ou recebeu DDL/DML desta retomada.

## 14. Validação visual

**Pendente.** A tentativa anterior de abrir o navegador havia sido impedida pela revisão automática por limite de uso. Na retomada, a consulta às ferramentas retornou “browser access host is unavailable” e conexão nativa indisponível. Nenhuma tela foi renderizada/inspecionada nesta retomada.

O DOM simulado não verifica layout, eventos reais de navegador, console, foco/acessibilidade, responsividade ou ciclo real do service worker.

### Roteiro manual

1. Abrir `http://localhost/estudAi_1.1/app/public/index.html`.
2. Entrar com uma das contas fictícias existentes, usando as credenciais guardadas localmente no arquivo privado da Etapa 06; não publicá-las.
3. Confirmar redirecionamento ao painel, identificação da conta e resumo de perfil completo, sem reabrir indevidamente o assistente.
4. Conferir vazio real: treino ainda não preparado, progresso 0/0, histórico vazio, nenhum plano/tarefa e nenhuma conquista desbloqueada. A meta padrão é transitória.
5. No console/rede, conferir ausência de exceções não tratadas, loops, POST automático, IA, diagnóstico, cron ou geração. A inicialização normal faz 14 GETs.
6. Conferir navegação entre as seções de leitura autorizadas e tela estreita, sem acionar geração, respostas, configurações de escrita, calendário ou simulados.
7. Confirmar atualização para o cache `0.1.0-alpha-recovery-07`; verificar novamente após recarregar.
8. Efetuar logout e abrir o painel novamente: deve voltar ao login. Repetir com a segunda conta e verificar identificação/perfil próprios.
9. Registrar resultado, navegador e erros observados antes de considerar a interface aprovada.

## 15. Sintaxe e Git

Aprovados: `php -l` nos 15 PHPs com diff acumulado; `node --check` nos quatro JS de produção alterados e no teste JS; parse AST dos testes Python; `httpd -t`; `git diff --check`.

Diff revisado integralmente, incluindo service worker, API, helpers, permissões HTTP e testes. Não foi encontrada necessidade comprovada de nova correção no aplicativo. A mudança de versão do cache é necessária para atualizar o código de recuperação; o restante do service worker foi mantido. HTML, CSS, assets e dependências não mudaram.

Git permanece com alterações das etapas anteriores e desta etapa, sem arquivos staged e sem commit/deploy. As advertências de conversão LF/CRLF são do Git local; `git diff --check` passou.

## 16. Pendências

- Executar o roteiro visual em navegador disponível.
- Manter explícita a limitação de dados de progresso vazios e dos 16 contadores antigos indisponíveis.
- Escritas de progresso, configuração, treino, calendário, simulados e geração de planos continuam fora da aprovação desta etapa. Botões existentes podem receber 403; isso não significa funcionalidade validada.
- A flag de recuperação deve ser retirada apenas em etapa futura autorizada, junto com validação das operações correspondentes.
- Referências originais a fontes externas e funcionamento real do PWA/offline ainda precisam de inspeção no navegador.

## 17. Estado final da Etapa 07

**Validações técnicas concluídas e aprovadas dentro do escopo de leitura, com validação visual pendente.** As ressalvas não autorizam declarar toda a interface ou os fluxos de escrita prontos para a apresentação. Nenhum serviço global do MySQL foi modificado; o Apache local ficou disponível na configuração restrita já existente.

Evidências privadas: `C:\Users\player\AppData\Local\EstudAI\Recovery\20261107_111547_57751376`. Relatórios finais HTTP/API e fixtures sanitizadas têm sufixo `20261109_111153_658458`; configuração: `config_validation_20261109_111139_036608.json`; auditoria de bancos: `audit_final_20261109_111335_048303.json`. O inventário de caminhos dessa auditoria intermediária é substituído pelo inventário normalizado em `validation_final_stage07_*.json`, que também consolida os resultados e as verificações finais. Nenhuma credencial integra este documento.

### Reexecução dos testes sem gravar no banco

```powershell
& 'C:\Python314\python.exe' -B tests\recovery_stage07.py --private-dir '<diretorio privado Etapa 07>' --accounts '<diretorio privado Etapa 06>\test_accounts.private.json'
& 'C:\Program Files\nodejs\node.exe' tests\dashboard_initialization.test.js '<fixture sanitizada produzida pelo teste HTTP>'
```

Não executar `recovery_stage06.py --resume` para repetir esta validação: ele salva novos snapshots de onboarding. Nenhum script de instalação deve ser executado para um banco já existente.

## 18. Próxima etapa sugerida

Analisar este relatório e realizar a conferência visual pendente da própria Etapa 07. Somente depois definir o escopo da Etapa 08. **A Etapa 08 não foi iniciada.**

