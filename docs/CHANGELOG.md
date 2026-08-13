# Changelog

Todas as mudancas relevantes do EstudAI serao documentadas neste arquivo.

## [0.1.0-alpha] - 2026-05-28

Nome: Launch Core

### Added

- Migration `database/migrations/0005_estudai_launch_core.sql`.
- Endpoint `api/redacao.php` para Redacao ENEM com historico e analise orientativa nao oficial.
- Action `api/plano-estudos.php?action=replanejar_semana`.
- Actions novas em `api/tarefas-estudo.php` para editar, adiar, remarcar, cancelar, iniciar/pausar/finalizar tempo e listar concluidas recentes.
- `app/public/offline.html` e cache PWA versionado para Launch Core.

### Changed

- Dashboard mostra proxima acao, resumo semanal, progresso, atrasos e atalhos.
- Plano semanal mostra estrategia, periodo, progresso, alertas e tarefas por dia.
- Rotina separa hoje, atrasadas, proximas e concluidas recentemente.
- Exercicios, revisoes, simulados, estatisticas e conquistas receberam acabamento de interface sem mudar identidade visual.

### Security

- CSRF concluido nos POSTs legados de questoes, revisao, simulados, metas, preferencias, anotacoes e conquistas.
- Rate limit simples por sessao/IP para login, cadastro, diagnostico, plano, replanejamento, IA, redacao e revisao semanal manual.

### Database

- `tarefas_estudo.tempo_real_min`.
- `respostas_exercicios_planejados.motivo_erro`.
- Nova tabela `redacoes_enem`.
- Novas conquistas discretas e academicas.

### AI

- IA continua sem gerar questoes, alternativas, gabaritos, exercicios ou simulados em tempo real.
- Redacao usa analise orientativa, com estimativa nao oficial e sem substituir corretor humano.

### Known Issues

- Tempo real depende da migration `0005`.
- Sem testes automatizados nesta entrega.

## [0.0.4-alpha] - 2026-05-17

### Added

- Migration `database/migrations/0004_estudai_weekly_flow_fix.sql`.
- Endpoint `api/revisoes-ia.php` com `carregar_por_tarefa`, `gerar_por_tarefa`, `carregar_semana` e `gerar_semana`.
- Action `api/plano-estudos.php?action=gerar_semana`.
- Tabelas `planejamento_semanal_controle` e `revisoes_conteudo_ia`.

### Changed

- Plano semanal volta a ser o padrao do produto; plano anual permanece como legado.
- Onboarding agora e obrigatorio e estruturado em objetivo, disponibilidade, reforcos, preferencias e revisao.
- ENEM virou modo fechado com materias automaticas e escolha apenas de lingua/reforcos.

### Fixed

- Validacao descarta plano com datas antes de hoje, depois da prova, fora da semana, fora da disponibilidade ou com materia invalida.
- Campo textual de horario foi removido da interface.
- `custom` segue traduzido como `Atividade` para o usuario.

### Security

- Acoes criticas novas usam `requireLogin()`, CSRF, rate limit e filtro por `usuario_id`.
- A chave OpenRouter permanece no backend.

### Database

- `estudo_perfis` recebe `onboarding_completo`, `modo_estudo`, `lingua_estrangeira`, `disponibilidade_json`, `reforcos_json` e `materias_base_json`.
- `planos_estudo` recebe campos de plano semanal e validade.
- `tarefas_estudo` recebe `fonte_conteudo`.
- `eventos_calendario_estudai` recebe `conteudo`, `semana_inicio` e `semana_fim`.

### AI

- Prompts de plano semanal, exercicios por tarefa, revisao por conteudo, simulado contextual e revisao semanal dominical foram endurecidos para JSON estrito.

### Planning

- Fallback semanal local respeita janela, disponibilidade e pesos de reforco.

### Calendar

- Calendario passa a refletir semanas geradas, sem depender de ano inteiro preenchido.

### Exercises

- Questoes permanecem vinculadas ao conteudo da tarefa e respostas sao atualizadas sem duplicacao.

### Simulations

- Simulados do plano exigem conteudo suficiente e nao sao recriados ao abrir.

### UX

- Dashboard e tarefas usam acoes contextuais mais claras.

### Known Issues

- Avaliacao IA de resposta aberta ainda esta preparada, mas nao finalizada.
- Edicao manual avancada de tarefas e remarcacao visual completa ficam para 0.0.5-alpha.

## [0.0.3-alpha] - 2026-05-17

### Added

- Migration `database/migrations/0003_estudai_annual_ai_core.sql`.
- Tabelas `plano_versoes`, `revisoes_semanais_ia`, `exercicios_planejados`, `respostas_exercicios_planejados`, `simulados_planejados`, `simulados_planejados_respostas` e `eventos_calendario_estudai`.
- Endpoint `api/calendario-estudai.php` com visoes de mes, ano, dia, eventos e atualizacao de status.
- Endpoint `api/exercicios-ia.php` com carregamento por tarefa/semana, geracao controlada, resposta e base para avaliacao aberta.
- Endpoint `api/simulados-planejados.php` com listar, carregar, gerar por tarefa, iniciar, responder e finalizar.
- Endpoint `api/revisao-semanal-ia.php` e script `api/cron_revisao_semanal_ia.php`.
- Tela de Calendario, tela de Exercicios e bloco de Simulados do Plano.
- Acoes contextuais nas tarefas: abrir estudo, fazer exercicios, revisar, iniciar simulado, criar resumo e ver atividade.

### Changed

- Versao do projeto atualizada para `0.0.3-alpha`.
- Service worker atualizado para cache `estudai-static-0.0.3-alpha`.
- Manifest atualizado para `0.0.3-alpha`.
- Onboarding agora coleta objetivo mais detalhado, ENEM com materias automaticas, horarios por bloco, foco, intensidade, obstaculos, niveis por materia e preferencias de exercicios/simulados.
- `api/plano-estudos.php` passa a aceitar `gerar_anual`, salvar versao do plano, criar eventos, pre-gerar exercicios da semana atual/proxima e criar simulados planejados.
- `api/estatisticas.php?action=estudai_geral` inclui progresso semanal, mensal, anual, exercicios IA, simulados planejados e materias com maior atraso/melhor desempenho.

### Fixed

- A interface traduz `custom` para `Atividade` e prioridades para rotulos amigaveis.
- Tarefas concluidas/reabertas sincronizam eventos do calendario quando a migration 0003 existe.

### Security

- Todos os novos endpoints de usuario exigem `requireLogin()`.
- Acoes POST criticas validam CSRF.
- Acessos de plano, tarefa, exercicio, simulado e calendario filtram por `usuario_id`.
- Geracao de plano anual, exercicios, simulados e revisao semanal manual usam rate limit simples.
- Cron automatico exige chamada local ou `ESTUDAI_CRON_TOKEN`.

### Database

- Migration 0003 e incremental, usa `CREATE TABLE IF NOT EXISTS` e nao apaga dados.
- Tarefas/itens/plano recebem colunas opcionais para escopo anual, conteudo, horario e metadata.
- Historico de planos passa por `plano_versoes`.

### AI

- Prompts novos para plano anual, exercicios, simulados planejados e revisao semanal.
- Fallback local cobre todas as chamadas novas.
- IA nao e chamada pelo frontend e a chave OpenRouter permanece no backend.

### Calendar

- Eventos do calendario sao persistidos em `eventos_calendario_estudai`.
- Calendario mensal tem filtros por tipo/status, painel de dia e visao anual resumida.

### Exercises

- Exercicios existentes sao reutilizados e nao recriados no reload.
- Respostas de multipla escolha sao corrigidas automaticamente.

### Simulations

- Simulados do plano sao vinculados a tarefa/plano e liberados por data.
- Respostas e finalizacao ficam em tabelas proprias sem quebrar simulados gerais.

### Known Issues

- Avaliacao IA de respostas abertas esta preparada, mas ainda retorna estado pendente.
- Ajuste semanal automatico aplica mudancas conservadoras em tarefas futuras; remarcacao visual completa fica para versao futura.
- Cron precisa ser configurado no servidor para domingo 00:00.
- CSRF ainda nao cobre todos os endpoints legados.

## [0.0.2-alpha] - 2026-05-17

### Added

- Endpoint `api/onboarding.php` com status, carregar e salvar perfil no banco.
- Endpoint `api/tarefas-estudo.php` com tarefas de hoje, semana, atrasadas, concluir e reabrir.
- Fluxo funcional de diagnostico do estudante com IA ou fallback local.
- Fluxo funcional de plano de estudos com persistencia, itens e tarefas.
- Action `estudai_geral` em `api/estatisticas.php`.
- Migration `database/migrations/0002_estudai_functional_core.sql`.
- Helper base de CSRF por sessao e envio automatico via `apiFetch`.
- Cards de progresso do nucleo funcional no dashboard.
- Secoes reais de Diagnostico, Plano, Rotina e Perfil de estudo.

### Changed

- Versao do frontend atualizada para `0.0.2-alpha`.
- Service worker atualizado para cache `estudai-static-0.0.2-alpha`.
- Onboarding local passou a ser fallback de leitura; banco e a fonte primaria.
- Prompts de diagnostico e plano passaram a exigir JSON estruturado.
- Plano mobile inicial foi substituido por plano salvo e agrupado por tarefas.
- Rotina mobile inicial foi substituida por tarefas vindas do banco.

### Fixed

- Diagnostico e plano nao dependem mais de payload sensivel enviado pelo frontend.
- Falhas de IA retornam fallback coerente em vez de quebrar a interface.
- Progresso basico passa a refletir tarefas concluidas no banco.

### Security

- Novos endpoints exigem `requireLogin()`.
- Acoes criticas novas validam CSRF por sessao.
- Chave OpenRouter continua restrita ao backend.
- Historico de IA registra apenas resumo minimo e resposta estruturada.
- Endpoints de IA usam rate limit simples por sessao/usuario.

### Database

- Novas/confirmadas tabelas: `estudo_perfis`, `onboarding_respostas`, `planos_estudo`, `plano_estudo_itens`, `tarefas_estudo`, `ia_historico` e `schema_migrations`.
- Migration 0002 usa `CREATE TABLE IF NOT EXISTS`, adiciona colunas ausentes e expande enums sem apagar dados.
- Planos ativos anteriores sao marcados como `substituido` ao gerar novo plano.

### AI

- Diagnostico retorna `perfil_resumido`, dificuldades, prioridades, estrategias e proximos passos.
- Plano retorna titulo, resumo, periodo, dias e tarefas com materia, tipo, tempo e prioridade.
- Fallback local cobre diagnostico e plano quando a IA ou a chave OpenRouter falham.

### Known Issues

- CSRF ainda nao foi aplicado a todos os endpoints legados.
- Migration 0002 deve ser aplicada antes de testar o nucleo funcional.
- Sem notificacoes push, background sync ou offline completo nesta versao.
- Plano ainda nao reajusta automaticamente a semana apos atrasos.

## [0.0.1-alpha] - 2026-05-15

### Adicionado

- Estrutura `src/` para estilos, paginas, configuracoes, servicos e assets.
- Documentacao em `docs/`.
- README raiz.
- Manifest PWA e service worker.
- Navegacao mobile inferior.
- Telas iniciais de Plano, Rotina e Perfil.
- Camada backend inicial para OpenRouter.
- `.env.example`.
- Design system em `assets/css/design-system.css`.
- Endpoints `api/ia.php`, `api/diagnostico.php` e `api/plano-estudos.php`.
- Onboarding local inicial para perfil de estudo.
- Migration incremental `database/migrations/0001_estudai_core_alpha.sql`.
- Documentacao de roadmap, seguranca, acessibilidade, onboarding, diagnostico e plano de estudos.

### Alterado

- CSS e JS foram extraidos de `index.html` e `app.html`.
- Visual mudou de amarelo/preto para off-white, azul escuro e neutros.
- Chamadas frontend passaram por `apiFetch()` e `apiEndpoint()`.
- Banco agora pode ler credenciais por variaveis de ambiente.
- Tema padrao de novas preferencias mudou para `light`.
- Manifest e service worker foram atualizados para a identidade EstudAI.
- OpenRouter foi centralizado em `config/openrouter.php`.
- Renderizacoes dinamicas no frontend receberam escape em pontos sensiveis.

### Removido

- Emojis visiveis da interface, substituidos por SVGs.

### Documentado

- Auditoria inicial.
- Levantamento mobile touch-first.
- PWA.
- OpenRouter.
- Banco de dados.
- Versao `0.0.1-alpha`.
- Estrategia de cache PWA.
- Checklist de validacao manual.
