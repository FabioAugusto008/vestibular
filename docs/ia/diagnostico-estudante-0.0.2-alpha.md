# Diagnostico do estudante - 0.0.2-alpha

## Fluxo

1. Usuario autenticado salva o perfil de estudo.
2. Frontend chama `POST api/diagnostico.php?action=gerar`.
3. Backend carrega `estudo_perfis` pelo `usuario_id`.
4. Backend chama OpenRouter com prompt JSON estruturado.
5. Se IA falhar ou chave nao existir, fallback local gera diagnostico coerente.
6. Resultado e registrado em `ia_historico`.

## Formato de retorno

```json
{
  "ok": true,
  "origem": "ia",
  "diagnostico": {
    "perfil_resumido": "...",
    "principais_dificuldades": [],
    "materias_prioritarias": [],
    "estrategia_recomendada": "...",
    "rotina_sugerida": "...",
    "estrategia_revisao": "...",
    "proximos_passos": []
  }
}
```

## Cuidados

- Nao envia e-mail, senha ou chave ao modelo.
- Nao promete diagnostico psicologico, medico ou clinico.
- Nao expõe OpenRouter no frontend.
- Usa rate limit simples por sessao/usuario.
