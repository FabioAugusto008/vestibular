# Changelog

O changelog principal tambem esta em `docs/CHANGELOG.md`.

## [0.1.0-alpha] - 2026-05-28

Nome: Launch Core

### Added

- Migration `database/migrations/0005_estudai_launch_core.sql`.
- Endpoint `server/api/redacao.php` para Redacao ENEM com historico e analise orientativa nao oficial.
- Action `plano-estudos.php?action=replanejar_semana` com motivo, contexto da semana, tarefas atrasadas e desempenho.
- Novas actions em `tarefas-estudo.php`: `editar`, `adiar`, `remarcar`, `cancelar`, `em_andamento`, `iniciar_tempo`, `pausar_tempo`, `finalizar_tempo` e `recentes`.
- `app/public/offline.html` e cache PWA `0.1.0-alpha-launch-core`.

### Changed

- Dashboard passou a exibir proxima acao, resumo semanal, pendencias, atrasos, tempo planejado e atalhos.
- Plano semanal exibe estrategia, progresso, agrupamento por dia, alertas e botao de replanejamento.
- Rotina separa hoje, atrasadas, proximas e concluidas recentemente.
- Exercicios exibem feedback, explicacao cadastrada e motivo opcional apos erro.
- Revisoes e simulados ganharam mensagens e estados mais claros.
- Estatisticas exibem questoes, acertos, erros, percentual, tarefas, simulados, streak e tempo.
- Conquistas ganharam XP/nivel discreto e novos criterios preparados.

### Security

- CSRF aplicado aos POSTs legados de `questoes.php`, `revisao.php`, `simulados.php`, `metas.php`, `preferencias.php`, `anotacoes.php` e `conquistas.php`.
- Rate limit simples por sessao/IP em login, cadastro, diagnostico, plano, replanejamento, IA, redacao e revisao semanal manual.

### Database

- `tarefas_estudo.tempo_real_min`.
- `respostas_exercicios_planejados.motivo_erro`.
- Nova tabela `redacoes_enem`.
- Novas conquistas discretas para semana completa, redacao, 100 questoes, revisao de erros e plano semanal.

### AI

- IA permanece restrita a diagnostico, planejamento, replanejamento, revisao, analise de desempenho, organizacao e analise orientativa de redacao.
- Exercicios, revisoes e simulados continuam usando somente questoes aprovadas do banco.

### Known Issues

- O controle de tempo real depende da migration `0005`; sem ela, o fluxo inicia tarefa defensivamente, mas nao consolida `tempo_real_min`.
- A analise de redacao e estimativa nao oficial e nao substitui corretor humano.
- Nao ha testes automatizados; validacao foi feita por lint PHP disponivel no Laragon.

## [0.0.4-alpha] - 2026-05-17

### Added

- Migration `database/migrations/0004_estudai_weekly_flow_fix.sql`.
- Endpoint `api/revisoes-ia.php` para revisoes por tarefa/conteudo.
- Action `api/plano-estudos.php?action=gerar_semana`.
- Controle `planejamento_semanal_controle` para semana ativa.

### Changed

- Plano semanal passa a ser o fluxo principal; plano anual permanece legado.
- Onboarding agora e obrigatorio, em etapas, sem botao de pular.
- Horarios passaram de texto livre para blocos com `input type="time"`.

### Fixed

- Validacao bloqueia planos antes de hoje, depois da prova, fora da janela semanal ou fora da disponibilidade.
- ENEM usa grade fixa e lingua estrangeira somente nesse modo.
- Reforcos substituem prioridades/dificuldades separadas.

### Security

- POSTs novos seguem `requireLogin()`, CSRF, filtro por `usuario_id` e rate limit nas acoes IA.
- OpenRouter segue restrito ao backend.

### Database

- Campos novos em `estudo_perfis`, `planos_estudo`, `tarefas_estudo` e `eventos_calendario_estudai`.
- Novas tabelas `planejamento_semanal_controle` e `revisoes_conteudo_ia`.

### AI

- Prompts atualizados para diagnostico com onboarding novo, plano semanal, exercicios por tarefa, revisao por conteudo, simulado contextual e revisao dominical.

### Planning

- Fallback semanal respeita materias base, reforcos, disponibilidade, janela de 7 dias e data limite.

### Calendar

- Eventos recebem conteudo e semana, derivados das tarefas validadas.

### Exercises

- Exercicios continuam salvos por tarefa e deixam de duplicar resposta ao atualizar.

### Simulations

- Simulados podem ser gerados por tarefa ou por semana, apenas com conteudo suficiente.

### UX

- Dashboard passa a destacar a proxima acao: preencher perfil, gerar plano semanal ou executar a tarefa atual.

### Known Issues

- Avaliacao IA de respostas abertas continua pendente.
- Editor manual fino de tarefas fica para proxima versao.

## [0.0.3-alpha] - 2026-05-17

### Added

- Plano anual inteligente, calendario mensal/anual, exercicios planejados, simulados do plano e revisao semanal IA.
- Migration `database/migrations/0003_estudai_annual_ai_core.sql`.
- Endpoints `api/calendario-estudai.php`, `api/exercicios-ia.php`, `api/simulados-planejados.php`, `api/revisao-semanal-ia.php` e `api/cron_revisao_semanal_ia.php`.

### Changed

- Onboarding evoluido para objetivo ENEM, horarios por bloco, intensidade, obstaculos, niveis por materia e preferencias de simulados/exercicios.
- Plano principal da interface agora gera o plano anual e cria eventos, tarefas, exercicios e simulados vinculados.

### Fixed

- Tipos tecnicos como `custom` deixam de aparecer para o usuario, sendo exibidos como `Atividade`.

### Security

- Novos endpoints filtram por `usuario_id`, validam CSRF em POST critico e tratam erros de IA no backend.

### Database

- Novas tabelas de versoes de plano, revisoes semanais, exercicios, respostas, simulados planejados e eventos de calendario.

### AI

- IA passa a participar de plano anual e revisao semanal; exercicios e simulados planejados devem usar base de questoes.

### Calendar

- Calendario mensal/anual usa eventos persistidos derivados do plano e sincroniza tarefas quando necessario.

### Exercises

- Exercicios sao salvos por tarefa/semana e reutilizados ao recarregar.

### Simulations

- Simulados planejados ficam bloqueados ate a data de liberacao e salvam respostas/resultado.

### Known Issues

- Cron dominical ainda depende de configuracao externa do servidor.
- Analise IA de respostas abertas e remarcacao visual refinada ficam para proximas versoes.

## [0.0.2-alpha] - 2026-05-17

### Added

- Onboarding persistido, diagnostico por IA, plano salvo, tarefas reais e progresso basico.
- Migration `database/migrations/0002_estudai_functional_core.sql`.
- Endpoints `api/onboarding.php` e `api/tarefas-estudo.php`.

### Changed

- Versao atualizada para `0.0.2-alpha`.
- PWA atualizado para cache `estudai-static-0.0.2-alpha`.

### Security

- CSRF base em acoes criticas novas.
- OpenRouter permanece somente no backend.

### Known Issues

- CSRF ainda nao cobre todos os endpoints legados.
- Notificacoes push e offline completo ficam para versoes futuras.
