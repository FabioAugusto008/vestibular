# Checklist de Validacao 0.0.4-alpha

## Onboarding

- [ ] Usuario novo nao acessa o app sem onboarding.
- [ ] Formulario nao tem botao pular/depois.
- [ ] ENEM mostra lingua estrangeira.
- [ ] Lingua estrangeira nao aparece fora do ENEM.
- [ ] ENEM carrega materias automaticamente.
- [ ] Usuario nao escolhe materia por materia no ENEM.
- [ ] Horarios usam campos `time`.
- [ ] Salvar sem horario valido falha.
- [ ] Salvar com dados validos libera app.

## Plano

- [ ] Gerar plano semanal.
- [ ] Plano comeca hoje ou no proximo dia disponivel.
- [ ] Plano nao gera tarefa fora da disponibilidade.
- [ ] Plano nao gera tarefa depois da prova.
- [ ] Plano nao gera tarefa antes de hoje.
- [ ] Plano usa materias do modo escolhido.
- [ ] Reforco alto aumenta frequencia sem excluir outras materias.

## Calendario

- [ ] Calendario mostra tarefas corretas.
- [ ] Clicar no dia mostra horario, materia, conteudo e tipo.
- [ ] Nao aparecem datas aleatorias.
- [ ] Nao aparece `custom` na interface.

## Exercicios

- [ ] Tarefa de exercicios abre questoes.
- [ ] Questoes sao do conteudo da tarefa.
- [ ] Recarregar nao recria questoes.
- [ ] Responder salva resultado.
- [ ] Explicacao aparece apos responder.

## Revisoes

- [ ] Tarefa de revisao abre revisao por conteudo.
- [ ] Revisao usa conteudo estudado/planejado.
- [ ] Revisao nao gera materia aleatoria.
- [ ] Marcar revisao concluida conclui a tarefa.

## Simulados

- [ ] Simulado usa conteudos da semana ou tarefa.
- [ ] Simulado bloqueado nao inicia.
- [ ] Simulado liberado inicia.
- [ ] Finalizar calcula resultado.

## Dashboard

- [ ] Sem onboarding mostra acao de formulario.
- [ ] Com onboarding sem plano mostra gerar plano semanal.
- [ ] Com plano mostra proxima acao.
- [ ] Tarefas atrasadas aparecem como alerta/contador.

## Revisao semanal

- [ ] Executar `api/revisao-semanal-ia.php?action=executar_manual` em dev.
- [ ] Analisa semana encerrada.
- [ ] Gera proxima semana.
- [ ] Nao altera tarefas concluidas.
- [ ] Registra historico.

## Sintaxe

- [ ] `php -l` nos PHP alterados/criados.
- [ ] `node --check` nos JS alterados/criados.
- [ ] Console do navegador sem erros graves.
