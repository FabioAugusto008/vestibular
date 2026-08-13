# Onboarding - 0.0.1-alpha

Data: 2026-05-15

## Objetivo

Capturar o perfil inicial do estudante para que o EstudAI consiga gerar diagnostico, rotina e plano de estudos personalizados sem depender de perguntas repetidas.

## Perguntas necessarias

- Qual seu objetivo principal: ENEM, vestibular, prova escolar, concurso ou outro?
- Qual sua data de prova ou prazo?
- Quantas horas por dia voce pode estudar?
- Quais dias da semana voce pode estudar?
- Quais materias voce tem mais dificuldade?
- Quais materias sao prioridade?
- Voce prefere teoria, questoes, revisao, simulados ou formato misto?
- Qual sua meta semanal?
- Voce quer lembretes ou notificacoes?

## Dados que precisam ser salvos

- `usuario_id`
- objetivo
- data de prova
- horas por dia
- dias disponiveis
- dificuldades
- prioridades
- preferencia de estudo
- meta semanal
- aceite de notificacoes
- data de conclusao e versao do onboarding

## Telas necessarias

- Modal inicial nao obrigatorio no primeiro acesso.
- Tela/area de perfil de estudo para edicao posterior.
- Resumo do perfil no dashboard.
- Futuramente, tela de diagnostico e plano gerado.

## Impactos no banco

Proposta na migration `database/migrations/0001_estudai_core_alpha.sql`:

- `estudo_perfis`
- `onboarding_respostas`
- `preferencias_estudo`

Nesta etapa, o onboarding foi iniciado de forma local com `localStorage`, sem quebrar o fluxo atual.

## Impactos na IA

O perfil vira entrada para:

- diagnostico do estudante;
- geracao de plano de estudos;
- ajuste de revisoes;
- recomendacao de proximas tarefas.

## Fluxo recomendado

1. Usuario faz login.
2. Se nao houver perfil local ou salvo no banco, o modal de onboarding aparece.
3. Usuario pode pular sem bloquear o app.
4. Ao salvar, o dashboard exibe um resumo.
5. Futuramente, o backend persiste os dados e chama o endpoint de diagnostico.
