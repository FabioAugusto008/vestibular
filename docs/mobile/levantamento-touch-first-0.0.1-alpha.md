# Levantamento mobile touch-first - 0.0.1-alpha

Data: 2026-05-15

## Objetivo

Transformar o EstudAI em uma experiencia confortavel para uso diario no celular, com navegacao curta, botoes grandes, leitura clara e fluxos que funcionem bem em sessoes rapidas.

## Telas mobile necessarias

- Login/cadastro.
- Inicio/dashboard.
- Plano de estudos.
- Rotina/tarefas do dia.
- Questoes do dia.
- Revisao.
- Simulados.
- Estatisticas resumidas.
- Anotacoes.
- Perfil/configuracoes.

## Componentes que precisam adaptacao

- Navegacao principal: no desktop segue como abas horizontais; no mobile deve usar navegacao inferior.
- Cards de estatisticas: precisam quebrar em 2 colunas ou 1 coluna em telas estreitas.
- Dots de questoes: precisam ter area de toque maior.
- Alternativas: devem ter altura minima confortavel e texto legivel.
- Modais: precisam ocupar largura quase total no mobile.
- Toasts: devem aparecer acima da navegacao inferior.
- Formularios: inputs e botoes com alvo de toque minimo perto de 48px.

## Navegacao recomendada

Mobile:

- Inicio
- Plano
- Rotina
- Perfil

Fluxos secundarios como questoes, revisao, simulados, estatisticas e anotacoes continuam acessiveis por cards e botoes internos.

Desktop:

- Mantem navegacao horizontal completa para acesso rapido.

## Diferencas entre desktop e mobile

- Desktop prioriza visao ampla, comparacao de metricas e multitarefa.
- Mobile prioriza proxima acao, leitura curta e alvo de toque maior.
- Desktop pode mostrar todas as abas; mobile precisa reduzir escolhas principais.
- Mobile deve evitar elementos pequenos, barras muito densas e textos longos em cards.

## Prioridades

1. Navegacao inferior mobile.
2. Dashboard legivel e touch-first.
3. Tela de plano inicial.
4. Tela de rotina inicial.
5. Perfil/configuracoes mobile.
6. Ajustes finos nas questoes e simulados.
7. Persistencia real de tarefas, se virar funcionalidade central.

## Implementado nesta versao

- Navegacao inferior mobile com Inicio, Plano, Rotina e Perfil.
- Tela inicial mobile usando o dashboard existente.
- Login/cadastro com identidade visual atualizada e responsividade base.
- Tela inicial de plano de estudos.
- Tela inicial de rotina/tarefas com checklist local.
- Tela inicial de perfil/configuracoes.
- Ajustes de toque em botoes, alternativas, dots e toasts.

## Riscos

- Checklist mobile usa `localStorage` e ainda nao sincroniza com o banco.
- Plano de estudos ainda e uma tela inicial, nao um gerador completo de cronograma.
- Preferencias mobile ainda reutilizam modal desktop.
- Tarefas futuras podem exigir novas tabelas se precisarem sincronizacao multi-dispositivo.

## Plano de implementacao seguinte

1. Criar modelo de dados para tarefas e plano de estudos.
2. Persistir rotina no backend.
3. Criar CRUD de blocos de estudo.
4. Adaptar simulados para modo mobile com progresso compacto.
5. Adicionar notificacoes PWA apenas depois de permissao explicita e estrategia clara.
