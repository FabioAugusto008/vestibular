# Plano de estudos com IA - 0.0.2-alpha

## Fluxo

1. Usuario salva o perfil.
2. Frontend chama `POST api/plano-estudos.php?action=gerar`.
3. Backend carrega perfil e ultimo diagnostico, quando existir.
4. IA gera plano semanal estruturado ou fallback local assume.
5. Plano ativo anterior e marcado como `substituido`.
6. Novo plano e salvo em `planos_estudo`.
7. Dias/tarefas viram registros em `plano_estudo_itens` e `tarefas_estudo`.

## Formato esperado da IA

```json
{
  "titulo": "Plano semanal personalizado",
  "resumo": "...",
  "data_inicio": "YYYY-MM-DD",
  "data_fim": "YYYY-MM-DD",
  "dias": [
    {
      "data": "YYYY-MM-DD",
      "dia_semana": "Segunda-feira",
      "tarefas": [
        {
          "titulo": "...",
          "materia": "...",
          "tipo": "misto",
          "descricao": "...",
          "tempo_estimado": 40,
          "prioridade": "media"
        }
      ]
    }
  ],
  "observacoes": []
}
```

## Fallback local

O fallback considera dias disponiveis, prioridades, dificuldades, preferencia de estudo, horas por dia e meta semanal. Ele gera um plano simples de ate 7 dias, sem chamar OpenRouter.

## Limites

- Ainda nao ha remarcacao automatica.
- Ainda nao ha edicao detalhada de plano pela interface.
- Exportacao e integracao com calendario ficam para versoes futuras.
