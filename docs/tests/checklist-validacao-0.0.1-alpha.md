# Checklist de validacao - 0.0.1-alpha

Data: 2026-05-15

## Autenticacao

- Login com credenciais validas.
- Login com senha invalida.
- Cadastro com e-mail valido.
- Cadastro com e-mail duplicado.
- Logout.

## App

- Dashboard carrega estatisticas.
- Questoes do dia carregam.
- Resposta de questao salva.
- Finalizacao do dia funciona.
- Revisao carrega categorias.
- Simulados listam, iniciam e finalizam.
- Estatisticas carregam.
- Metas carregam e salvam.
- Anotacoes salvam e listam.
- Configuracoes abrem e salvam preferencias.

## Onboarding e IA

- Modal de onboarding abre no primeiro acesso.
- Perfil local salva em `localStorage`.
- `api/diagnostico.php` exige login e POST.
- `api/plano-estudos.php` exige login e POST.
- Fallback funciona sem chave OpenRouter.
- Chave OpenRouter nao aparece no frontend.

## Mobile

- Navegacao inferior aparece em telas pequenas.
- Botoes tem toque confortavel.
- Cards nao extrapolam largura.
- Modais cabem na tela.

## PWA

- Manifest carrega.
- Service worker registra em HTTP/HTTPS.
- Cache versionado atualiza.
- APIs nao sao cacheadas.

## Banco

- `database/schema.sql` importa em ambiente limpo.
- Migration `0001_estudai_core_alpha.sql` roda sem apagar dados.
- Tabelas novas possuem FKs para `usuarios`.

## Acessibilidade

- Foco visivel em inputs e botoes.
- Labels nos campos de login/cadastro.
- Alertas anunciaveis.
- Contraste basico adequado.

## Validacao tecnica

- Rodar `php -l` nos arquivos PHP alterados/criados.
- Verificar console do navegador para erros JS.
- Testar carregamento de `index.html` e `app.html`.

## Executado nesta etapa

- `C:\xampp\php\php.exe -l` em todos os arquivos PHP: sem erros de sintaxe.
- `node --check` em `src/pages/app.js`, `src/pages/login.js`, `assets/js/ia.js`, `src/config/api.js` e `src/services/http.js`: sem erros de sintaxe.
- `manifest.webmanifest` validado com `JSON.parse`: OK.
- Servidor PHP local em `127.0.0.1:8089`: `index.html`, `app.html` e `manifest.webmanifest` responderam HTTP 200.
- Observacao: `php` nao estava no PATH do PowerShell; foi usado o binario do XAMPP.
