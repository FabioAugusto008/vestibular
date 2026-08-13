# Levantamento OpenRouter - 0.0.1-alpha

Data: 2026-05-15

Fontes consultadas:

- https://openrouter.ai/docs/api/api-reference/chat/send-chat-completion-request
- https://openrouter.ai/docs/api/reference/authentication

## Estado atual

No momento do levantamento inicial, nao foram encontradas funcoes de IA ativas no projeto. A etapa 2 passou a criar endpoints internos em PHP, mantendo a chave fora do frontend.

## Arquivos afetados nesta preparacao

- `.env.example`
- `src/config/ai.php`
- `src/services/ai/openrouterClient.php`
- `src/services/ai/prompts.php`
- `docs/openrouter/levantamento-openrouter-0.0.1-alpha.md`

## Funcoes atuais afetadas

Na etapa inicial, nenhuma funcao existente foi ligada ao OpenRouter. Na etapa 2, a integracao foi isolada em endpoints internos novos para evitar quebrar login, banco, questoes, revisao ou simulados.

## Formato atual das chamadas

Nao existe formato atual de IA. O frontend chama somente endpoints locais via `apiFetch()`.

## Formato recomendado para OpenRouter

Endpoint:

```text
POST https://openrouter.ai/api/v1/chat/completions
```

Headers:

```text
Authorization: Bearer <OPENROUTER_API_KEY>
Content-Type: application/json
HTTP-Referer: <OPENROUTER_SITE_URL>
X-OpenRouter-Title: <OPENROUTER_SITE_NAME>
```

Body minimo:

```json
{
  "model": "openai/gpt-5.2",
  "messages": [
    { "role": "system", "content": "Voce e um assistente educacional." },
    { "role": "user", "content": "Explique esta questao." }
  ],
  "temperature": 0.4,
  "max_completion_tokens": 900
}
```

Resposta esperada:

```text
choices[0].message.content
```

## Variaveis de ambiente necessarias

- `OPENROUTER_API_KEY`
- `OPENROUTER_BASE_URL`
- `OPENROUTER_MODEL`
- `OPENROUTER_SITE_URL`
- `OPENROUTER_SITE_NAME`

## Riscos de seguranca

- A chave OpenRouter nunca deve ficar em HTML, CSS ou JS publico.
- O frontend nao deve chamar OpenRouter diretamente.
- Prompts com dados sensiveis de usuario precisam ser minimizados.
- Logs de erro nao devem imprimir API key ou payload completo com informacoes sensiveis.
- Rate limit e custo precisam ser tratados antes de expor funcoes de IA aos usuarios.

## O que precisa ficar no backend

- API key.
- Endpoint OpenRouter.
- Selecionador de modelo.
- Tratamento de erro e status HTTP.
- Sanitizacao de entrada.
- Controle de custo/rate limit.
- Logs tecnicos sem segredos.

## O que nunca deve ficar exposto no frontend

- `OPENROUTER_API_KEY`.
- Headers de autenticacao.
- Fallbacks com chaves.
- Prompt system completo se ele contiver regras internas sensiveis.
- Dados brutos desnecessarios de usuario.

## Camada criada

`src/config/ai.php` centraliza:

- provider;
- base URL;
- modelo;
- site URL;
- site name;
- timeout;
- API key via ambiente.

`src/services/ai/openrouterClient.php` centraliza:

- montagem do payload;
- headers;
- chamada cURL;
- tratamento de erro;
- retorno JSON;
- helper para extrair `choices[0].message.content`.

`src/services/ai/prompts.php` centraliza prompts iniciais para futuras funcoes educacionais.

## Proposta de proximas funcoes de IA

- Explicar uma questao respondida incorretamente.
- Gerar plano de estudo semanal com base no historico.
- Sugerir revisoes prioritarias por materia/dificuldade.
- Resumir anotacoes do usuario.

## Pendencias antes de ativar

- Definir limites por usuario.
- Criar endpoint PHP proprio, por exemplo `api/ia.php`.
- Registrar uso em tabela de auditoria/custo.
- Criar mensagens de erro amigaveis.
- Testar modelos e custos no OpenRouter antes de liberar.
