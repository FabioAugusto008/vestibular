# Levantamento de seguranca - 0.0.1-alpha

Data: 2026-05-15

## Login e cadastro

- Usa `password_hash` e `password_verify`.
- Usa prepared statements.
- Login agora regenera o ID de sessao.
- Cadastro valida e-mail, senha minima e tamanho do nome.
- Pendente: CSRF token e politica de senha mais completa.

## Sessoes e logout

- Sessao centralizada em `config/helpers.php`.
- Cookie configurado com `HttpOnly` e `SameSite=Lax`.
- `Secure` e ativado quando HTTPS estiver ativo.
- Logout usa `session_destroy`.

## Endpoints protegidos

- Endpoints principais usam `requireLogin()`.
- Novos endpoints de IA tambem exigem login.
- Pendente: permissao granular por recurso alem de `usuario_id`.

## SQL Injection

- Endpoints revisados usam `prepare()` e parametros.
- Queries dinamicas devem continuar restritas a whitelists.

## Validacao de entrada

- Novos endpoints JSON validam payload, tamanho e tipo.
- `questoes`, `revisao`, `simulados` validam IDs e alternativas.
- Pendente: padronizar validadores por tipo.

## Erros PHP

- APIs retornam mensagens genericas em pontos sensiveis.
- Recomendacao: em producao, `display_errors=Off` e logs no servidor.

## CSRF

- Ainda nao implementado.
- Risco: acoes autenticadas via POST podem ser acionadas por outro site se o navegador enviar cookie.
- Proxima etapa: token por sessao em formularios e headers AJAX.

## Variaveis de ambiente e OpenRouter

- `.env.example` documenta variaveis.
- Chave OpenRouter fica somente no backend.
- `assets/js/ia.js` chama endpoints internos, nunca OpenRouter diretamente.

## AJAX e XSS

- `apiFetch()` envia `X-Requested-With: XMLHttpRequest`, permitindo respostas JSON em sessao expirada.
- Parte das renderizacoes com `innerHTML` foi protegida com escape no frontend.
- Recomendacao: continuar preferindo `textContent` para texto dinamico.

## Rate limit

- Novos endpoints de IA usam limite simples por sessao.
- Proxima etapa: rate limit por usuario/IP no banco ou cache.
