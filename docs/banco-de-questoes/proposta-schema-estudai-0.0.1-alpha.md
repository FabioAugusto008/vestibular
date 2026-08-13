# Proposta de schema EstudAI - 0.0.1-alpha

Data: 2026-05-15

## Diretriz

Nao aplicar alteracoes destrutivas automaticamente. A migration `database/migrations/0001_estudai_core_alpha.sql` cria tabelas novas com `CREATE TABLE IF NOT EXISTS` e mantem o schema atual.

## Tabelas propostas

### `estudo_perfis`

Objetivo: guardar perfil consolidado do estudante. Campos: `usuario_id`, objetivo, data_prova, horas_dia, dias_semana_json, dificuldades_json, prioridades_json, preferencia_estudo, meta_semanal, notificacoes. Relacao: 1:1 com `usuarios`. Impacto: base para onboarding, IA e personalizacao. Prioridade: alta.

### `onboarding_respostas`

Objetivo: guardar respostas brutas por versao do onboarding. Campos: `usuario_id`, versao, respostas_json, concluido_em. Relacao: N:1 com `usuarios`. Impacto: auditoria e evolucao de perguntas. Prioridade: alta.

### `planos_estudo`

Objetivo: plano gerado manualmente ou por IA. Campos: usuario, titulo, origem, status, resumo, datas, plano_json. Relacao: N:1 com `usuarios`. Impacto: transforma o app em organizador de estudos. Prioridade: alta.

### `plano_estudo_itens`

Objetivo: blocos do plano por dia/materia. Campos: plano_id, usuario_id, dia_semana, data_prevista, materia, tipo_atividade, tempo_estimado, status. Relacao: N:1 com `planos_estudo`. Impacto: renderizacao e acompanhamento. Prioridade: alta.

### `tarefas_estudo`

Objetivo: tarefas acionaveis do estudante. Campos: usuario_id, plano_id, titulo, materia, tipo, data_prevista, tempo_estimado, status. Relacao: N:1 com `usuarios`, opcional com `planos_estudo`. Impacto: rotina diaria e checklist sincronizado. Prioridade: alta.

### `rotina_semanal`

Objetivo: disponibilidade recorrente. Campos: usuario_id, dia_semana, hora_inicio, hora_fim, foco, ativo. Relacao: N:1 com `usuarios`. Impacto: base para planos realistas. Prioridade: media.

### `sessoes_estudo`

Objetivo: registrar blocos reais de estudo. Campos: usuario_id, tarefa_id, materia, tipo_atividade, iniciou_em, finalizou_em, duracao_seg, observacoes. Relacao: N:1 com `usuarios`. Impacto: analise de constancia e produtividade. Prioridade: media.

### `ia_historico`

Objetivo: registrar uso de IA. Campos: usuario_id, provider, modelo, tipo, entrada_resumo, resposta_json, status, erro, tokens. Relacao: N:1 com `usuarios`. Impacto: auditoria, custos e melhoria de prompts. Prioridade: media.

### `preferencias_estudo`

Objetivo: preferencias especificas da experiencia EstudAI. Campos: usuario_id, densidade_interface, ultima_secao, pwa_instalado, timezone, preferencias_json. Relacao: 1:1 com `usuarios`. Impacto: personalizacao sem alterar tabela legada. Prioridade: baixa/media.

### `notificacoes_usuario`

Objetivo: lembretes e mensagens internas. Campos: usuario_id, tipo, titulo, mensagem, agendada_para, enviada_em, lida_em, status. Relacao: N:1 com `usuarios`. Impacto: lembretes e metas. Prioridade: media.

### `revisoes_programadas`

Objetivo: agenda de revisao espacada. Campos: usuario_id, questao_id, materia, assunto, data_revisao, intervalo_dias, status. Relacao: N:1 com `usuarios`; opcional com `questoes`. Impacto: revisao inteligente. Prioridade: alta.
