# Plano de estudos com IA - 0.0.1-alpha

Data: 2026-05-15

## Entrada esperada

- Perfil do onboarding.
- Diagnostico do estudante.
- Dias e horas disponiveis.
- Prazo/data de prova.
- Materias prioritarias.
- Dificuldades.
- Preferencia de estudo.
- Dados futuros de desempenho.

## Saida esperada

- resumo do plano;
- dias de estudo;
- materias por dia;
- tempo estimado;
- tipo de atividade;
- revisao programada;
- simulados;
- tarefas de curto prazo;
- observacoes.

## Formato ideal

JSON estruturado com lista de dias, tarefas e observacoes. Isso facilita salvar no banco, renderizar no frontend e atualizar partes do plano.

## Como salvar

Proposta:

- `planos_estudo` guarda cabecalho, origem, status, resumo e JSON completo.
- `plano_estudo_itens` guarda blocos por dia/tarefa.
- `tarefas_estudo` permite acompanhamento diario.

## Como atualizar

- Recalcular semanalmente.
- Ajustar ao concluir ou adiar tarefas.
- Usar desempenho em questoes, revisao e simulados.
- Manter historico de versoes em vez de sobrescrever sem rastreio.

## Adaptacao por desempenho

- Baixa taxa de acerto aumenta revisao e teoria.
- Boa taxa de acerto aumenta questoes e simulados.
- Falta de constancia reduz carga e foca blocos menores.

## Erro da IA

- Retornar fallback local simples.
- Mostrar aviso claro.
- Nao apagar plano anterior.
- Registrar falha em historico futuro.

## Evitar planos irreais

- Respeitar horas por dia.
- Limitar tarefas por bloco.
- Reservar tempo de revisao.
- Nao preencher todos os dias se o estudante nao informou disponibilidade.

## Implementacao inicial

- Endpoint: `api/plano-estudos.php`.
- Servico: `estudaiGerarPlanoEstudos()`.
- Prompt: `estudaiPlanoEstudosPrompt()`.
- Fallback local em JSON.
