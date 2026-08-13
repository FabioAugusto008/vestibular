# Diagnostico do estudante com IA - 0.0.1-alpha

Data: 2026-05-15

## Dados usados

- Objetivo do estudante.
- Data de prova ou prazo.
- Horas por dia.
- Dias disponiveis.
- Materias com dificuldade.
- Materias prioritarias.
- Preferencia de estudo.
- Meta semanal.
- Historico futuro de questoes, revisoes, simulados e metas.

## Como a IA deve analisar

A IA deve cruzar disponibilidade, objetivo, prazo e desempenho para identificar gargalos e sugerir foco. A resposta precisa ser especifica, acionavel e coerente com o tempo real do estudante.

## Respostas esperadas

- perfil resumido do estudante;
- principais dificuldades;
- materias prioritarias;
- sugestao de rotina;
- estrategia de revisao;
- proximos passos.

## Conexao com plano de estudos

O diagnostico deve alimentar o plano de estudos. Materias prioritarias, tempo disponivel e dificuldades definem os blocos de teoria, questoes, revisao e simulado.

## Riscos de resposta generica

- Onboarding incompleto.
- Falta de historico real de desempenho.
- Prompt permissivo demais.
- Modelo sem formato de saida estruturado.

Mitigacao: exigir JSON, limitar escopo, usar fallback local e pedir mais dados quando faltarem informacoes.

## Cuidados com privacidade

- Nao enviar senha, e-mail ou dados sensiveis desnecessarios para a IA.
- Nao expor chave OpenRouter no frontend.
- Registrar historico de IA sem prompts completos sensiveis quando possivel.
- Permitir revisao e exclusao futura dos dados de perfil.

## Implementacao inicial

- Endpoint: `api/diagnostico.php`.
- Servico: `src/services/ai/estudaiService.php`.
- Prompt: `estudaiDiagnosticoPrompt()` em `src/services/ai/prompts.php`.
- Fallback local caso a IA falhe ou a chave nao esteja configurada.
