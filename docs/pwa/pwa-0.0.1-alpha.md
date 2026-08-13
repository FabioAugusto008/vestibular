# PWA - 0.0.1-alpha

Data: 2026-05-15

## Objetivo

Adicionar suporte PWA inicial ao EstudAI, com instalacao basica no celular e cache seguro de arquivos estaticos.

## Arquivos criados

- `manifest.webmanifest`
- `sw.js`
- `src/services/pwa.js`
- `src/assets/icons/estudai-icon.svg`

## Funcionamento

O `manifest.webmanifest` define nome, cor do tema, modo standalone, orientacao portrait e icone.

O `src/services/pwa.js` registra o service worker somente em `http` ou `https`, evitando erro ao abrir arquivo diretamente.

O `sw.js` usa cache versionado:

- nome do cache: `estudai-static-0.0.1-alpha.2`;
- cache inicial de HTML, CSS, JS, manifest, design system, servico local de IA e icone;
- limpeza automatica de caches antigos;
- chamadas para `api/` ficam fora do cache;
- navegacao usa rede primeiro e fallback para cache;
- assets estaticos usam cache com revalidacao simples.

## O que nao foi feito

- Cache offline completo de dados do usuario.
- Push notifications.
- Background sync.
- Cache de respostas das APIs.

## Riscos evitados

- O service worker nao guarda respostas de login, sessao, banco ou endpoints PHP.
- A estrategia nao e agressiva, para reduzir risco de atualizar codigo e o navegador manter versao antiga.

## Proximos passos

- Criar icones PNG em varios tamanhos para melhor compatibilidade.
- Criar tela offline especifica.
- Planejar notificacoes antes de pedir permissao ao usuario.
- Versionar cache a cada release.
