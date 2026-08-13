# Checklist de validacao - EstudAI 0.0.2-alpha

## Autenticacao

- [ ] Login funciona.
- [ ] Cadastro funciona.
- [ ] Logout funciona.

## Onboarding

- [ ] Usuario novo ve aviso para preencher perfil.
- [ ] Salvar onboarding persiste no banco.
- [ ] Recarregar pagina mantem perfil salvo.
- [ ] Editar perfil funciona.
- [ ] Payload invalido retorna erro amigavel.

## Diagnostico

- [ ] Gerar diagnostico com perfil salvo.
- [ ] IA sem chave usa fallback.
- [ ] Erro de IA nao quebra interface.
- [ ] Diagnostico aparece no dashboard/interface.
- [ ] `ia_historico` recebe registro sem chave ou dados sensiveis.

## Plano

- [ ] Gerar plano com perfil salvo.
- [ ] Plano salva em `planos_estudo`.
- [ ] Plano cria registros em `plano_estudo_itens`.
- [ ] Plano cria tarefas em `tarefas_estudo`.
- [ ] Plano aparece na secao Plano apos recarregar.
- [ ] Gerar novo plano substitui o ativo anterior sem apagar historico.

## Rotina

- [ ] Tarefas de hoje aparecem.
- [ ] Proximas tarefas da semana aparecem.
- [ ] Tarefas atrasadas aparecem.
- [ ] Concluir tarefa funciona.
- [ ] Reabrir tarefa funciona.
- [ ] Progresso atualiza apos concluir/reabrir.

## Estatisticas

- [ ] `api/estatisticas.php?action=estudai_geral` retorna dados.
- [ ] Dashboard exibe perfil concluido.
- [ ] Dashboard exibe plano ativo.
- [ ] Dashboard exibe tarefas da semana.
- [ ] Dashboard exibe conclusao semanal.
- [ ] Dashboard exibe atrasadas quando houver.

## Mobile

- [ ] Onboarding cabe no celular.
- [ ] Plano funciona no mobile.
- [ ] Rotina funciona no mobile.
- [ ] Navegacao inferior nao cobre botoes.
- [ ] Tarefas nao geram tabela larga.

## PWA

- [ ] Service worker registra.
- [ ] Cache versionado esta como `estudai-static-0.0.2-alpha`.
- [ ] APIs nao sao cacheadas.
- [ ] Endpoints de IA nao sao cacheados.

## Sintaxe

- [ ] `php -l api/onboarding.php`
- [ ] `php -l api/diagnostico.php`
- [ ] `php -l api/plano-estudos.php`
- [ ] `php -l api/tarefas-estudo.php`
- [ ] `php -l api/estatisticas.php`
- [ ] `php -l config/helpers.php`
- [ ] `node --check src/pages/app.js`
- [ ] `node --check src/pages/login.js`
- [ ] `node --check assets/js/ia.js`
- [ ] Verificar console do navegador.
