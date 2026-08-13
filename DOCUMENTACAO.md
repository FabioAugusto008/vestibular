# EstudAI - Documentacao Tecnica e Funcional

Documento gerado em: 2026-05-28  
Versao analisada: `0.1.0-alpha`  
Projeto: `EstudAI`  
Tipo: plataforma web de organizacao, planejamento e acompanhamento de rotina de estudos

---

## 1. Visao Geral

O EstudAI e uma plataforma de estudos com login, onboarding obrigatorio, diagnostico com IA, geracao de plano semanal, calendario, rotina diaria, questoes, exercicios vinculados ao plano, revisoes, simulados, estatisticas, conquistas e anotacoes.

A arquitetura atual separa tres responsabilidades principais:

- Frontend em HTML, CSS e JavaScript vanilla.
- Backend procedural em PHP, exposto por endpoints em `server/api`.
- Banco MySQL/MariaDB via PDO, com schema canonico em `database/schema.sql`.

O produto esta orientado por um fluxo semanal:

1. O estudante cria conta ou entra no sistema.
2. O onboarding coleta objetivo, disponibilidade real, modo de estudo, materias e reforcos.
3. A IA gera diagnostico educacional a partir do perfil.
4. A IA gera um plano semanal dentro da disponibilidade real do aluno.
5. O backend salva plano, itens, tarefas e eventos de calendario.
6. O aluno executa tarefas, responde exercicios e simulados.
7. O sistema mede desempenho, atraso, acertos, erros, progresso e tempo planejado.
8. A revisao semanal analisa a semana encerrada e pode gerar a proxima semana.

Atualizacao `0.1.0-alpha` (`Launch Core`): dashboard, rotina, plano, seguranca, PWA, estatisticas, conquistas e Redacao ENEM foram reforcados sem alterar a identidade visual. A IA continua proibida de gerar questoes, alternativas, gabaritos, exercicios ou simulados em tempo real.

Uma decisao importante do projeto: a IA nao deve criar questoes, alternativas, gabaritos, simulados ou exercicios autorais em tempo real. Exercicios, revisoes por conteudo e simulados usam questoes aprovadas existentes no banco.

---

## 2. Objetivos do Projeto

O objetivo funcional do EstudAI e transformar a rotina real do estudante em um plano de estudo executavel, acompanhado por dados e ajustado semanalmente.

Objetivos principais:

- Organizar a rotina de estudos por disponibilidade real.
- Montar planos semanais realistas usando IA.
- Manter o ENEM como fluxo especial com grade fixa de materias.
- Separar planejamento inteligente de geracao de questoes.
- Usar banco proprio de questoes aprovadas para exercicios e simulados.
- Registrar respostas, acertos, erros, historico, metas e conquistas.
- Exibir calendario e rotina derivados de tarefas persistidas.
- Revisar erros e desempenho para orientar a proxima semana.
- Servir como PWA basico com cache de arquivos estaticos.

---

## 3. Stack e Dependencias

### Frontend

- HTML5.
- CSS3.
- JavaScript vanilla.
- Service Worker para PWA basico.
- Fonte externa via Google Fonts: `Inter` e `Space Grotesk`.

### Backend

- PHP procedural.
- PDO para MySQL/MariaDB.
- cURL para chamadas ao OpenRouter.
- Sessoes PHP para autenticacao.

### Banco

- MySQL/MariaDB.
- Charset recomendado: `utf8mb4`.
- Schema canonico: `database/schema.sql`.
- Migrations incrementais: `database/migrations`.

### IA

- Provider: OpenRouter.
- Configuracao: `server/config/openrouter.php`.
- Cliente HTTP: `server/services/ai/openrouterClient.php`.
- Prompts: `server/services/ai/prompts.php`.
- Normalizacao de respostas: `server/services/ai/estudaiService.php`.

---

## 4. Estrutura de Pastas

```text
app/
  public/
    index.html                 Tela de login/cadastro
    app.html                   Painel principal autenticado
    manifest.webmanifest       Manifest PWA
    sw.js                      Service worker
  src/
    assets/                    Marca, logos, icones e identidade visual
    components/                Reservado para componentes futuros
    config/api.js              Registro dos endpoints do backend
    pages/login.js             Logica da tela de login
    pages/app.js               Logica principal do painel
    services/http.js           Wrapper de fetch, CSRF e endpoints
    services/ia.js             Cliente frontend para endpoints de IA
    services/pwa.js            Registro do service worker
    styles/                    CSS do app, login e design system

server/
  api/                         Endpoints HTTP PHP
  config/                      Configuracoes de app, banco, IA e OpenRouter
  controllers/                 Reservado, atualmente vazio
  helpers/helpers.php          Sessao, seguranca, JSON, banco e questoes
  models/                      Reservado, atualmente vazio
  services/
    ai/                        Cliente e servicos de IA
    weeklyReview.php           Revisao semanal e geracao da proxima semana

database/
  schema.sql                   Schema canonico completo
  migrations/                  Migrations incrementais
  questions/                   Seed autoral de questoes ENEM

docs/                          Documentacao historica por versao e tema
storage/                       Logs/cache local
tests/                         Reservado para testes futuros
```

---

## 5. Arquitetura em Alto Nivel

O frontend trabalha como uma SPA simples em `app/public/app.html`, controlada por `app/src/pages/app.js`. A navegacao troca secoes por DOM, sem roteador externo.

O backend nao usa framework. Cada arquivo em `server/api` atua como endpoint independente, inclui `helpers.php`, verifica sessao quando necessario, interpreta `action` e responde JSON.

O banco e o ponto central de estado:

- Sessao autenticada identifica `usuario_id`.
- Perfil e onboarding ficam em `estudo_perfis` e `onboarding_respostas`.
- Planos ficam em `planos_estudo`.
- Itens do plano ficam em `plano_estudo_itens`.
- Execucao diaria fica em `tarefas_estudo`.
- Calendario fica em `eventos_calendario_estudai`.
- Questoes e alternativas ficam em `questoes` e `questoes_alternativas`.
- Respostas ficam em `respostas_usuario`, `questoes_respostas_usuario`, `respostas_exercicios_planejados` e `simulados_planejados_respostas`.
- Chamadas de IA ficam registradas em `ia_historico`.
- Revisoes semanais ficam em `revisoes_semanais_ia`.

Fluxo resumido:

```text
app.html/app.js
  -> services/http.js
    -> server/api/*.php
      -> helpers.php
        -> getDB()
          -> MySQL/MariaDB
      -> services/ai/*.php quando precisa de IA
```

---

## 6. Configuracao de Ambiente

O backend le variaveis por `getenv`, `$_ENV`, `$_SERVER` ou pelo arquivo `.env`.

Variaveis suportadas:

```text
DATABASE_URL
DB_URL
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
DB_SSL
OPENROUTER_API_KEY
OPENROUTER_BASE_URL
OPENROUTER_MODEL
OPENROUTER_SITE_URL
OPENROUTER_SITE_NAME
OPENROUTER_TIMEOUT_SECONDS
ESTUDAI_CRON_TOKEN
```

Observacoes importantes:

- O arquivo `.env` nao deve ser versionado nem exposto publicamente.
- Chaves reais de OpenRouter e credenciais reais de banco devem ser mantidas fora da documentacao.
- `DATABASE_URL` tem prioridade sobre as variaveis separadas de banco.
- `DB_SSL=true` ativa opcoes SSL no PDO.
- O timeout do OpenRouter e limitado no codigo entre 10 e 90 segundos.

Trecho relevante de `server/config/database.php`:

```php
function envValue(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    // fallback: leitura do arquivo .env na raiz
}
```

---

## 7. Como Executar Localmente

Fluxo recomendado:

1. Instalar PHP com extensoes `pdo_mysql` e `curl`.
2. Iniciar MySQL/MariaDB.
3. Criar/importar o banco usando `database/schema.sql`.
4. Configurar `.env` com banco e OpenRouter.
5. Servir o projeto pela raiz.

Exemplo com servidor embutido do PHP, executado na raiz:

```powershell
php -S localhost:8080
```

URL principal:

```text
http://localhost:8080/app/public/index.html
```

Em Laragon/Apache, a URL dependera do virtual host/pasta. Como o projeto esta em `c:\laragon\www\estudai`, normalmente sera algo como:

```text
http://localhost/estudai/app/public/index.html
```

---

## 8. Frontend

### 8.1 Entrada publica

Arquivo: `app/public/index.html`

Responsabilidades:

- Exibir login e cadastro.
- Carregar `api.js`, `http.js`, `pwa.js` e `login.js`.
- Redirecionar usuario autenticado para `app.html`.

Arquivo: `app/src/pages/login.js`

Funcoes principais:

- `switchTab(tab)`: alterna entre login e cadastro.
- `fazerLogin()`: envia `action=login` para `auth.php`.
- `fazerCadastro()`: envia `action=cadastrar` para `auth.php`.
- Ao carregar, chama `auth?action=status` e redireciona se ja estiver logado.

### 8.2 Painel autenticado

Arquivo: `app/public/app.html`

Secoes principais:

| Secao | ID | Finalidade |
| --- | --- | --- |
| Inicio | `sec-dashboard` | Resumo, progresso, perfil, diagnostico, plano ativo e meta semanal |
| Calendario | `sec-calendario` | Eventos de tarefas, exercicios, revisoes e simulados |
| Questoes | `sec-questoes` | Treino diario de questoes |
| Revisao | `sec-revisao` | Revisao de erros e revisoes por conteudo |
| Exercicios | `sec-exercicios` | Pratica vinculada a tarefa do plano |
| Simulados | `sec-simulados` | Simulados planejados e simulados legados |
| Estatisticas | `sec-estatisticas` | Indicadores de desempenho |
| Conquistas | `sec-conquistas` | Badges desbloqueados |
| Anotacoes | `sec-anotacoes` | Anotacoes em questoes |
| Plano | `sec-plano` | Plano semanal ativo |
| Rotina | `sec-rotina` | Tarefas de hoje, semana e atrasadas |
| Perfil | `sec-perfil` | Resumo do onboarding/perfil |

### 8.3 Estado global do painel

Arquivo: `app/src/pages/app.js`

O objeto `state` centraliza dados de UI:

```js
const state = {
  questoes: [],
  respostas: {},
  gabarito: null,
  finalizado: false,
  currentIdx: 0,
  theme: 'light',
  revisaoQuestoes: [],
  simulados: [],
  onboarding: null,
  csrfToken: '',
  diagnostico: null,
  plano: null,
  tarefasHoje: [],
  tarefasSemana: [],
  tarefasAtrasadas: [],
  calendarEvents: [],
  currentExerciseLote: null,
  plannedSimulados: [],
  onboardingCompleto: false
};
```

Na inicializacao, `app.js`:

1. Chama `auth?action=status`.
2. Salva o CSRF token em `window.EstudAICsrfToken`.
3. Carrega onboarding.
4. Bloqueia o app se onboarding estiver pendente.
5. Carrega preferencias, questoes, historico, metas, revisao, estatisticas do nucleo e conquistas.

### 8.4 Navegacao e bloqueio por onboarding

`showSection(id)` troca as secoes ativas. Se o onboarding nao estiver completo, somente `dashboard` e `perfil` ficam acessiveis.

Trecho relevante:

```js
function showSection(id) {
  if (!state.onboardingCompleto && !['dashboard', 'perfil'].includes(id)) {
    openOnboardingModal(true);
    showToast('warning', 'Formulario obrigatorio', 'Antes de gerar seu plano, precisamos entender sua rotina de estudos.');
    return;
  }

  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
}
```

### 8.5 Configuracao de endpoints

Arquivo: `app/src/config/api.js`

O frontend usa `../../server/api` como base:

```js
const API_BASE = '../../server/api';

endpoints: {
  auth: `${API_BASE}/auth.php`,
  onboarding: `${API_BASE}/onboarding.php`,
  planoEstudos: `${API_BASE}/plano-estudos.php`,
  tarefasEstudo: `${API_BASE}/tarefas-estudo.php`,
  calendarioEstudai: `${API_BASE}/calendario-estudai.php`,
  exerciciosIa: `${API_BASE}/exercicios-ia.php`,
  revisoesIa: `${API_BASE}/revisoes-ia.php`,
  simuladosPlanejados: `${API_BASE}/simulados-planejados.php`
}
```

### 8.6 Wrapper HTTP e CSRF

Arquivo: `app/src/services/http.js`

Responsabilidades:

- Resolver nome logico do endpoint.
- Serializar query strings.
- Enviar `X-Requested-With: XMLHttpRequest`.
- Enviar `X-CSRF-Token` em metodos diferentes de `GET` e `HEAD`, quando houver token.
- Usar `credentials: same-origin`.

Trecho relevante:

```js
function apiFetch(endpoint, options) {
  const headers = new Headers(fetchOptions.headers || {});
  const method = String(fetchOptions.method || 'GET').toUpperCase();

  if (!headers.has('X-Requested-With')) {
    headers.set('X-Requested-With', 'XMLHttpRequest');
  }

  if (method !== 'GET' && method !== 'HEAD') {
    const token = window.EstudAICsrfToken || config.csrfToken;
    if (token && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', token);
    }
  }

  return fetch(endpoint, { credentials: 'same-origin', ...fetchOptions, headers });
}
```

### 8.7 Onboarding no frontend

O onboarding e modal, em etapas, e coleta:

- Objetivo/modo de estudo.
- Data de prova.
- Lingua estrangeira para ENEM.
- Materias manuais para modos nao ENEM.
- Disponibilidade por dia e blocos de horario.
- Reforcos por materia.
- Intensidade.
- Preferencia de exercicios por dia.
- Frequencia de simulados.
- Obstaculos.
- Conteudos de reforco.
- Informacao livre.
- Meta semanal.
- Notificacoes.

Funcoes principais:

- `setupOnboardingWizard()`
- `setOnboardingStep(step)`
- `collectScheduleBlocks()`
- `collectReforcos()`
- `collectOnboardingForm()`
- `validateOnboardingStep(step)`
- `saveOnboarding()`
- `renderOnboardingSummary()`
- `renderProfileDetails()`

Para ENEM, o frontend monta grade fixa:

```js
function getMateriasBasePorModo(modo, linguaEstrangeira, materiasManuais = []) {
  if (modo === 'enem') {
    return [
      'Redacao', 'Linguagens', 'Matematica', 'Ciencias Humanas',
      'Ciencias da Natureza', 'Portugues', 'Literatura', 'Historia',
      'Geografia', 'Filosofia', 'Sociologia', 'Biologia', 'Quimica',
      'Fisica', linguaEstrangeira === 'espanhol' ? 'Espanhol' : 'Ingles'
    ];
  }
  return materiasManuais.map((item) => item.trim()).filter(Boolean).slice(0, 30);
}
```

### 8.8 Plano, rotina e calendario no frontend

Funcoes principais:

- `loadPlanoAtivo()`: carrega plano ativo.
- `gerarPlanoEstudos()`: chama `plano-estudos?action=gerar_semana`.
- `renderPlano()`: exibe plano salvo e tarefas.
- `loadRotina()`: carrega tarefas de hoje, semana e atrasadas.
- `concluirTarefa(id)`: marca tarefa como concluida.
- `reabrirTarefa(id)`: reabre tarefa.
- `loadCalendarMonth()`: carrega eventos mensais.
- `loadCalendarYear()`: carrega eventos anuais.
- `renderCalendar()`: monta grade mensal.
- `renderCalendarYear()`: monta visao anual.

### 8.9 Exercicios, revisoes e simulados no frontend

Exercicios:

- `abrirExerciciosTarefa(tarefaId)`
- `loadExercisesForTask(tarefaId, regenerate)`
- `renderExercises()`
- `responderExercicio(loteId, key, tipo)`

Revisoes por conteudo:

- `abrirRevisaoTarefa(tarefaId)`
- `renderContentReview()`

Revisao geral de erros:

- `loadRevisao(materia, trigger)`
- `renderRevisao()`
- `responderRevisao(questaoId, resposta)`

Simulados planejados:

- `loadPlannedSimulados()`
- `gerarSimuladoDaSemana()`
- `abrirSimuladoDaTarefa(tarefaId)`
- `iniciarSimuladoPlanejado(id)`
- `responderSimuladoPlanejado(simId, key)`
- `finalizarSimuladoPlanejado(simId)`

Simulados legados:

- `loadSimulados()`
- `iniciarSimulado(id)`
- `responderSimulado(questaoId, resposta)`
- `finalizarSimulado()`

### 8.10 Design System e estilos

Arquivos:

- `app/src/styles/design-system.css`
- `app/src/styles/app.css`
- `app/src/styles/login.css`

Principais caracteristicas:

- Variaveis CSS para cores, espacamento, raios, sombras e fontes.
- Tema claro e escuro via classe `html.dark`.
- Header sticky com blur.
- Navegacao desktop horizontal e mobile fixa.
- Cards, badges, botoes, inputs, modais e alertas.
- Suporte a `prefers-reduced-motion` no design system.

Exemplo de tokens:

```css
:root {
  --color-bg: #f6f8fb;
  --color-surface: #ffffff;
  --color-primary: #092b57;
  --color-secondary: #1765ff;
  --color-success: #16825d;
  --color-danger: #b42318;
  --radius-md: 8px;
  --font-sans: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
```

### 8.11 PWA

Arquivos:

- `app/public/manifest.webmanifest`
- `app/public/sw.js`
- `app/src/services/pwa.js`

O service worker:

- Usa cache versionado `0.1.0-alpha-launch-core`.
- Cacheia HTML, CSS, JS e assets principais.
- Remove caches antigos na ativacao.
- Ignora metodos diferentes de GET.
- Nao cacheia chamadas a `/server/api/`.
- Usa network-first para navegacao e cache-first com atualizacao para assets.

Trecho relevante:

```js
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;
  if (url.pathname.includes('/server/api/')) return;
});
```

---

## 9. Backend

### 9.1 Padroes gerais

Todos os endpoints principais incluem `server/helpers/helpers.php`.

Responsabilidades de `helpers.php`:

- Inicializar sessao.
- Verificar login.
- Retornar JSON padronizado.
- Criar e validar CSRF token.
- Ler payload JSON, form-data ou urlencoded.
- Sanitizar strings.
- Verificar existencia de tabelas e colunas.
- Registrar erros tecnicos.
- Buscar questoes aprovadas.
- Acoplar alternativas a questoes.
- Resolver gabarito.
- Salvar respostas em modelos de resposta.
- Gerar questoes do dia.

### 9.2 Sessao e autenticacao

Nome da sessao:

```php
define('SESSION_NAME', 'estudai_session');
define('ESTUDAI_VERSION', '0.1.0-alpha');
```

Cookie de sessao:

- `httponly: true`
- `samesite: Lax`
- `secure: true` quando HTTPS estiver ativo
- `path: /`

Trecho relevante:

```php
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
```

### 9.3 Respostas JSON

Padrao de resposta:

```php
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
```

### 9.4 CSRF

O token CSRF e salvo na sessao e enviado pelo frontend em `X-CSRF-Token`.

```php
function validateCsrfToken(): void {
    initSession();
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        jsonResponse(['erro' => 'Token de seguranca invalido. Recarregue a pagina e tente novamente.'], 419);
    }
}
```

CSRF e usado nos fluxos novos e nos endpoints legados que fazem POST, incluindo onboarding, plano semanal, tarefas, calendario, exercicios planejados, revisoes planejadas, simulados planejados, questoes, revisao, simulados, metas, preferencias, anotacoes, conquistas e revisao semanal manual.

Na `0.1.0-alpha`, tambem existe rate limit simples por sessao/IP para login, cadastro, diagnostico, plano, replanejamento, mensagens de IA, redacao e revisao semanal manual.

---

## 10. Referencia dos Endpoints

Todos os endpoints, exceto cron publico controlado por token/local, exigem usuario autenticado via `requireLogin()`.

### 10.1 `server/api/auth.php`

| Acao | Metodo | Finalidade |
| --- | --- | --- |
| `cadastrar` | POST | Cria usuario com nome, email e senha |
| `login` | POST | Autentica email/senha, regenera ID da sessao e retorna CSRF |
| `logout` | GET/POST | Encerra sessao |
| `status` | GET | Informa se usuario esta logado e retorna CSRF |

Regras:

- Nome entre 2 e 100 caracteres.
- Email valido e unico.
- Senha com pelo menos 6 caracteres.
- Senha armazenada com `password_hash(..., PASSWORD_BCRYPT)`.
- Login usa `password_verify`.

### 10.2 `server/api/onboarding.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `status` | GET | Nao | Verifica se onboarding esta completo |
| `carregar` | GET | Nao | Carrega perfil e ultima resposta salva |
| `salvar` | POST | Sim | Valida e persiste perfil completo |

Regras importantes:

- Onboarding e obrigatorio.
- Verifica se o usuario da sessao ainda existe.
- Modo `enem` exige lingua `ingles` ou `espanhol`.
- Modo `enem` define grade fixa de materias.
- Modos nao ENEM exigem ao menos uma materia manual.
- Disponibilidade deve ser estruturada por dia.
- Cada bloco exige `inicio` e `fim` em `HH:MM`.
- Hora final precisa ser maior que hora inicial.
- Blocos no mesmo dia nao podem se sobrepor.
- Reforcos viram pesos de 1 a 3.
- O perfil completo fica em `estudo_perfis`.
- Cada salvamento gera snapshot em `onboarding_respostas`.

Exemplo de regra ENEM no backend:

```php
function onboardingMateriasBasePorModo(string $modo, ?string $lingua, array $materiasManuais = []): array {
    if ($modo === 'enem') {
        if (!in_array($lingua, ['ingles', 'espanhol'], true)) {
            jsonResponse(['erro' => 'Escolha Ingles ou Espanhol para o modo ENEM.'], 400);
        }
        return [
            'Redacao', 'Linguagens', 'Matematica', 'Ciencias Humanas',
            'Ciencias da Natureza', 'Portugues', 'Literatura', 'Historia',
            'Geografia', 'Filosofia', 'Sociologia', 'Biologia', 'Quimica',
            'Fisica', $lingua === 'espanhol' ? 'Espanhol' : 'Ingles',
        ];
    }
}
```

### 10.3 `server/api/diagnostico.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `status` | GET | Nao | Indica se existe perfil e diagnostico |
| `carregar_ultimo` | GET | Nao | Retorna ultimo diagnostico salvo |
| `gerar` | POST | Sim | Chama IA, normaliza e salva historico |

Regras:

- Exige perfil salvo.
- Usa `estudaiGerarDiagnostico`.
- Registra resultado em `ia_historico`.
- Falha de IA retorna erro 503 controlado.

### 10.4 `server/api/plano-estudos.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar_ativo` | GET | Nao | Retorna plano ativo com tarefas |
| `arquivar` | POST | Sim | Arquiva plano informado |
| `gerar_semana` | POST | Sim | Fluxo principal semanal |
| `replanejar_semana` | POST | Sim | Replaneja a semana ativa a partir de motivo, perfil, disponibilidade, atrasos e desempenho |
| `gerar_anual` | POST | Sim | Fluxo anual legado 0.0.3-alpha |
| `regenerar_manual_dev` | POST | Sim | Alias legado para plano anual |
| `gerar` | POST | Sim | Fluxo semanal legado |
| `regenerar` | POST | Sim | Alias do fluxo legado |

Fluxo de `gerar_semana`:

1. Confere tabelas da versao funcional.
2. Carrega perfil.
3. Exige `onboarding_completo`.
4. Monta janela semanal a partir da disponibilidade.
5. Inclui diagnostico recente se existir.
6. Chama IA para gerar plano semanal.
7. Valida todas as tarefas geradas.
8. Substitui planos ativos anteriores.
9. Cria `planos_estudo`.
10. Cria `planejamento_semanal_controle`.
11. Cria `plano_versoes`.
12. Cria `plano_estudo_itens`.
13. Cria `tarefas_estudo`.
14. Cria `eventos_calendario_estudai`.

Validacoes de janela:

- A semana comeca no primeiro dia disponivel entre hoje e os proximos 7 dias.
- A semana termina 6 dias apos o inicio, limitada pela data da prova quando existir.
- Tarefas antes de hoje sao descartadas.
- Tarefas fora da janela sao descartadas.
- Tarefas depois da data da prova sao descartadas.
- Materias fora de `materias_base` sao descartadas.
- Horarios precisam caber em um bloco de disponibilidade.

Trecho relevante:

```php
function planoTaskFitsBlock(array $task, array $janela): bool {
    $data = planoNormalizarData($task['data'] ?? null);
    $date = new DateTimeImmutable($data);
    $day = planoDiaKeyFromDate($date);
    $inicio = planoValidHour($task['hora_inicio'] ?? null);
    $fim = planoValidHour($task['hora_fim'] ?? null);

    foreach (($janela['dias_disponiveis'][$day] ?? []) as $block) {
        if ($inicio >= $block['inicio'] && $fim <= $block['fim']) {
            return true;
        }
    }
    return false;
}
```

### 10.5 `server/api/tarefas-estudo.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `hoje` | GET | Nao | Lista tarefas da data atual |
| `semana` | GET | Nao | Lista tarefas da semana atual |
| `atrasadas` | GET | Nao | Lista tarefas pendentes antes de hoje |
| `listar_por_data` | GET | Nao | Lista tarefas de uma data especifica |
| `recentes` | GET | Nao | Lista tarefas concluidas recentemente |
| `concluir` | POST | Sim | Marca tarefa, item e evento como concluidos |
| `reabrir` | POST | Sim | Reabre tarefa, item e evento |
| `em_andamento` | POST | Sim | Marca tarefa como em andamento |
| `editar` | POST | Sim | Edita titulo, descricao, data, horario, duracao e prioridade |
| `adiar` | POST | Sim | Move tarefa para data futura |
| `remarcar` | POST | Sim | Atualiza data/horario e marca como remarcada |
| `cancelar` | POST | Sim | Cancela tarefa e evento vinculado |
| `iniciar_tempo` | POST | Sim | Inicia sessao simples de estudo |
| `pausar_tempo` | POST | Sim | Pausa sessao e salva minutos reais |
| `finalizar_tempo` | POST | Sim | Finaliza sessao e conclui tarefa |

Regras:

- Calcula `status_calculado = atrasada` quando tarefa pendente passou da data.
- Sincroniza `plano_estudo_itens` quando a tarefa tem `item_id`.
- Sincroniza `eventos_calendario_estudai` quando existe evento da tarefa.
- Retorna resumo semanal com total, concluidas, pendentes, atrasadas e minutos.

### 10.6 `server/api/calendario-estudai.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `mes` | GET | Nao | Eventos do mes `YYYY-MM` |
| `ano` | GET | Nao | Eventos do ano |
| `dia` | GET | Nao | Eventos de uma data |
| `eventos` | GET | Nao | Eventos entre `inicio` e `fim` |
| `atualizar_status` | POST | Sim | Atualiza status de evento |

Regras:

- Antes de buscar eventos, sincroniza tarefas que ainda nao possuem evento.
- Evento atrasado e calculado quando status e `pendente` e a data ja passou.
- Tipos de tarefa viram tipos de calendario:
  - `questoes` vira `exercicio`.
  - `revisao`, `simulado`, `resumo` preservam tipo.
  - demais viram `tarefa`.

### 10.7 `server/api/exercicios-ia.php`

Apesar do nome, este endpoint nao gera exercicios por IA em tempo real. Ele cria lotes a partir da base aprovada de questoes.

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar_por_tarefa` | GET | Nao | Carrega lote existente ou cria lote para tarefa |
| `gerar_por_tarefa` | POST | Sim | Arquiva lote ativo e cria novo |
| `carregar_semana` | GET | Nao | Lista lotes ativos da semana |
| `responder` | POST | Sim | Salva resposta objetiva do exercicio |
| `avaliar_aberta` | POST | Sim | Retorna 410, fluxo desativado |

Regras:

- Busca tarefa por `usuario_id`.
- Usa `materia` e `conteudo` da tarefa.
- Quantidade vem do perfil: `3_5` vira 5, `11_20` vira 12, `mais_20` vira 15, padrao 8.
- Salva lote em `exercicios_planejados`.
- Salva resposta em `respostas_exercicios_planejados`.
- Tambem registra em `respostas_usuario` e `questoes_respostas_usuario` quando ha `questao_id`.
- Multipla escolha e avaliada por gabarito da base.

### 10.8 `server/api/revisoes-ia.php`

Apesar do nome, revisoes por conteudo nao fabricam explicacoes novas por IA. Elas sao montadas a partir de erros reais em exercicios planejados.

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar_por_tarefa` | GET | Nao | Busca revisao ja criada para tarefa |
| `gerar_por_tarefa` | POST | Sim | Cria revisao com erros da tarefa |
| `carregar_semana` | GET | Nao | Lista revisoes da semana |
| `gerar_semana` | POST | Sim | Gera revisoes para tarefas concluidas da semana |

Regras:

- Exige erros em `respostas_exercicios_planejados`.
- Limita erros a 8 por tarefa.
- Salva revisao em `revisoes_conteudo_ia`.
- `origem` pode ser `banco` quando enum permitir.

### 10.9 `server/api/questoes.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar` | GET | Nao | Carrega questoes do dia, respostas e status |
| `responder` | POST | Sim | Salva resposta do treino diario |
| `finalizar` | POST | Sim | Fecha o dia, calcula desempenho e mostra gabarito |

Regras:

- Gera ate 20 questoes do dia via `gerarQuestoesDodia($hoje)`.
- Somente questoes aprovadas entram no treino.
- Nao permite responder questao que nao pertence ao treino do dia.
- Nao permite responder questao duplicada no mesmo dia.
- Nao permite responder depois de finalizar.
- Gabarito e explicacao so retornam depois de finalizar.
- Para finalizar, todas as questoes do dia precisam estar respondidas.

### 10.10 `server/api/revisao.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar` | GET | Nao | Lista questoes erradas ainda pendentes |
| `responder` | POST | Sim | Registra nova tentativa de revisao |
| `estatisticas` | GET | Nao | Conta pendencias por materia |

Regra central:

- Uma questao entra na revisao se o usuario errou e nao existe acerto posterior para a mesma questao.

### 10.11 `server/api/simulados-planejados.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `listar` | GET | Nao | Lista simulados planejados |
| `carregar` | GET | Nao | Carrega simulado com respostas |
| `gerar_para_tarefa` | POST | Sim | Cria simulado para uma tarefa |
| `gerar_para_semana` | POST | Sim | Cria simulado da semana ativa |
| `iniciar` | POST | Sim | Libera/inicia simulado |
| `responder` | POST | Sim | Salva resposta de questao planejada |
| `finalizar` | POST | Sim | Finaliza e calcula resultado |

Regras:

- Simulados usam questoes aprovadas da base.
- Simulado semanal exige plano semanal ativo.
- Simulado semanal usa conteudos distintos da semana.
- Simulado por tarefa usa conteudo da tarefa.
- Precisa de pelo menos 5 questoes.
- Status `bloqueado` vira `liberado` quando `data_liberacao <= CURDATE()`.
- Simulado futuro nao pode iniciar antes da data de liberacao.

### 10.12 `server/api/simulados.php`

Endpoint legado de simulados completos.

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `listar` | GET | Nao | Lista simulados ativos |
| `iniciar` | POST | Sim | Cria/reaproveita tentativa |
| `responder` | POST | Sim | Salva resposta da tentativa |
| `finalizar` | POST | Sim | Finaliza tentativa e calcula resultado |
| `historico` | GET | Nao | Lista ultimas tentativas finalizadas |

### 10.13 `server/api/estatisticas.php`

| Acao | Metodo | Finalidade |
| --- | --- | --- |
| `geral` | GET | Totais, acertos, erros, desempenho por materia/dificuldade, streak |
| `evolucao` | GET | Evolucao diaria por ate 90 dias |
| `evolucao_materia` | GET | Evolucao por materia |
| `tempo` | GET | Tempo medio/min/max |
| `estudai_geral` | GET | Indicadores do fluxo EstudAI: tarefas, progresso, plano, exercicios e simulados |

### 10.14 `server/api/conquistas.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `listar` | GET | Nao | Lista todas as conquistas por categoria |
| `verificar` | POST | Sim | Verifica criterios e desbloqueia novas |

Categorias:

- `streak`
- `questoes`
- `desempenho`
- `especial`

Criterios implementados:

- Sequencias de 3, 7, 30 e 100 dias.
- 50, 200, 500 e 1000 questoes.
- 100 acertos.
- Dia perfeito.
- Simulado com 80% ou mais.
- Meta semanal concluida.
- Primeiro dia.
- Primeiro simulado.
- Primeira anotacao.
- Revisao de questao errada.

### 10.15 `server/api/metas.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar` | GET | Nao | Cria/carrega meta semanal e recalcula progresso |
| `definir` | POST | Sim | Define meta de questoes da semana |
| `historico` | GET | Nao | Lista ultimas 12 metas |

Regras:

- Semana inicia na segunda-feira.
- Meta padrao: 100 questoes.
- Meta configuravel entre 10 e 500 questoes.
- Progresso vem de `respostas_usuario`.

### 10.16 `server/api/historico.php`

Endpoint sem `action`.

Retorna:

- Historico dos ultimos 30 dias finalizados.
- Streak atual.
- Totais gerais de dias estudados, acertos e erros.

### 10.17 `server/api/anotacoes.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `salvar` | POST | Sim | Cria/atualiza anotacao por questao |
| `carregar` | GET | Nao | Carrega anotacao de uma questao |
| `listar` | GET | Nao | Lista anotacoes com preview do enunciado |
| `remover` | POST | Sim | Remove anotacao |

Regras:

- Uma anotacao por usuario e questao.
- Texto vazio em `salvar` remove a anotacao.
- Texto limitado a 2000 caracteres.

### 10.18 `server/api/preferencias.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `carregar` | GET | Nao | Carrega tema, notificacoes e horario |
| `salvar` | POST | Sim | Salva preferencias |

Regras:

- Cria preferencias padrao se nao existirem.
- Tema permitido: `light` ou `dark`.
- Horario precisa estar no formato `HH:MM`; caso contrario, usa `08:00`.

### 10.19 `server/api/redacao.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `listar` | GET | Nao | Lista redacoes do usuario |
| `salvar` | POST | Sim | Salva rascunho de redacao ENEM |
| `analisar` | POST | Sim | Gera analise orientativa nao oficial por competencia |

Regras:

- Nao promete correcao oficial.
- Nao substitui corretor humano.
- Usa termos de apoio ao estudante, sugestoes de melhoria e estimativa nao oficial.
- Salva historico em `redacoes_enem` quando a migration `0005` estiver aplicada.

### 10.20 `server/api/ia.php`

| Metodo | Finalidade |
| --- | --- |
| GET | Informa provider, modelo e se esta configurado |
| POST | Envia mensagem educacional generica ao OpenRouter |

Regras:

- Exige login.
- POST aceita apenas `action=mensagem`.
- Mensagem e contexto sao limitados a 2000 caracteres cada.
- Usa prompt de sistema do EstudAI.
- Nao e o fluxo principal de diagnostico/plano; esses ficam em endpoints especificos.

### 10.20 `server/api/revisao-semanal-ia.php`

| Acao | Metodo | CSRF | Finalidade |
| --- | --- | --- | --- |
| `status` | GET | Nao | Indica se hoje e domingo, ultima revisao e se ha plano ativo |
| `ultima` | GET | Nao | Retorna ultima revisao semanal |
| `executar_manual` | POST | Sim | Executa revisao semanal para usuario logado |

### 10.21 `server/api/cron_revisao_semanal_ia.php`

Endpoint de cron para revisao semanal automatica.

Regras:

- Nao exige login.
- Autoriza por `ESTUDAI_CRON_TOKEN`, `token` query string ou header `X-Cron-Token`.
- Se nao houver token configurado, aceita somente requisicao local.
- Roda apenas aos domingos.
- Em request local, `force=1` permite executar fora de domingo.
- Processa todos os planos semanais ativos.

### 10.22 `server/api/cron_gerar.php`

Endpoint/script simples para gerar questoes do dia.

Uso pretendido:

- Rodar diariamente, por exemplo a meia-noite.
- Chama `gerarQuestoesDodia($hoje)`.
- Escreve mensagem em texto simples no output.

---

## 11. Inteligencia Artificial

### 11.1 Provider

O projeto usa OpenRouter por meio de `server/services/ai/openrouterClient.php`.

Configuracao:

- `OPENROUTER_API_KEY`
- `OPENROUTER_BASE_URL`
- `OPENROUTER_MODEL`
- `OPENROUTER_SITE_URL`
- `OPENROUTER_SITE_NAME`
- `OPENROUTER_TIMEOUT_SECONDS`

O cliente:

- Exige extensao cURL.
- Exige API key configurada.
- Envia `Authorization: Bearer`.
- Envia `HTTP-Referer` e `X-OpenRouter-Title`.
- Usa `response_format` quando solicitado.
- Trata HTTP 429 com excecao especifica.

Trecho relevante:

```php
$payload = [
    'model' => $options['model'] ?? $config['model'],
    'messages' => $messages,
    'temperature' => $options['temperature'] ?? 0.4,
    'max_completion_tokens' => $options['max_completion_tokens'] ?? $options['max_tokens'] ?? 900,
];
```

### 11.2 Usos permitidos da IA

Conforme o prompt de sistema:

- Analisar rotina.
- Gerar diagnostico educacional.
- Planejar calendario/plano de estudos.
- Revisar a semana.
- Sugerir ajustes de rotina.

Usos explicitamente bloqueados:

- Gerar exercicios em tempo real.
- Criar simulados em tempo real.
- Criar questoes, alternativas ou gabaritos.
- Fabricar revisoes de conteudo sem base real.

Trecho do prompt:

```php
'Use IA apenas para analisar rotina, planejar calendario de estudos, revisar a semana e sugerir ajustes de rotina.',
'Nao gere exercicios, simulados, questoes, gabaritos ou conteudo autoral em tempo real.',
'Os exercicios do produto vem de uma base propria revisada, organizada por materia, conteudo e dificuldade.'
```

### 11.3 Diagnostico

Funcao: `estudaiGerarDiagnostico(array $perfil)`

Retorno normalizado:

```json
{
  "perfil_resumido": "texto curto",
  "principais_dificuldades": [],
  "materias_prioritarias": [],
  "estrategia_recomendada": "texto curto",
  "rotina_sugerida": "texto curto",
  "estrategia_revisao": "texto curto",
  "proximos_passos": []
}
```

### 11.4 Plano semanal

Funcao: `estudaiGerarPlanoSemanal(array $entrada)`

O prompt exige:

- JSON valido.
- Datas `YYYY-MM-DD`.
- Horarios `HH:MM`.
- Apenas datas dentro da janela.
- Nunca antes de hoje.
- Nunca depois da prova.
- Apenas dias e blocos disponiveis.
- Apenas materias base.
- Tipos permitidos.
- Sem criacao de perguntas, alternativas ou gabaritos.

Retorno esperado:

```json
{
  "titulo": "Plano semanal personalizado",
  "resumo": "texto curto",
  "semana_inicio": "YYYY-MM-DD",
  "semana_fim": "YYYY-MM-DD",
  "estrategia_da_semana": "texto curto",
  "tarefas": [
    {
      "data": "YYYY-MM-DD",
      "dia_semana": "Segunda-feira",
      "hora_inicio": "19:00",
      "hora_fim": "20:00",
      "materia": "Matematica",
      "conteudo": "Funcao do 2 grau",
      "tipo": "teoria",
      "titulo": "Estudar funcao do 2 grau",
      "descricao": "texto curto",
      "tempo_estimado": 60,
      "prioridade": "media",
      "objetivo": "texto curto"
    }
  ],
  "observacoes": [],
  "alertas": []
}
```

### 11.5 Revisao semanal

Funcao: `estudaiGerarRevisaoSemanal(array $entrada)`

Analisa:

- Semana passada.
- Total de tarefas.
- Conclusao.
- Atrasos.
- Minutos planejados/concluidos.
- Exercicios respondidos.
- Simulados liberados/finalizados.
- Tarefas da proxima semana.

Retorno esperado:

```json
{
  "resumo_semana": "texto curto",
  "desempenho": {
    "classificacao": "bom",
    "percentual_conclusao": 0,
    "pontos_fortes": [],
    "pontos_fracos": []
  },
  "ajuste_pesos": {"Matematica": 3},
  "decisao": {
    "acao": "gerar_proxima_semana",
    "motivos": []
  },
  "mensagem_usuario": "texto curto"
}
```

O servico normaliza tambem `ajuste_tipo`:

- `sem_ajustes`
- `pequenos_ajustes`
- `grandes_ajustes`
- `recriacao`

---

## 12. Banco de Dados

Schema canonico: `database/schema.sql`

Banco alvo:

```sql
CREATE DATABASE IF NOT EXISTS vestibular_estudos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 12.1 Tabelas principais

| Tabela | Finalidade |
| --- | --- |
| `schema_migrations` | Controle de versoes/migrations aplicadas |
| `usuarios` | Cadastro de usuarios |
| `preferencias_usuario` | Tema, notificacoes e horario |
| `preferencias_estudo` | Preferencias de estudo em JSON legado |
| `notificacoes_usuario` | Notificacoes internas |
| `estudo_perfis` | Perfil consolidado do onboarding |
| `onboarding_respostas` | Snapshot historico das respostas do onboarding |
| `materias` | Materias/areas base |
| `conteudos` | Conteudos por materia |
| `questoes` | Banco de questoes |
| `questoes_alternativas` | Alternativas A-E das questoes |
| `questoes_do_dia` | Associacao de questoes ao treino diario |
| `respostas_usuario` | Respostas diarias e de exercicios/revisao |
| `questoes_respostas_usuario` | Historico modelo de respostas por questao |
| `desempenho_diario` | Resultado final do treino diario |
| `historico_estudos` | Historico generico de estudos |
| `estatisticas_materia` | Estatisticas agregadas por materia |
| `metas_semanais` | Metas semanais de questoes |
| `conquistas` | Catalogo de badges |
| `conquistas_usuario` | Conquistas desbloqueadas |
| `anotacoes` | Anotacoes por questao |
| `planos_estudo` | Planos semanais, anuais e legados |
| `planejamento_semanal_controle` | Controle da semana ativa |
| `plano_estudo_itens` | Itens detalhados do plano |
| `tarefas_estudo` | Tarefas executaveis pelo aluno |
| `eventos_calendario_estudai` | Eventos exibidos no calendario |
| `rotina_semanal` | Rotina semanal legada/futura |
| `sessoes_estudo` | Sessoes de estudo legadas/futuras |
| `revisoes_programadas` | Revisoes programadas legadas/futuras |
| `ia_historico` | Historico de chamadas/respostas de IA |
| `revisoes_semanais_ia` | Analises semanais com IA |
| `plano_versoes` | Versionamento de planos |
| `exercicios_planejados` | Lotes de exercicios por tarefa/semana |
| `respostas_exercicios_planejados` | Respostas dos lotes de exercicios |
| `revisoes_conteudo_ia` | Revisoes por conteudo baseadas em erros |
| `simulados` | Simulados legados |
| `simulado_questoes` | Questoes dos simulados legados |
| `simulado_tentativas` | Tentativas dos simulados legados |
| `simulado_respostas` | Respostas dos simulados legados |
| `simulados_planejados` | Simulados vinculados ao plano |
| `simulados_planejados_respostas` | Respostas dos simulados planejados |

### 12.2 Relacionamentos principais

```text
usuarios
  -> estudo_perfis
  -> onboarding_respostas
  -> planos_estudo
      -> plano_estudo_itens
      -> tarefas_estudo
          -> eventos_calendario_estudai
          -> exercicios_planejados
          -> revisoes_conteudo_ia
          -> simulados_planejados
  -> respostas_usuario
  -> desempenho_diario
  -> metas_semanais
  -> conquistas_usuario
  -> anotacoes
  -> ia_historico
  -> revisoes_semanais_ia

questoes
  -> questoes_alternativas
  -> questoes_do_dia
  -> respostas_usuario
  -> anotacoes
  -> simulado_questoes
```

### 12.3 Seed de dados

O schema cria dados iniciais:

- Materias base.
- Conteudos iniciais.
- Questoes autorais estilo ENEM.
- Alternativas A-E.
- Simulado base.
- Conquistas.

Arquivos relevantes:

- `database/schema.sql`
- `database/questions/estudai_seed_questoes_enem_autoral.sql`

A contagem encontrada no projeto indica 32 inserts de questoes no schema canonico e 32 inserts no seed autoral separado.

### 12.4 Migrations

| Migration | Finalidade |
| --- | --- |
| `0001_estudai_core_alpha.sql` | Nucleo inicial de perfil, plano, tarefas, rotina, IA e notificacoes |
| `0002_estudai_functional_core.sql` | Onboarding persistido, diagnostico, plano salvo e tarefas reais |
| `0003_estudai_annual_ai_core.sql` | Plano anual, calendario, exercicios planejados, simulados planejados e revisao semanal |
| `0004_estudai_weekly_flow_fix.sql` | Fluxo semanal principal, controle semanal e revisoes por conteudo |
| `0005_estudai_launch_core.sql` | Launch Core: tempo real simples, motivo de erro, redacao ENEM, conquistas, PWA e seguranca |

---

## 13. Regras de Negocio por Funcionalidade

### 13.1 Autenticacao

- Usuario cadastra nome, email e senha.
- Email e unico.
- Senha e armazenada com bcrypt.
- Login regenera ID da sessao.
- CSRF token e recriado no login.
- Frontend exige usuario logado para acessar `app.html`.

### 13.2 Onboarding obrigatorio

- O app abre o modal de onboarding automaticamente se o perfil nao esta completo.
- Sem onboarding, o usuario nao acessa plano, calendario, rotina, exercicios, questoes, revisao, simulados, estatisticas, conquistas e anotacoes.
- Perfil consolidado fica em `estudo_perfis`.
- Snapshot completo fica em `onboarding_respostas`.

### 13.3 Perfil ENEM

- O modo ENEM exige lingua estrangeira.
- Materias base sao automaticas.
- Reforcos nao excluem materias; apenas aumentam peso/frequencia.
- O nivel por materia ainda nao e informado manualmente; a UI indica que sera identificado por desempenho real.

### 13.4 Plano semanal

- Plano semanal e o fluxo principal desde `0.0.4-alpha` e ganhou replanejamento em `0.1.0-alpha`.
- Plano anual continua como legado.
- A IA sugere tarefas, mas o backend valida e descarta o que violar regras.
- O backend substitui planos ativos anteriores antes de salvar o novo.
- Cada tarefa gera item de plano, tarefa executavel e evento de calendario.

### 13.5 Tarefas

- Tarefa pode ter status `pendente`, `em_andamento`, `adiada`, `remarcada`, `concluida` ou `cancelada`.
- Tarefa pendente com data passada aparece como `atrasada` calculada.
- Concluir tarefa tambem conclui item do plano e evento.
- Reabrir tarefa reabre item e evento.

### 13.6 Calendario

- Eventos sao derivados de tarefas do plano.
- O endpoint sincroniza tarefas sem evento quando carrega calendario.
- Simulado futuro pode aparecer como `bloqueado`.
- Filtros do frontend permitem exibir/ocultar teoria, exercicios, revisao, simulado, resumo, concluidas e atrasadas.

### 13.7 Questoes diarias

- O sistema tenta manter 20 questoes por dia.
- Questoes sao sorteadas da base aprovada.
- Respostas ficam em `respostas_usuario`.
- O dia so finaliza quando todas as questoes do dia foram respondidas.
- Ao finalizar, grava `desempenho_diario`.

### 13.8 Exercicios planejados

- Sao criados por tarefa.
- Usam materia/conteudo da tarefa.
- Nao criam perguntas por IA.
- Resposta objetiva e comparada com gabarito da base.
- A resposta alimenta tambem historico geral de questoes.

### 13.9 Revisao de erros

Existem dois fluxos:

- Revisao geral: busca questoes que o usuario errou e ainda nao acertou depois.
- Revisao por conteudo: usa erros de exercicios planejados ligados a uma tarefa.

### 13.10 Simulados

Existem dois fluxos:

- Legado: `simulados`, `simulado_questoes`, `simulado_tentativas`, `simulado_respostas`.
- Planejado: `simulados_planejados`, vinculado a tarefa ou semana.

Simulado planejado:

- Usa questoes aprovadas.
- Pode ser bloqueado por data.
- Guarda respostas por `question_key`.
- Calcula percentual sobre respondidas ao finalizar.

### 13.11 Metas

- Meta semanal comeca na segunda-feira.
- Meta padrao e 100 questoes.
- O progresso e recalculado por respostas da semana.
- Pode ser marcada como concluida automaticamente.

### 13.12 Conquistas

- Sistema de badges com catalogo no banco.
- Verificacao calcula criterios dinamicamente.
- Conquistas desbloqueadas sao persistidas em `conquistas_usuario`.

### 13.13 Anotacoes

- Uma anotacao por usuario e questao.
- Texto vazio remove a anotacao.
- Listagem mostra preview do enunciado.

### 13.14 Revisao semanal com IA

O servico `weeklyReview.php`:

1. Calcula a semana passada e a proxima semana.
2. Busca plano semanal ativo.
3. Mede tarefas, atrasos, conclusao, tempo, exercicios e simulados.
4. Envia entrada estruturada para a IA.
5. Salva revisao em `revisoes_semanais_ia`.
6. Registra versao em `plano_versoes`.
7. Pode ajustar prioridades de tarefas futuras.
8. Pode gerar e salvar um novo plano para a proxima semana.

Janela semanal:

```php
function estudaiWeeklyWindow(?DateTimeImmutable $base = null): array {
    $base = $base ?: new DateTimeImmutable('today');
    $thisMonday = $base->modify('monday this week');
    $lastMonday = $thisMonday->modify('-7 days');
    $lastSunday = $thisMonday->modify('-1 day');
    $nextMonday = $thisMonday->modify('+7 days');
    $nextSunday = $thisMonday->modify('+13 days');
    return [
        'semana_inicio' => $lastMonday->format('Y-m-d'),
        'semana_fim' => $lastSunday->format('Y-m-d'),
        'proxima_inicio' => $nextMonday->format('Y-m-d'),
        'proxima_fim' => $nextSunday->format('Y-m-d'),
    ];
}
```

---

## 14. Busca de Questoes Aprovadas

Funcao central: `questoesBuscarAprovadas(PDO $db, ?string $materia, ?string $conteudo, int $quantidade = 5)`

Ela:

- Exige tabela `questoes`.
- Limita quantidade entre 1 e 40.
- Busca somente status aprovados:
  - `aprovada`
  - `revisada`
  - `aprovado`
  - `revisado`
  - `ativo`
- Tenta buscar por materia e conteudo.
- Depois tenta por materia.
- Depois tenta geral.
- Anexa alternativas.
- Filtra questoes incompletas.
- Retorna escopo e aviso.

Trecho relevante:

```php
function questoesApprovedWhere(string $alias = 'q'): string {
    return "{$alias}.status IN ('aprovada','revisada','aprovado','revisado','ativo')";
}
```

---

## 15. Seguranca

Medidas existentes:

- Senhas com bcrypt.
- Sessao com cookie `httponly` e `SameSite=Lax`.
- Regeneracao de sessao no login.
- Respostas JSON com `X-Content-Type-Options: nosniff`.
- Escopo por `usuario_id` na maioria dos endpoints.
- IA restrita ao backend.
- CSRF nos fluxos novos e criticos.
- Sanitizacao e limite de tamanho em varios payloads.
- Verificacao defensiva de tabelas/colunas antes de usar recursos novos.
- Logs tecnicos em `storage/logs/app.log` quando gravavel.

Pontos de atencao:

- Completar CSRF nos endpoints legados.
- Evitar expor `.env`.
- Rotacionar credenciais caso tenham sido compartilhadas fora do ambiente local.
- Adicionar rate limit explicito para acoes de IA; a documentacao historica cita rate limit, mas nao ha middleware `rateLimit` encontrado no codigo atual.
- Revisar permissoes do endpoint `cron_revisao_semanal_ia.php` em producao, sempre com `ESTUDAI_CRON_TOKEN`.
- Criar testes automatizados para os fluxos criticos.

---

## 16. Logs e Erros

Funcao de log:

```php
function logTechnicalError(string $context, Throwable $error): void {
    $line = '[' . date('c') . '] ' . $context . ': ' . $error->getMessage() . PHP_EOL;
    $path = dirname(__DIR__, 2) . '/storage/logs/app.log';
    if (is_dir(dirname($path)) && is_writable(dirname($path))) {
        @file_put_contents($path, $line, FILE_APPEND);
        return;
    }
    error_log(trim($line));
}
```

Endpoints que chamam log tecnico explicitamente:

- `questoes.php`
- `exercicios-ia.php`
- `simulados-planejados.php`
- `ia.php`
- `database.php` quando falha conexao e a funcao existe

---

## 17. Status Atual da Versao

Versao atual identificada no codigo:

```php
define('ESTUDAI_VERSION', '0.1.0-alpha');
```

Entregue ate `0.1.0-alpha`:

- Onboarding obrigatorio.
- ENEM com grade fixa.
- Disponibilidade por dia e horario real.
- Plano semanal como fluxo principal.
- Validacao rigorosa de datas, horarios, materias e disponibilidade.
- Exercicios por tarefa usando base aprovada.
- Revisoes por conteudo usando erros reais.
- Simulados planejados por tarefa/semana usando base aprovada.
- Calendario mensal/anual persistido.
- Revisao semanal com IA e cron.
- Dashboard com proxima acao e progresso do nucleo.
- Dashboard Launch Core com resumo semanal, atrasos, proxima acao e atalhos.
- Rotina com edicao, remarcacao, cancelamento, status em andamento e controle simples de tempo.
- Replanejamento semanal com motivo.
- Redacao ENEM inicial com analise orientativa nao oficial.
- CSRF nos endpoints legados com POST.
- PWA com pagina offline.

Pendencias conhecidas pelo roadmap/codigo:

- Testes automatizados.
- Avaliacao IA de respostas abertas.
- Notificacoes push reais.
- Melhorias de acessibilidade/foco em modais.
- Historico mais rico de diagnosticos e planos.

---

## 18. Comandos Uteis

Validar sintaxe de PHP:

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
php -l server/services/weeklyReview.php
php -l server/services/ai/estudaiService.php
```

Servidor local:

```powershell
php -S localhost:8080
```

Gerar questoes do dia:

```powershell
php server/api/cron_gerar.php
```

Executar cron semanal local fora de domingo, apenas em ambiente local:

```text
http://localhost:8080/server/api/cron_revisao_semanal_ia.php?force=1
```

---

## 19. Arquivos Mais Importantes

| Arquivo | Importancia |
| --- | --- |
| `app/public/app.html` | Estrutura visual do painel |
| `app/src/pages/app.js` | Estado e comportamento principal do frontend |
| `app/src/services/http.js` | Wrapper HTTP e CSRF |
| `server/helpers/helpers.php` | Base compartilhada do backend |
| `server/api/onboarding.php` | Perfil obrigatorio |
| `server/api/plano-estudos.php` | Geracao e persistencia do plano |
| `server/api/tarefas-estudo.php` | Execucao da rotina |
| `server/api/calendario-estudai.php` | Eventos do calendario |
| `server/api/exercicios-ia.php` | Exercicios por tarefa usando banco |
| `server/api/revisoes-ia.php` | Revisoes baseadas em erros reais |
| `server/api/simulados-planejados.php` | Simulados vinculados ao plano |
| `server/services/weeklyReview.php` | Revisao semanal e proxima semana |
| `server/services/ai/prompts.php` | Contratos de IA |
| `server/services/ai/estudaiService.php` | Chamadas e normalizacao de IA |
| `database/schema.sql` | Modelo canonico do banco |

---

## 20. Resumo Executivo

O EstudAI ja possui um nucleo funcional consistente para estudo semanal orientado por IA. A parte mais madura do projeto esta no fluxo `onboarding -> diagnostico -> plano semanal -> tarefas -> calendario -> exercicios/simulados -> revisao semanal`.

O desenho atual e cuidadoso em um ponto importante: a IA planeja e analisa, mas nao cria a base avaliativa. Questoes, alternativas, gabaritos, exercicios e simulados partem do banco aprovado. Isso reduz risco de alucinacao em conteudo avaliativo e deixa o sistema mais auditavel.

Os proximos avancos tecnicos mais importantes sao completar CSRF nos endpoints legados, adicionar testes automatizados, melhorar edicao/remarcacao de tarefas e fortalecer operacao de producao com credenciais protegidas, logs revisados e cron autenticado.
