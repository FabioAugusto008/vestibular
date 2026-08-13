# Integracao OpenRouter - 0.0.1-alpha

Data: 2026-05-15

## Arquitetura segura

- O frontend nunca recebe a chave da API.
- O navegador chama somente endpoints internos em `api/`.
- O PHP chama o OpenRouter.
- A configuracao fica centralizada em `config/openrouter.php`.
- O modelo e configuravel por variavel de ambiente.
- Ha timeout, validacao basica e limite simples por sessao.

## Variaveis

```env
OPENROUTER_API_KEY=
OPENROUTER_MODEL=openai/gpt-5.2
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_SITE_URL=http://localhost/vestibular
OPENROUTER_SITE_NAME=EstudAI
OPENROUTER_TIMEOUT_SECONDS=45
```

## Arquivos

- `config/openrouter.php`
- `src/services/ai/openrouterClient.php`
- `src/services/ai/prompts.php`
- `src/services/ai/estudaiService.php`
- `api/ia.php`
- `api/diagnostico.php`
- `api/plano-estudos.php`
- `assets/js/ia.js`

## Tratamento de erro

- Falha generica no endpoint base retorna 503 sem vazar detalhes.
- Diagnostico e plano usam fallback local.
- Payload invalido retorna 400.
- Rate limit retorna 429.

## Cuidados

- Nao chamar OpenRouter direto do navegador.
- Nao commitar chave real.
- Nao cachear endpoints de IA no service worker.
- Registrar historico futuramente sem dados sensiveis.

## Fontes oficiais

- https://openrouter.ai/docs
- https://openrouter.ai/docs/api-reference/chat-completion
