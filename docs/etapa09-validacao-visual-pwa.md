# Etapa 09 — Validação visual, integração e estabilização

Data: 11/11/2026. Projeto: C:\xampp\htdocs\estudAi_1.1.
Apresentação prevista: 17/11/2026, Parque Tecnológico de São José dos Campos.

## 01. Resumo executivo

Etapa 09 concluída para o fluxo principal e o escopo visual testado. Um Chrome real executou login, consulta do onboarding, dashboard, planejamento existente, rotina, calendário, expiração de sessão e isolamento entre duas contas fictícias. Dez capturas foram inspecionadas visualmente.

O defeito do Service Worker foi reproduzido e corrigido em app/public/sw.js: falhas de rede sem cache podiam fazer respondWith resolver para undefined. Também foram corrigidos o reaproveitamento de HTML com query de rastreamento, rejeições de CacheStorage e a delimitação de cache/requisições ao projeto.

Resultados: 58 verificações no Chrome, 21 cenários unitários do PWA e 124 verificações HTTP/assets aprovadas. Nenhum banco mudou. Nenhuma geração de plano, chamada OpenRouter, alteração visual, instalação de dependência, commit ou deploy foi executado.

A validação cobre Chrome headless real em perfil isolado, desktop e viewport estreito. Instalação standalone, aparelho físico, outros navegadores e o perfil pessoal do usuário continuam fora dessa comprovação.

## 02. Estado inicial

Foram lidos os relatórios das Etapas 07 e 08, Git/diff, configuração do ambiente, políticas HTTP e testes existentes. HEAD inicial: 67a2fe0, com alterações anteriores sem commit.

O ambiente configurado e a conexão PDO real apontaram para estudai_teste_2026 e estudai_teste_app@localhost. A configuração de IA retornou enabled=false, com ESTUDAI_AI_DISABLED=true preservado.

O plano local da Etapa 08 já existia: ID 2, origem fallback, uma tarefa e um evento. Foi reutilizado. Os inventários iniciais de arquivos, configurações Apache e bancos foram guardados fora da área pública.

## 03. Estado do Apache

A instância compartilhada original permaneceu ativa: processo principal 25536. Nenhuma parada, reinicialização, mudança global de configuração ou de VirtualHost foi feita nesta etapa.

| Porta | Interfaces observadas | Estado |
| --- | --- | --- |
| 80 | 0.0.0.0 e :: | EstudAI responde pelo caminho do projeto |
| 443 | 0.0.0.0 e :: | Listener original preservado; validação HTTPS local não integra a aprovação |
| 8081 | 0.0.0.0 e :: | Projeto ausente; aviso conhecido preservado |
| 8082 | 127.0.0.1 | Little Rio Manager, HTTP 200 |
| 3306 | :: | MySQL80, processo 7032, preservado |

httpd -t retornou Syntax OK. Continua o aviso de DocumentRoot ausente em C:/xampp/htdocs/Setor9/implacavel-concursos-main/public. Esse projeto não foi corrigido.

Os hashes dos arquivos .conf do Apache foram comparados ao início da etapa. O MariaDB do XAMPP não foi iniciado e MySQL80 não foi reiniciado.

## 04. Segurança após restauração dos VirtualHosts

O Apache compartilhado continua escutando amplamente nas portas originais. A restrição do EstudAI é específica do projeto: Require local no .htaccess, com política de arquivos, métodos e ações permitidas, CSP e nosniff preservados.

Os testes locais receberam 403 para arquivos privados e rotas indevidas. A aplicação não ganhou acesso público amplo com esta etapa. A revisão da configuração sustenta a restrição por origem local; não foi realizado teste de rede a partir de outro equipamento, portanto não se declara essa comprovação independente.

A CSP observada foi: connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'. O PHP continua com display_errors Off. Não foram alteradas regras globais ou relaxadas restrições para contornar erros.

## 05. Testes HTTP

tests/http_stage09.py aprovou 124 verificações, incluindo páginas, assets, bytes dos arquivos, tipos MIME, cabeçalhos, autenticação obrigatória e bloqueios.

Login e app.html carregaram. O HTML público de app.html não contém dados pessoais da sessão; as consultas protegidas exigem autenticação. No Chrome autenticado, o dashboard consumiu as respostas reais das APIs.

A URL index.html?utm_source=chatgpt.com retornou o mesmo HTML estático da URL canônica online. Leituras anônimas de plano, calendário, tarefas e onboarding responderam 401 JSON. Rotas de IA, cron e ações indevidas responderam 403.

Nenhum HTTP 200 foi usado isoladamente como evidência de funcionamento visual.

## 06. Testes de assets

Dezenove assets locais declarados pelas páginas/manifesto foram verificados por HTTP, comparados byte a byte com disco e avaliados quanto a MIME e cabeçalhos. Os caminhos relativos e nomes codificados na URL funcionaram.

O favicon declarado dentro do EstudAI retornou 200. /favicon.ico na raiz do localhost retornou 404 e está fora do projeto; não foi criado favicon global nem alterado outro site para resolver esse pedido.

As requisições de fontes Google usadas no navegador responderam 200. As faces utilizadas de Inter e Space Grotesk carregaram. Faces declaradas e não usadas podem permanecer unloaded. Não houve mudança de fontes, CSS ou identidade visual. Sem rede, fontes externas podem usar as alternativas existentes.

## 07. Estado do login

O Chrome real exibiu a tela e validou campos vazios, senha incorreta, autenticação fictícia válida e redirecionamento ao dashboard. O fluxo de logout retornou à tela de login. A invalidação da sessão no servidor levou o frontend de volta ao login ao consultar uma seção protegida.

O cadastro foi exercitado com campos vazios, senha curta e tentativa duplicada, rejeitada com 409. Não foi criada nova conta: o cadastro positivo já tinha comprovação anterior e não foi repetido nesta etapa.

## 08. Estado do onboarding

O perfil fictício já concluído foi carregado. A abertura pelo dashboard mostrou valores persistidos; disponibilidade e navegação entre os passos 0–4 foram exercitadas. O fechamento do modal foi confirmado por classe e visibilidade computada.

Não foi usado Salvar, não foi criado diagnóstico remoto e não houve modificação das respostas no banco. A etapa não revalida uma gravação positiva de onboarding.

## 09. Estado do dashboard

A inicialização real terminou com EstudAIDashboardReady resolvido, identidade fictícia correta, onboarding completo e plano existente carregado. O estado moduleErrors ficou vazio no fluxo online aprovado.

As capturas de desktop e viewport 390 × 844 foram inspecionadas. Não foi observado bloqueio visual no fluxo testado, nem overflow horizontal do documento no viewport estreito. A troca das seções principais funcionou.

## 10. Estado do planejamento

Foi carregado o plano ID 2 da Etapa 08, sem nova geração. Origem exibida: fallback. A atividade existente é de Redacao, “Estudar Tese e repertorio”, em 16/11/2026, 18:00–19:00, duração de 60 minutos.

O frontend mostrou a data, a duração e a origem correspondentes à API. Nenhum clique no botão de geração ou chamada action=gerar_semana foi feito nesta etapa.

## 11. Estado das tarefas

A tarefa existente continuou associada ao plano e ao evento, com status pendente. O banco registra origem manual para a tarefa conforme o ENUM vigente, enquanto os metadados registram a geração fallback; isso já foi tratado na Etapa 08.

A rotina da semana corrente ficou vazia porque a única tarefa está em 16/11, na semana seguinte à data dos testes. O estado vazio foi exibido sem quebrar a aplicação.

A atividade e sua duração apareceram no planejamento e no detalhe do calendário. Iniciar, concluir, editar e excluir tarefas não foram executados nem liberados nesta etapa.

## 12. Estado do calendário

Novembro apresentou um evento da conta A; o detalhe de 16/11 correspondeu à tarefa de Redacao de 60 minutos. Dezembro ficou vazio e o retorno a novembro recuperou o mesmo evento, sem duplicação.

O detalhe foi inspecionado também no viewport estreito. Os valores exibidos de data/duração foram compatíveis com os dados locais existentes; não houve evidência de deslocamento de data no cenário testado. Não se extrapola esse resultado para todos os fusos ou transições de horário.

A conta B não recebeu o plano nem os eventos da conta A.

## 13. Integração frontend/backend

Foram consumidos os contratos reais de autenticação, sessão, onboarding, leituras do painel, plano, tarefas e calendário pelo frontend existente. IDs, origem fallback, perfil concluído, arrays vazios, plano null da segunda conta e eventos corresponderam à renderização observada.

As APIs ficaram fora do cache do Service Worker: todas as respostas de API observadas tinham fromServiceWorker=false. Offline, a API falhou por rede; não houve resposta falsa de sucesso.

Não foi encontrada incompatibilidade que justificasse alterar app.js ou PHP nesta etapa. As mudanças anteriores nesses arquivos foram preservadas.

## 14. Diagnóstico do Service Worker

Foram revisados sw.js, pwa.js e offline.html. O registro observado no perfil isolado foi exclusivamente http://localhost/estudAi_1.1/app/public/, com script sw.js do mesmo diretório. O worker controla clientes desse escopo e agora filtra os recursos da árvore /estudAi_1.1/app/.

Problemas reproduzidos no código anterior:

- Falha de rede e ausência simultânea da página solicitada e de offline.html podiam produzir undefined para respondWith.
- Um asset sem rede e sem cache tinha o mesmo resultado inválido.
- Query de rastreamento impedia correspondência com o HTML já armazenado.
- Rejeições de abertura/gravação do cache podiam escapar sem tratamento.
- A exclusão por prefixo genérico podia remover caches de outra cópia/projeto.
- Pedidos de favicon da raiz, outro projeto e fontes externas podiam ser interceptados desnecessariamente.

A primeira bateria executada contra a versão anterior teve 13 falhas em 20 cenários. A versão final passou os 21 cenários, incluindo a corrida entre clonagem da resposta e consumo do corpo pelo cliente.

## 15. Causa do erro offline, se comprovada

Está comprovada a causa de Failed to convert value to 'Response': os caminhos de falha descritos acima resolviam a Promise sem uma Response válida. A bateria de regressão reproduziu esse comportamento e a versão corrigida devolveu uma resposta válida inclusive sem offline.html.

Também foi comprovado que a query utm_source podia selecionar o fallback offline apesar de existir o HTML canônico em cache.

Não é possível afirmar retrospectivamente se o Apache estava desligado no instante do erro manual original. Não há correlação temporal suficiente. Nesta etapa a indisponibilidade foi emulada no cliente e no próprio worker, preservando o servidor.

Não foi demonstrado conflito no perfil pessoal do usuário. O perfil isolado tinha somente o registro esperado. Caches/registrations antigos do perfil pessoal não foram inspecionados nem apagados.

## 16. Correções implementadas

Somente app/public/sw.js foi alterado em produção nesta etapa:

- Versão 0.1.0-alpha-recovery-09 e nome de cache associado ao caminho da aplicação.
- Limpeza limitada às versões antigas do prefixo específico desta aplicação.
- Filtro de origem/caminho, preservando exclusão de APIs e métodos diferentes de GET.
- Navegação network-first, armazenamento canônico sem query e consulta offline de HTML ignorando a query.
- Fallback para offline.html e, na ausência dele, HTML mínimo com status 503.
- Asset ausente offline retorna Response 503, sem TypeError.
- Leitura/gravação de CacheStorage protegida contra rejeições; clonagem antes do consumo do corpo.
- Atualizações de cache acompanhadas pelo ciclo de vida do evento.

O comportamento stale-while-revalidate dos assets já armazenados foi preservado. Respostas reais de erro da rede não foram convertidas em sucesso. APIs continuam dependendo do backend.

Caches genéricos antigos não foram apagados por não haver garantia de propriedade exclusiva. Recursos do próprio EstudAI ainda podem ser recuperados desses caches por URL quando o cache atual não os contém.

## 17. Testes de PWA

| Camada | Resultado |
| --- | --- |
| VM do worker, sem rede real | 21/21 cenários aprovados |
| Chrome, URL com query e cache aquecido | Login estático offline, HTTP 200 vindo do worker |
| Chrome, navegação não visitada | offline.html disponível |
| Chrome, cache do EstudAI vazio e offline.html ausente | HTML mínimo HTTP 503 |
| Chrome, asset não armazenado sem rede | HTTP 503 |
| Chrome, chamada de API sem rede | Falha de rede, sem cache/sucesso artificial |
| Chrome, retorno da rede | Login carregou normalmente |
| Isolamento | Caches sentinela de outros projetos preservados |

A emulação foi aplicada à página e ao target do Service Worker. Emular somente a página não interrompia as requisições próprias do worker; essa limitação foi corrigida no teste, sem alterar o servidor.

O teste a frio removeu apenas o prefixo do EstudAI no perfil descartável criado para a execução. Nenhum cache, localStorage ou IndexedDB do navegador pessoal foi acessado.

## 18. Testes de regressão

A execução final do Chrome aprovou 58 verificações; o teste HTTP aprovou 124; o teste unitário do worker aprovou 21. O navegador registrou zero exceções JavaScript não tratadas e zero erros de worker no fluxo final.

Casos de rede deliberadamente offline, senha inválida e cadastro duplicado produziram falhas esperadas, documentadas nas evidências. Eles não foram classificados como regressão.

Não foram repetidas gerações de plano, testes concorrentes ou gravações da Etapa 08: a mudança de produção está no worker, e a regressão selecionada cobriu o fluxo real afetado. Não se soma a contagem histórica das etapas anteriores aos testes desta etapa.

## 19. Validação visual realizada ou pendente

Executada com Chrome/157.0.8103.39 real, em modo headless, perfil isolado, viewport desktop 1365 × 900 e estreito 390 × 844. As capturas foram feitas após fontes/animações, e as dez imagens finais foram abertas e inspecionadas.

Foram vistas: login desktop, dashboard desktop, disponibilidade do onboarding, plano desktop, rotina desktop, calendário desktop, dashboard estreito, detalhe de calendário estreito, login offline aquecido e fallback de emergência sem cache.

Não se observou defeito visual impeditivo nessas telas. O fallback sem cache é intencionalmente HTML mínimo; o design existente das páginas não foi alterado.

O relatório automático bruto mantém visual_inspection_pending=true porque a automação não certifica inspeção humana/visual. A inspeção posterior está registrada separadamente em visual_review_stage09.json; o relatório bruto foi preservado.

Permanecem sem comprovação: aparelho físico, PWA instalado em janela standalone, outros navegadores, revisão completa de acessibilidade e atualização do worker no perfil pessoal.

## 20. Arquivos modificados

| Arquivo desta etapa | Finalidade |
| --- | --- |
| app/public/sw.js | Correção pontual dos defeitos de cache/fallback |
| tests/service_worker_stage09.test.js | Regressão unitária do worker |
| tests/browser_stage09.mjs | Chrome real via CDP, perfil isolado, sem dependências novas |
| tests/http_stage09.py | HTTP/assets/segurança, sem escrita na aplicação |
| docs/etapa09-validacao-visual-pwa.md | Este relatório |

No inventário do início desta etapa, os demais arquivos anteriores foram preservados por comparação de bytes/hash. Permanecem no Git as alterações anteriores de .htaccess, app.js, calendario-estudai.php, plano-estudos.php, dashboard_initialization.test.js e recovery_stage07.py, além do sw.js agora corrigido.

Também foram preservados os arquivos anteriores ainda não rastreados: relatório da Etapa 08, planning_frontend.test.js, planning_stage08.php, recovery_configuration.py e recovery_stage08.py. Não foram incluídos em commit.

As evidências privadas ficaram em C:\Users\player\AppData\Local\EstudAI\Recovery\20261111_180623_ae63eab0, fora do DocumentRoot, com ACL restrita. Relatórios não contêm credenciais, cookies, tokens ou dados de contas reais.

## 21. Verificações de sintaxe

node --check aprovado para o worker e JavaScript dos testes novos. O teste Python novo passou análise AST. PHPs e JavaScript que já estavam alterados pelas etapas anteriores também receberam verificação de sintaxe final, sem alteração de conteúdo.

httpd -t: Syntax OK, com o aviso conhecido da porta 8081. git diff --check: aprovado. Os avisos do Git sobre futura conversão LF/CRLF não foram convertidos em normalização de arquivos.

## 22. Estado dos bancos

A comparação administrativa somente leitura verificou esquema/DDL, colunas e conteúdo integral por tabela nos três bancos, antes e depois da etapa. As impressões digitais anteriores da Etapa 08 também coincidiram.

| Banco | Tabelas | Registros totais | Resultado |
| --- | --- | --- | --- |
| vestibular_estudos | 42 | 641 | Inalterado |
| estudai_demo_2026 | 42 | 641 | Inalterado |
| estudai_teste_2026 | 42 | 282 | Inalterado |

A conta da aplicação permaneceu estudai_teste_app@localhost, com os mesmos 22 itens de SHOW GRANTS da Etapa 08. Não é root e não recebeu privilégios novos. Mantém as concessões específicas já necessárias ao escopo autorizado anterior.

O backup original também permaneceu íntegro. Não foram executados schema, migrations, seeds, novas gerações ou escrita nos bancos históricos. As autenticações/logout alteraram apenas o estado de sessão, sem alterar o conteúdo dos bancos.

## 23. Estado do Git

HEAD permaneceu 67a2fe0, sem commit, deploy ou arquivos preparados no índice. O estado anterior sujo foi preservado; esta etapa não usou reset, checkout de descarte ou limpeza.

.env continua ignorado e não rastreado. Credenciais de teste e evidências sensíveis permaneceram fora do repositório. O relatório final diferencia as alterações desta etapa das mudanças anteriores ainda pendentes.

## 24. Riscos encontrados

O perfil pessoal pode continuar com um worker antigo até atualizar o registro correto. Essa hipótese não foi verificada nesse perfil. Não é apropriado limpar indiscriminadamente o armazenamento de localhost.

O Apache compartilhado mantém listeners amplos por necessidade dos outros projetos. A proteção atual depende de Require local e das demais regras do EstudAI; não houve teste de rede independente por outro equipamento.

Sem rede, conteúdo estático aquecido pode abrir, mas autenticação e dados do backend não funcionam. A página offline continua sendo uma resposta correta quando o recurso não está armazenado.

A interface ainda apresenta controles de funcionalidades fora do escopo recuperado. Sua presença não representa aprovação de questões, simulados, IA, geração adicional ou alterações de tarefas.

O aviso do projeto ausente da porta 8081 e o favicon global 404 permanecem externos ao escopo do EstudAI. Não impedem o fluxo demonstrado no caminho correto.

## 25. Pendências

Não restou falha comprovada sem correção no escopo executado. Para a demonstração presencial, falta o ensaio no Chrome normal do usuário, conferindo a atualização do worker e o ambiente/tela efetivamente usados.

Instalação standalone e aparelho físico podem ser verificados manualmente se fizerem parte da apresentação. Essas pendências não foram declaradas como aprovadas por esta etapa.

Não foi repetido cadastro positivo, salvamento de onboarding, geração de plano ou mutação de tarefa. As respectivas evidências anteriores devem ser consultadas quando necessárias. Não houve autorização automática de funcionalidades seguintes.

## 26. Roteiro manual de validação

1. Com o Apache atual funcionando, abra http://localhost/estudAi_1.1/app/public/index.html. Confirme também a URL com ?utm_source=chatgpt.com.
2. No DevTools, Application > Service Workers, localize somente o script /estudAi_1.1/app/public/sw.js, escopo /estudAi_1.1/app/public/. Atualize esse registro e recarregue. Confira no código a versão 0.1.0-alpha-recovery-09. Não remova registros de outros projetos.
3. Não use “Clear site data” de localhost, nem limpeza geral de caches, IndexedDB ou localStorage. Se precisar testar um perfil vazio, crie um perfil separado do Chrome.
4. Entre com a conta fictícia A já existente, obtida pelo mecanismo privado local, sem copiar credenciais para console/relatório. Confira redirecionamento, identidade e painel.
5. Abra a configuração do onboarding, confira as respostas e os passos, e feche sem salvar nem gerar diagnóstico.
6. Abra o plano existente, confirme fallback e a atividade de 16/11/2026, 18:00–19:00, 60 minutos. Não clique para gerar outro plano.
7. Abra rotina e calendário. Na data dos testes, rotina atual vazia é esperada. No calendário, selecione 16/11, avance a dezembro vazio e volte; deve continuar existindo apenas um evento.
8. Emule 390 × 844 ou use a tela prevista para a apresentação. Confira navegação e detalhe da atividade. Não acione iniciar/concluir/editar tarefas.
9. Faça logout e entre com a conta fictícia B: ela não deve ver o plano/evento da conta A. Faça logout novamente.
10. Para conferir cache aquecido, visite o login online antes. Emule offline também para o Service Worker (confira a opção Offline na área Service Workers se disponível e confirme que os fetches do worker falham). A URL com query deve abrir o login estático armazenado; autenticação continua dependendo de rede.
11. Uma navegação não armazenada pode mostrar a página offline. Não desligue o Apache para esse teste, pois Little Rio Manager compartilha a instância. O cenário sem nenhum cache já foi testado no perfil isolado e retornou 503 válido.
12. Restaure a rede no DevTools, recarregue e confirme o retorno do login. No Console/Network, não deve aparecer Failed to convert value to 'Response', chamada OpenRouter ou erro de API convertido em sucesso.
13. Mantenha o Apache e Little Rio Manager ativos. Para a demonstração, use apenas o fluxo aprovado e as contas fictícias existentes.

## 27. Próxima etapa recomendada

Submeter este relatório à análise do responsável e realizar o ensaio presencial do roteiro aprovado, sem mudanças funcionais automáticas.

A Etapa 09 está tecnicamente e visualmente concluída no escopo executado em Chrome real. O ensaio no perfil/equipamento da apresentação permanece recomendado. Nenhuma etapa seguinte, questões, simulados ou integração OpenRouter foi iniciada.
