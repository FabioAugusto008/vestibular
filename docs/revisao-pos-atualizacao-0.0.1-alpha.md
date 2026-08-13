# Revisao pos-atualizacao - 0.0.1-alpha

Data: 2026-05-15

## Resumo do estado atual

O EstudAI esta organizado como aplicacao PHP + HTML/CSS/JS puro, sem framework frontend e sem pipeline de build. A etapa anterior extraiu CSS/JS para `src/`, criou documentacao inicial em `docs/`, adicionou PWA basico e preparou uma camada inicial de OpenRouter sem uso direto pelo navegador.

## Concluido na etapa anterior

- `index.html` e `app.html` passaram a consumir CSS e JS externos.
- Estrutura `src/` criada para estilos, paginas, configuracoes, servicos e assets.
- README, changelog, auditoria inicial e docs de mobile/PWA/OpenRouter/banco criados.
- `manifest.webmanifest`, `sw.js` e registro de service worker adicionados.
- Visual inicial migrado para off-white, superficies claras e azul escuro.
- Navegacao mobile inferior e telas iniciais de plano, rotina e perfil foram iniciadas.
- Camada backend inicial para OpenRouter foi criada em `src/services/ai`.

## Possiveis problemas encontrados

- A base ainda tinha tokens visuais duplicados em `login.css` e `app.css`, sem arquivo de design system compartilhado.
- A camada OpenRouter ainda nao possuia endpoints internos publicos do app.
- O onboarding existia apenas como plano futuro.
- Algumas renderizacoes com `innerHTML` recebiam dados de API e precisavam de escape no frontend.
- CSRF ainda nao esta implementado para formularios criticos.
- O banco principal segue com nome legado `vestibular_estudos`, mantido por seguranca para nao quebrar ambientes existentes.

## Riscos tecnicos

- Reexecutar `database/schema.sql` pode duplicar seeds sem controle completo.
- O service worker precisa de versionamento em toda entrega para evitar cache antigo.
- IA sem rate limit pode gerar abuso de custo.
- Planos gerados por IA podem ser genericos se o onboarding estiver incompleto.
- `localStorage` nao sincroniza onboarding entre dispositivos.

## Pendencias

- Persistir onboarding e planos no banco.
- Criar CSRF token nos formularios de login/cadastro e acoes autenticadas.
- Adicionar historico real de chamadas de IA em `ia_historico`.
- Criar icones PNG para PWA.
- Criar testes automatizados ou ao menos smoke tests de API.

## Validacao executada

- Sintaxe PHP validada com `C:\xampp\php\php.exe -l` em todos os arquivos PHP.
- Sintaxe JS validada com `node --check` nos scripts alterados/criados.
- `manifest.webmanifest` validado com `JSON.parse`.
- Servidor PHP local em `127.0.0.1:8089` respondeu HTTP 200 para `index.html`, `app.html` e `manifest.webmanifest`.

## Plano da segunda etapa

- Padronizar produto como EstudAI em interface, README, manifest e docs.
- Criar `assets/css/design-system.css`.
- Refinar desktop/mobile sem trocar a stack.
- Implementar onboarding local nao obrigatorio.
- Criar endpoints internos para diagnostico e plano de estudos com IA.
- Centralizar OpenRouter em `config/openrouter.php`.
- Criar migration incremental em `database/migrations/`.
- Documentar seguranca, acessibilidade, PWA, mobile, banco, IA e roadmap.
