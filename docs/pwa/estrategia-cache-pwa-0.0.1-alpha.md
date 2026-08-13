# Estrategia de cache PWA - 0.0.1-alpha

Data: 2026-05-15

## Estado atual

- Manifest usa nome EstudAI e tema azul escuro.
- Service worker registrado por `src/services/pwa.js`.
- Cache versionado em `sw.js`.
- APIs em `/api/` ficam fora do cache.
- Navegacao usa rede primeiro e fallback para cache.

## Alteracoes desta etapa

- Cache atualizado para `estudai-static-0.0.1-alpha.2`.
- `assets/css/design-system.css` e `assets/js/ia.js` adicionados ao cache estatico.
- Descricao do manifest padronizada como EstudAI.

## Estrategia recomendada

- Versionar o cache a cada release.
- Nao cachear respostas de login, IA, banco ou sessoes.
- Preferir rede primeiro para HTML.
- Usar cache com revalidacao para CSS/JS estaticos.
- Criar fallback offline especifico no futuro.

## Riscos evitados

- Usuario preso em versao antiga.
- Cache de dados sensiveis.
- IA ou APIs dinamicas respondendo a partir de cache.

## Pendencias

- Criar icones PNG em varios tamanhos.
- Criar pagina offline.
- Criar estrategia para update prompt quando houver nova versao.
