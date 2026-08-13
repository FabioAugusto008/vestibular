# EstudAI

EstudAI e uma plataforma web para organizar, planejar e acompanhar uma rotina de estudos com apoio de IA.

O projeto combina cadastro, login, onboarding, diagnostico do estudante, plano semanal, calendario, rotina diaria, exercicios, revisoes, simulados, estatisticas, conquistas e anotacoes. A IA ajuda na organizacao da rotina, no diagnostico, no replanejamento e na revisao semanal; exercicios e simulados continuam usando questoes aprovadas na base do sistema.

## Status

Versao atual: `0.1.0-alpha` (`Launch Core`).

Esta versao prepara o EstudAI para uma primeira apresentacao publica, com foco em dashboard, rotina editavel, plano semanal, revisoes, simulados, redacao ENEM, PWA basico, CSRF e tratamento de erros.

## Funcionalidades

- Cadastro, login e sessao com CSRF.
- Onboarding obrigatorio persistido no banco.
- Diagnostico do estudante com IA via backend.
- Plano semanal de estudos salvo com itens, tarefas, eventos e versoes.
- Calendario mensal/anual com tarefas, exercicios, revisoes e simulados.
- Rotina diaria baseada em tarefas reais, com concluir, reabrir, iniciar, pausar, finalizar, editar, adiar, remarcar e cancelar.
- Exercicios planejados por tarefa e semana, reutilizando questoes da base.
- Revisoes por conteudo com historico real de erros.
- Simulados planejados com liberacao por data.
- Revisao semanal por IA aos domingos.
- Replanejamento semanal com motivo informado pelo estudante.
- Area de redacao ENEM com checklist, competencias, historico e analise orientativa nao oficial.
- Estatisticas, historico, metas, conquistas e anotacoes por questao.
- PWA com `offline.html`, service worker e cache estatico versionado.

## Tecnologias

- Frontend: HTML, CSS e JavaScript vanilla.
- Backend: PHP procedural.
- Banco de dados: MySQL/MariaDB via PDO.
- Ambiente local recomendado: XAMPP com Apache, PHP e MySQL/MariaDB.

## Estrutura

```text
app/public/           Paginas publicas, manifest e service worker
app/src/assets/       Icones, marca e identidade visual
app/src/components/   Componentes frontend reutilizaveis
app/src/config/       Configuracoes frontend
app/src/pages/        Scripts das paginas
app/src/services/     Servicos frontend
app/src/styles/       Estilos da aplicacao
database/             Schema, migrations, seeds e questoes
docs/                 Documentacao tecnica e historico de versoes
server/api/           Endpoints PHP
server/config/        Configuracao de banco, app e IA
server/helpers/       Helpers compartilhados
server/services/      Servicos PHP e integracao com IA
storage/              Cache e logs locais
tests/                Espaco para testes futuros
```

## Como Rodar Localmente

1. Copie o projeto para a pasta do servidor local, por exemplo `C:\xampp\htdocs\estudai`.
2. Crie um banco MySQL/MariaDB vazio.
3. Importe `database/schema.sql`.
4. Copie `.env.example` para `.env`.
5. Ajuste as variaveis de ambiente no arquivo `.env`.
6. Inicie Apache e MySQL pelo painel do XAMPP.
7. Acesse `http://localhost/estudai/app/public/index.html`.

## Variaveis De Ambiente

Use `.env.example` como modelo. O arquivo `.env` real deve ficar somente na maquina/servidor local e nao deve ser commitado.

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=vestibular_estudos
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
DB_SSL=false

OPENROUTER_API_KEY=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_MODEL=liquid/lfm-2.5-1.2b-instruct:free
OPENROUTER_SITE_URL=http://localhost/estudai
OPENROUTER_SITE_NAME=EstudAI
OPENROUTER_TIMEOUT_SECONDS=90

ESTUDAI_CRON_TOKEN=
```

## Seguranca Antes De Publicar

- Nunca publique `.env`, chaves de API, senhas, tokens ou dumps reais do banco.
- `backup/`, arquivos `.zip` e logs locais estao ignorados pelo Git porque podem conter informacoes sensiveis.
- Se alguma chave ja tiver sido enviada ao GitHub, revogue a chave no provedor e gere outra.
- Se `.env` ja tiver sido commitado, remova do indice com `git rm --cached .env` e limpe o historico antes de tornar o repositorio publico.
- Chamadas de IA devem continuar no backend; o frontend nao deve receber `OPENROUTER_API_KEY`.

## Comandos Uteis

```powershell
php -l server/api/auth.php
php -l server/api/onboarding.php
php -l server/api/diagnostico.php
php -l server/api/plano-estudos.php
php -l server/api/tarefas-estudo.php
php -l server/api/calendario-estudai.php
php -l server/api/exercicios-ia.php
php -l server/api/revisoes-ia.php
php -l server/api/simulados-planejados.php
php -l server/api/redacao.php
php -l server/config/database.php
```

## Documentacao

- [Documentacao completa](DOCUMENTACAO.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Versao 0.1.0-alpha](docs/versions/0.1.0-alpha.md)

## Observacoes De Desenvolvimento

- A IA nao deve gerar questoes, alternativas, gabaritos, exercicios ou simulados autorais em tempo real.
- Exercicios, revisoes e simulados devem continuar usando questoes aprovadas no banco.
- Antes de alterar schema, crie uma migration incremental e documentada.
- Preserve os contratos atuais dos endpoints em `server/api/`.
- Para o cron semanal em producao, configure `ESTUDAI_CRON_TOKEN` e execute `server/api/cron_revisao_semanal_ia.php` aos domingos.
