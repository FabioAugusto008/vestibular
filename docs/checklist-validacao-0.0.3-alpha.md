# Checklist de validacao 0.0.3-alpha

## Fluxo principal

- Login funciona.
- Cadastro funciona.
- Logout funciona.
- Usuario novo ve aviso para preencher perfil.
- Onboarding expandido abre em desktop e mobile.
- Objetivo ENEM seleciona materias base automaticamente no payload.
- Horarios por bloco sao salvos e rejeitam formato invalido.
- Perfil salvo permanece apos recarregar.
- Diagnostico com IA/fallback continua funcionando.
- Gerar plano anual cria plano ativo, tarefas, eventos, exercicios e simulados planejados.
- Recarregar pagina nao recria plano automaticamente.
- Dashboard mostra proxima acao e progresso basico.

## Calendario

- Abrir Calendario mostra mes atual.
- Mes anterior/proximo funcionam.
- Botao Hoje volta para a data atual.
- Visao anual mostra quantidade por mes.
- Filtros por teoria, exercicios, revisao, simulado, resumo, concluidas e atrasadas funcionam.
- Clicar em um dia mostra tarefas do dia.
- Botoes contextuais abrem a area correta.
- Mobile mostra lista confortavel sem tabela larga.

## Exercicios

- Abrir tarefa tipo exercicios carrega lote existente.
- Se nao existir lote, gera e salva.
- Recarregar nao recria lote.
- Responder multipla escolha salva resposta.
- Correcao automatica mostra acerto/erro.
- Gerar novamente arquiva lote antigo e cria novo.

## Simulados do Plano

- Simulado futuro aparece bloqueado.
- Simulado com data atual/passada fica liberado.
- Iniciar simulado muda status para iniciado.
- Responder questoes salva respostas.
- Finalizar calcula acertos, erros e percentual.
- Simulados gerais antigos continuam funcionando.

## Revisao semanal

- `api/revisao-semanal-ia.php?action=status` retorna ultima revisao.
- Execucao manual em dev salva revisao.
- Execucao manual cria versao em `plano_versoes`.
- Ajustes afetam apenas tarefas futuras nao concluidas.
- Cron fora de domingo retorna sem executar, exceto `force=1` local.
- Cron com token invalido falha fora do ambiente local.

## Estatisticas

- `api/estatisticas.php?action=estudai_geral` retorna progresso semanal, mensal e anual.
- Retorna exercicios respondidos, taxa de acerto e simulados planejados.
- Dashboard atualiza apos concluir tarefa, responder exercicio e finalizar simulado.

## Seguranca

- Usuario A nao acessa calendario, tarefa, exercicio ou simulado do usuario B.
- POST sem CSRF falha nos endpoints novos.
- Payloads grandes sao rejeitados.
- Chave OpenRouter nao aparece no frontend.
- Erro de IA usa fallback sem quebrar interface.

## PWA

- `sw.js` usa cache `estudai-static-0.0.3-alpha`.
- `/api/` nao e cacheado.
- Caches antigos `estudai-static-*` sao removidos no activate.

## Sintaxe

- Rodar `php -l` nos PHP alterados/criados.
- Rodar `node --check` nos JS alterados/criados.
- Verificar console do navegador em login, dashboard, calendario, exercicios e simulados.
