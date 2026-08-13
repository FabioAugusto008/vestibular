# Auditoria inicial - 0.0.1-alpha

Data: 2026-05-15

## Visao geral

O EstudAI esta organizado como uma aplicacao PHP procedural com frontend estatico em HTML, CSS e JavaScript vanilla. Nao ha framework JavaScript, bundler, `package.json`, pipeline de build ou sistema de componentes formal. O projeto roda diretamente em ambiente Apache/PHP, como XAMPP.

Estrutura encontrada antes das alteracoes:

- `index.html`: tela de login/cadastro, com CSS e JavaScript inline.
- `app.html`: painel autenticado principal, com CSS e JavaScript inline.
- `api/`: endpoints PHP para autenticacao, questoes, historico, metas, preferencias, revisao, simulados, estatisticas, conquistas e anotacoes.
- `config/database.php`: conexao PDO MySQL com credenciais hardcoded.
- `config/helpers.php`: sessao, autenticacao, respostas JSON e chamada da procedure diaria.
- `database/schema.sql`: schema completo, seed de questoes, conquistas e simulados.

## Framework e stack

- Frontend: HTML, CSS e JavaScript vanilla.
- Backend: PHP procedural.
- Banco: MySQL/MariaDB via PDO.
- Servidor esperado: Apache/PHP, com caminho local similar a XAMPP.
- Build/lint: nao identificado.
- PWA: nao identificado antes desta versao.

## Paginas existentes

- `index.html`: autentica usuario, cria conta e redireciona para `app.html` quando ja existe sessao.
- `app.html`: dashboard, questoes do dia, revisao, simulados, estatisticas, conquistas, anotacoes, preferencias e logout.

## Componentes existentes

Nao existem componentes separados em arquivos. Os blocos de interface estao embutidos em `app.html` e `index.html`, incluindo:

- cards de estatisticas;
- navegacao superior por abas;
- formularios;
- modal de meta semanal;
- modal de configuracoes;
- toasts;
- cards de questoes;
- cards de simulados;
- lista de historico;
- grid de conquistas;
- area de anotacoes.

## Arquivos de estilo

Antes da reorganizacao, os estilos estavam inline:

- `index.html`, bloco `<style>`.
- `app.html`, bloco `<style>`.

Problemas: duplicacao de tokens visuais, dificil manutencao, identidade visual acoplada ao markup e predominancia do tema escuro com amarelo/preto.

## Servicos/API existentes

Endpoints PHP identificados:

- `api/auth.php`: cadastro, login, logout e status de sessao.
- `api/questoes.php`: carregar questoes do dia, responder e finalizar.
- `api/historico.php`: historico e streak.
- `api/metas.php`: meta semanal e historico de metas.
- `api/preferencias.php`: tema, notificacoes e horario de lembrete.
- `api/revisao.php`: questoes erradas, resposta em revisao e estatisticas.
- `api/simulados.php`: listar, iniciar, responder, finalizar e historico de simulados.
- `api/estatisticas.php`: estatisticas gerais, evolucao e tempo medio.
- `api/conquistas.php`: listar e verificar conquistas.
- `api/anotacoes.php`: salvar, carregar, listar e remover anotacoes.
- `api/cron_gerar.php`: gera questoes diarias via procedure.

## Chamadas de IA

No momento da auditoria inicial, nao foram encontradas chamadas diretas a OpenAI, OpenRouter, Gemini, modelos, tokens, `Authorization: Bearer` ou endpoints de chat completion no codigo. A etapa 2 criou endpoints internos e manteve a chave apenas no backend.

Risco: qualquer integracao futura com IA nao deve ser feita diretamente no frontend. A chave OpenRouter precisa ficar somente no backend ou em variaveis de ambiente lidas pelo PHP.

## Integracoes externas

- Google Fonts em `index.html` e `app.html`.
- MySQL/MariaDB via PDO.
- Navegador usa `fetch()` para os endpoints locais em `api/`.
- Nao ha SDK externo instalado.

## Banco de dados

Tabelas identificadas no `database/schema.sql`:

- `usuarios`
- `questoes`
- `questoes_do_dia`
- `respostas_usuario`
- `desempenho_diario`
- `preferencias_usuario`
- `metas_semanais`
- `conquistas`
- `conquistas_usuario`
- `simulados`
- `simulado_questoes`
- `simulado_tentativas`
- `simulado_respostas`
- `anotacoes`
- `estatisticas_materia`

Tambem existe a procedure `gerar_questoes_do_dia`.

## Variaveis de ambiente necessarias ou recomendadas

Atualmente o projeto nao le variaveis de ambiente. As credenciais do banco ficam em `config/database.php`.

Recomendadas para a versao 0.0.1-alpha:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `OPENROUTER_API_KEY`
- `OPENROUTER_MODEL`
- `OPENROUTER_BASE_URL`
- `OPENROUTER_SITE_URL`
- `OPENROUTER_SITE_NAME`

## Pontos frageis ou desorganizados

- CSS e JavaScript inline em arquivos HTML grandes.
- Chamadas `fetch()` espalhadas no frontend.
- Credenciais do banco hardcoded.
- Identidade visual antiga muito ligada a amarelo/preto.
- Uso de marcadores visuais antigos em partes da interface.
- Textos e comentarios exibem sinais de problemas de encoding em algumas leituras por terminal, embora os arquivos sejam declarados como UTF-8.
- Ausencia de README e documentacao de versao.
- Ausencia de PWA, manifest e service worker.
- Ausencia de camada de IA.
- Ausencia de migrations versionadas; schema e seed estao no mesmo arquivo.
- Nao ha scripts formais de teste, lint ou build.

## Arquivos que serao alterados

Alteracoes previstas e seguras:

- `index.html`: conectar CSS/JS externos, ajustar nome visual, manifest e service worker.
- `app.html`: conectar CSS/JS externos, ajustar markup visual, adicionar navegacao mobile e PWA.
- `config/database.php`: preparar leitura opcional por variaveis de ambiente sem quebrar defaults atuais.
- `README.md`: criar documentacao raiz.
- `.env.example`: criar placeholders seguros.
- `manifest.webmanifest`: criar manifesto PWA.
- `sw.js`: criar service worker simples.
- `src/styles/*.css`: extrair e organizar estilos.
- `src/pages/*.js`: extrair scripts das paginas.
- `src/config/*.js`: centralizar endpoints frontend.
- `src/services/*.js`: centralizar cliente HTTP do frontend.
- `src/config/ai.php`: configuracao segura para OpenRouter no backend.
- `src/services/ai/*.php`: camada inicial de IA backend sem chave real.
- `docs/**`: criar documentacao solicitada.

## Riscos

- Login/autenticacao: risco se caminhos relativos para `api/auth.php` forem alterados incorretamente.
- Banco: risco se defaults atuais forem removidos; por isso a leitura por ambiente deve preservar fallback local.
- Frontend: extrair JS/CSS pode quebrar funcoes chamadas via `onclick` caso o script seja carregado como modulo. A extracao sera feita como script classico.
- PWA: cache agressivo pode manter arquivos antigos; o service worker sera minimalista, com cache limitado e limpeza por versao.
- OpenRouter: chave jamais deve ir para `index.html`, `app.html` ou arquivos JS publicos.

## Plano de execucao

1. Preservar comportamento atual e criar documentacao de auditoria.
2. Criar estrutura `src/` e `docs/` sem remover arquivos existentes.
3. Extrair CSS/JS inline para arquivos organizados mantendo scripts classicos.
4. Centralizar endpoints e `fetch()` em uma camada frontend pequena.
5. Atualizar visual desktop para off-white, azul escuro e neutros, removendo marcadores visuais antigos.
6. Adicionar base mobile touch-first sem quebrar desktop.
7. Criar PWA simples com manifest e service worker versionado.
8. Criar documentacao de README, versao, changelog, mobile, PWA, OpenRouter e banco.
9. Criar camada backend inicial para OpenRouter com variaveis de ambiente e sem expor chaves.
10. Verificar imports, sintaxe PHP e erros simples.
