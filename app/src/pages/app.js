// ====================================
// STATE
// ====================================
const state = {
  questoes: [],
  respostas: {},
  gabarito: null,
  finalizado: false,
  currentIdx: 0,
  timerInterval: null,
  timerSec: 0,
  theme: 'light',
  // Revisao
  revisaoQuestoes: [],
  revisaoIdx: 0,
  revisaoRespostas: {},
  // Simulado
  simulados: [],
  simAtivo: null,
  simQuestoes: [],
  simRespostas: {},
  simIdx: 0,
  simTimer: null,
  simTimerSec: 0,
  simFinalizado: false,
  simGabarito: null,
  // Conquistas
  conquistas: {},
  // Preferencias
  prefs: { tema: 'light', notificacoes: false, horario_lembrete: '08:00' },
  onboarding: null,
  csrfToken: '',
  estudaiStats: null,
  diagnostico: null,
  plano: null,
  tarefasHoje: [],
  tarefasSemana: [],
  tarefasAtrasadas: [],
  tarefasRecentes: [],
  calendarDate: new Date(),
  calendarEvents: [],
  calendarYearEvents: [],
  selectedCalendarDay: new Date().toISOString().slice(0, 10),
  currentExerciseTaskId: null,
  currentExerciseLote: null,
  plannedSimulados: [],
  plannedSimAtivo: null,
  onboardingCompleto: false,
  onboardingStep: 0,
  currentReviewTaskId: null,
  currentReview: null,
  redacoes: [],
  redacaoAtualId: null
};

const iconSvg = {
  award: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v4a5 5 0 01-10 0V4z" /><path stroke-linecap="round" stroke-linejoin="round" d="M5 6H3a2 2 0 002 4h2M19 6h2a2 2 0 01-2 4h-2" /></svg>',
  book: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13M12 6.253C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253M12 6.253C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>',
  check: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
  crown: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 16l2-9 5 5 5-5 2 9H5zM5 20h14" /></svg>',
  flag: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5v16M5 5h11l-1.5 4L16 13H5" /></svg>',
  flame: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4m0 0l3-3m-3 3L9 4m9 9a6 6 0 11-12 0c0-2.5 1.5-4.5 3.2-5.8.8 1.8 2.2 3.2 4.1 4.1A8.1 8.1 0 0018 13z" /></svg>',
  medal: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 2l3 6 3-6M12 8a6 6 0 100 12 6 6 0 000-12z" /></svg>',
  pencil: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L9.38 17.273 5 18l.727-4.38 11.135-9.133z" /></svg>',
  refresh: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.6 16A7.5 7.5 0 0018 18.4M18.4 8A7.5 7.5 0 006 5.6" /></svg>',
  rocket: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-6 6M14 4h6v6c0 5-4 9-9 9H8v-3C8 11 9 8 14 4z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18l-2 2" /></svg>',
  sparkles: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5zM19 16l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2z" /></svg>',
  star: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.9 5.88 6.5.95-4.7 4.58 1.1 6.47L12 17.82l-5.8 3.06 1.1-6.47-4.7-4.58 6.5-.95L12 3z" /></svg>',
  target: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z" /></svg>',
  clipboard: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6M9 5a3 3 0 016 0M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 12h6M9 16h6" /></svg>'
};
iconSvg.fire = iconSvg.flame;
iconSvg.trophy = iconSvg.award;

function resultIcon(pct) {
  if (pct >= 80) return iconSvg.award;
  if (pct >= 50) return iconSvg.check;
  return iconSvg.book;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[char]);
}

function safeArray(value) {
  if (Array.isArray(value)) return value;
  if (value == null || value === '') return [];
  if (typeof value === 'string') return [value];
  if (typeof value === 'object') return Object.values(value).filter(item => item != null && item !== '');
  return [];
}

function labelTipoTarefa(tipo) {
  switch (tipo) {
    case 'teoria': return 'Teoria';
    case 'questoes':
    case 'exercicio': return 'Questoes';
    case 'revisao': return 'Revisao';
    case 'simulado': return 'Simulado';
    case 'resumo': return 'Resumo';
    case 'misto': return 'Misto';
    case 'custom': return 'Atividade';
    default: return 'Atividade';
  }
}

function labelPrioridade(prioridade) {
  switch (prioridade) {
    case 'baixa': return 'Baixa';
    case 'media': return 'Media';
    case 'alta': return 'Alta';
    default: return 'Media';
  }
}

function localDateKey(date = new Date()) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function offsetDateKey(dateKey, offset) {
  const parts = String(dateKey || '').split('-').map(Number);
  if (parts.length !== 3 || parts.some(Number.isNaN)) return '';
  return localDateKey(new Date(parts[0], parts[1] - 1, parts[2] + offset));
}

function dashboardTaskTiming(task) {
  const date = task?.data_prevista || '';
  const today = localDateKey();
  const tomorrow = offsetDateKey(today, 1);

  if (!date) {
    return {
      label: 'Sem data',
      description: 'Tarefa pendente sem data definida.',
      className: 'is-unscheduled',
      badgeClass: 'badge-warning'
    };
  }

  if (date < today) {
    return {
      label: 'Atrasada',
      description: `Pendente desde ${formatDate(date)}.`,
      className: 'is-overdue',
      badgeClass: 'badge-danger'
    };
  }

  if (date === today) {
    return {
      label: 'Para fazer hoje',
      description: 'Esta tarefa e de hoje.',
      className: 'is-today',
      badgeClass: 'badge-warning'
    };
  }

  if (date === tomorrow) {
    return {
      label: 'Tarefa de amanha',
      description: 'Esta pendencia e para amanha.',
      className: 'is-tomorrow',
      badgeClass: 'badge-success'
    };
  }

  return {
    label: `Proxima tarefa`,
    description: `Pendente para ${formatDate(date)}.`,
    className: 'is-future',
    badgeClass: 'badge-success'
  };
}

function taskActionLabel(task) {
  const tipo = task.tipo || task.metadata?.tipo_tarefa || 'custom';
  if (tipo === 'teoria') return 'Abrir estudo';
  if (tipo === 'questoes' || tipo === 'exercicio') return 'Praticar questoes';
  if (tipo === 'revisao') return 'Revisar';
  if (tipo === 'simulado' && task.data_prevista && task.data_prevista > localDateKey()) return `Liberado em ${formatDate(task.data_prevista)}`;
  if (tipo === 'simulado') return 'Iniciar simulado';
  if (tipo === 'resumo') return 'Criar resumo';
  return 'Ver atividade';
}

// ====================================
// INIT
// ====================================
(async function init() {
  const r = await apiFetch(apiEndpoint('auth', 'action=status'));
  const d = await r.json();
  if (!d.logado) { window.location.href = 'index.html'; return; }

  state.csrfToken = d.csrf_token || '';
  window.EstudAICsrfToken = state.csrfToken;
  if (window.EstudAIConfig) window.EstudAIConfig.csrfToken = state.csrfToken;

  document.getElementById('user-name-display').textContent = d.nome.split(' ')[0];
  const firstName = d.nome.split(' ')[0];
  const greeting = document.getElementById('dashboard-greeting');
  if (greeting) greeting.textContent = `Bom estudo, ${firstName}. Sua semana em um lugar so.`;
  document.getElementById('user-avatar').textContent = d.nome[0].toUpperCase();
  document.getElementById('mobile-user-name').textContent = d.nome;
  document.getElementById('mobile-user-avatar').textContent = d.nome[0].toUpperCase();
  setupRoutineChecklist();
  await loadOnboarding();
  setupOnboardingWizard();

  if (!state.onboardingCompleto) {
    renderOnboardingSummary();
    renderDashboardPlan();
    renderDashboardRoutine();
    openOnboardingModal(true);
    return;
  }

  await loadPreferencias();
  await loadQuestoes();
  await loadHistorico();
  await loadMeta();
  await loadRevisaoStats();
  await loadEstudaiCore();
  verificarConquistas();
})();

// ====================================
// THEME
// ====================================
function toggleTheme() {
  state.theme = state.theme === 'dark' ? 'light' : 'dark';
  applyTheme();
  salvarPreferencias();
}

function applyTheme() {
  const html = document.documentElement;
  const icon = document.getElementById('theme-icon');
  
  if (state.theme === 'dark') {
    html.classList.add('dark');
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />';
  } else {
    html.classList.remove('dark');
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />';
  }
  
  document.getElementById('setting-theme').checked = state.theme === 'dark';
}

function handleThemeChange() {
  state.theme = document.getElementById('setting-theme').checked ? 'dark' : 'light';
  applyTheme();
  salvarPreferencias();
}

// ====================================
// PREFERENCIAS
// ====================================
async function loadPreferencias() {
  try {
    const r = await apiFetch(apiEndpoint('preferencias', 'action=carregar'));
    const d = await r.json();
    if (d.ok) {
      state.prefs = d.preferencias;
      state.theme = d.preferencias.tema;
      applyTheme();
      
      document.getElementById('setting-notif').checked = d.preferencias.notificacoes;
      document.getElementById('setting-horario').value = d.preferencias.horario_lembrete;
      document.getElementById('horario-setting').style.display = d.preferencias.notificacoes ? 'flex' : 'none';
    }
  } catch (e) {}
}

async function salvarPreferencias() {
  const fd = new FormData();
  fd.append('action', 'salvar');
  fd.append('tema', state.theme);
  fd.append('notificacoes', state.prefs.notificacoes ? 1 : 0);
  fd.append('horario_lembrete', state.prefs.horario_lembrete);
  await apiFetch(apiEndpoint('preferencias'), { method: 'POST', body: fd });
}

function handleNotifChange() {
  state.prefs.notificacoes = document.getElementById('setting-notif').checked;
  document.getElementById('horario-setting').style.display = state.prefs.notificacoes ? 'flex' : 'none';
  salvarPreferencias();
  
  if (state.prefs.notificacoes && 'Notification' in window) {
    Notification.requestPermission();
  }
}

function handleHorarioChange() {
  state.prefs.horario_lembrete = document.getElementById('setting-horario').value;
  salvarPreferencias();
}

// ====================================
// NAVIGATION
// ====================================
function showSection(id) {
  if (!state.onboardingCompleto && !['dashboard', 'perfil'].includes(id)) {
    openOnboardingModal(true);
    showToast('warning', 'Formulario obrigatorio', 'Antes de gerar seu plano, precisamos entender sua rotina de estudos.');
    return;
  }
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.mobile-nav-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.section === id);
  });
  document.getElementById('sec-' + id).classList.add('active');
  
  const tabs = document.querySelectorAll('.nav-tab');
  const map = {dashboard: 0, calendario: 1, plano: 2, rotina: 3, exercicios: 4, questoes: 5, revisao: 6, simulados: 7, redacao: 8, estatisticas: 9, conquistas: 10, anotacoes: 11};
  tabs[map[id]]?.classList.add('active');

  if (id === 'questoes' && !state.finalizado && state.questoes.length) startTimer();
  if (id !== 'questoes') stopTimer();
  
  if (id === 'revisao' && !state.currentReviewTaskId) loadRevisao('todas');
  if (id === 'calendario') loadCalendarMonth();
  if (id === 'exercicios') renderExercises();
  if (id === 'simulados') {
    loadSimulados();
    loadPlannedSimulados();
  }
  if (id === 'estatisticas') loadEstatisticas();
  if (id === 'conquistas') loadConquistas();
  if (id === 'anotacoes') loadAnotacoes();
  if (id === 'redacao') loadRedacoes();
  if (id === 'plano') loadPlanoAtivo();
  if (id === 'rotina') loadRotina();
  if (id === 'perfil') renderProfileDetails();
}

function setupRoutineChecklist() {
  const items = document.querySelectorAll('#sec-rotina .routine-item input');
  const saved = JSON.parse(localStorage.getItem('estudai:routine') || '{}');
  items.forEach((input, index) => {
    input.checked = !!saved[index];
    input.addEventListener('change', () => {
      saved[index] = input.checked;
      localStorage.setItem('estudai:routine', JSON.stringify(saved));
    });
  });
}

// ====================================
// ONBOARDING OBRIGATORIO
// ====================================
const DIA_LABELS = {
  segunda: 'Segunda-feira',
  terca: 'Terca-feira',
  quarta: 'Quarta-feira',
  quinta: 'Quinta-feira',
  sexta: 'Sexta-feira',
  sabado: 'Sabado',
  domingo: 'Domingo'
};

function getMateriasBasePorModo(modo, linguaEstrangeira, materiasManuais = []) {
  if (modo === 'enem') {
    return [
      'Redacao', 'Linguagens', 'Matematica', 'Ciencias Humanas', 'Ciencias da Natureza',
      'Portugues', 'Literatura', 'Historia', 'Geografia', 'Filosofia', 'Sociologia',
      'Biologia', 'Quimica', 'Fisica', linguaEstrangeira === 'espanhol' ? 'Espanhol' : 'Ingles'
    ];
  }
  return materiasManuais.map((item) => item.trim()).filter(Boolean).slice(0, 30);
}

function normalizeOnboardingData(data) {
  if (!data) return null;
  const disponibilidade = data.disponibilidade || data.disponibilidade_json || data.horarios || {};
  const materias = data.materias_base || data.materias_base_json || [];
  const reforcos = data.reforcos || data.reforcos_json || {};
  return {
    ...data,
    modo_estudo: data.modo_estudo || data.objetivo || 'enem',
    dias: data.dias_semana || data.dias || Object.keys(disponibilidade),
    dias_semana: data.dias_semana || data.dias || Object.keys(disponibilidade),
    disponibilidade,
    horarios: disponibilidade,
    materias_base: materias,
    reforcos,
    conteudos_reforco: data.conteudos_reforco || [],
    obstaculos: data.obstaculos || [],
    notificacoes: !!data.notificacoes
  };
}

async function loadOnboarding() {
  try {
    const r = await apiFetch(apiEndpoint('onboarding', 'action=status'));
    const d = await r.json();
    state.onboardingCompleto = !!(d.ok && d.onboarding_completo);
    state.onboarding = state.onboardingCompleto ? normalizeOnboardingData(d.perfil) : null;
    if (state.onboarding) fillOnboardingForm(state.onboarding);
  } catch (e) {
    state.onboardingCompleto = false;
    state.onboarding = null;
  }
  renderOnboardingSummary();
}

function splitList(value) {
  return String(value || '').split(',').map((item) => item.trim()).filter(Boolean).slice(0, 30);
}

function checkedValues(selector) {
  return Array.from(document.querySelectorAll(selector)).filter((input) => input.checked).map((input) => input.value);
}

function setupOnboardingWizard() {
  document.querySelectorAll('[data-onb-step]').forEach((btn) => {
    btn.addEventListener('click', () => setOnboardingStep(Number(btn.dataset.onbStep)));
  });
  document.querySelectorAll('#onb-dias input').forEach((input) => {
    input.addEventListener('change', () => renderScheduleBlocks());
  });
  const objetivo = document.getElementById('onb-objetivo');
  if (objetivo) objetivo.addEventListener('change', handleObjectiveChange);
  const lingua = document.getElementById('onb-lingua');
  if (lingua) lingua.addEventListener('change', renderMateriasReforcos);
  const materias = document.getElementById('onb-materias-manuais');
  if (materias) materias.addEventListener('input', renderMateriasReforcos);
  setOnboardingStep(0);
  handleObjectiveChange();
  if (state.onboarding) {
    Object.entries(state.onboarding.reforcos || {}).forEach(([materia, peso]) => {
      const row = Array.from(document.querySelectorAll('.reinforcement-row')).find((item) => item.dataset.materia === materia);
      const radio = row?.querySelector(`input[value="${Number(peso)}"]`);
      if (radio) radio.checked = true;
    });
  } else {
    renderScheduleBlocks();
  }
}

function setOnboardingStep(step) {
  state.onboardingStep = Math.max(0, Math.min(4, step));
  document.querySelectorAll('.onboarding-step').forEach((el, index) => {
    el.classList.toggle('active', index === state.onboardingStep);
  });
  document.querySelectorAll('[data-onb-step]').forEach((btn) => {
    btn.classList.toggle('active', Number(btn.dataset.onbStep) === state.onboardingStep);
  });
  const prev = document.getElementById('onboarding-prev-btn');
  const next = document.getElementById('onboarding-next-btn');
  const save = document.getElementById('onboarding-save-btn');
  if (prev) prev.disabled = state.onboardingStep === 0;
  if (next) next.classList.toggle('hidden', state.onboardingStep === 4);
  if (save) save.classList.toggle('hidden', state.onboardingStep !== 4);
  if (state.onboardingStep === 4) renderOnboardingReview();
}

function nextOnboardingStep() {
  const error = validateOnboardingStep(state.onboardingStep);
  if (error) {
    setOnboardingAlert(error, 'error');
    return;
  }
  setOnboardingAlert('', '');
  setOnboardingStep(state.onboardingStep + 1);
}

function prevOnboardingStep() {
  setOnboardingAlert('', '');
  setOnboardingStep(state.onboardingStep - 1);
}

function handleObjectiveChange() {
  const modo = document.getElementById('onb-objetivo')?.value || 'enem';
  document.getElementById('onb-lingua-field')?.classList.toggle('hidden', modo !== 'enem');
  document.getElementById('onb-materias-field')?.classList.toggle('hidden', modo === 'enem');
  renderMateriasReforcos();
}

function currentBaseMaterias() {
  const modo = document.getElementById('onb-objetivo')?.value || 'enem';
  const lingua = document.getElementById('onb-lingua')?.value || 'ingles';
  const manuais = splitList(document.getElementById('onb-materias-manuais')?.value || '');
  return getMateriasBasePorModo(modo, lingua, manuais);
}

function renderMateriasReforcos() {
  const list = document.getElementById('onb-materias-base-list');
  const reforcos = document.getElementById('onb-reforcos-list');
  const materias = currentBaseMaterias();
  if (list) {
    list.innerHTML = materias.length
      ? materias.map((m) => `<span class="soft-chip">${escapeHtml(m)}</span>`).join('')
      : '<span class="text-muted">Informe materias ou conteudos para liberar os reforcos.</span>';
  }
  if (reforcos) {
    reforcos.innerHTML = materias.map((materia) => `
      <div class="reinforcement-row" data-materia="${escapeHtml(materia)}">
        <strong>${escapeHtml(materia)}</strong>
        <div class="segmented">
          <label><input type="radio" name="ref-${escapeHtml(materia)}" value="1" checked> Normal</label>
          <label><input type="radio" name="ref-${escapeHtml(materia)}" value="2"> Medio</label>
          <label><input type="radio" name="ref-${escapeHtml(materia)}" value="3"> Alto</label>
        </div>
      </div>
    `).join('');
  }
}

function renderScheduleBlocks() {
  const container = document.getElementById('onb-horarios');
  if (!container) return;
  const selected = checkedValues('#onb-dias input:checked');
  container.innerHTML = selected.length ? selected.map((dia) => scheduleDayHtml(dia)).join('') : '<p class="text-muted">Selecione pelo menos um dia disponivel.</p>';
}

function scheduleDayHtml(dia) {
  return `
    <div class="schedule-day" data-dia="${escapeHtml(dia)}">
      <div class="schedule-day-head">
        <strong>${escapeHtml(DIA_LABELS[dia] || dia)}</strong>
        <button class="btn-secondary btn-sm" type="button" onclick="addScheduleBlock('${escapeHtml(dia)}')">Adicionar horario</button>
      </div>
      <div class="schedule-blocks" data-dia-blocks="${escapeHtml(dia)}">
        ${scheduleBlockHtml()}
      </div>
    </div>
  `;
}

function scheduleBlockHtml(inicio = '', fim = '') {
  return `
    <div class="schedule-block">
      <label>Das <input type="time" class="input" data-field="inicio" value="${escapeHtml(inicio)}"></label>
      <label>ate <input type="time" class="input" data-field="fim" value="${escapeHtml(fim)}"></label>
      <button class="btn-secondary btn-sm" type="button" onclick="removeScheduleBlock(this)">Remover</button>
    </div>
  `;
}

function addScheduleBlock(dia) {
  const target = document.querySelector(`[data-dia-blocks="${CSS.escape(dia)}"]`);
  if (target) target.insertAdjacentHTML('beforeend', scheduleBlockHtml());
}

function removeScheduleBlock(button) {
  const blocks = button.closest('.schedule-blocks');
  if (blocks && blocks.children.length > 1) {
    button.closest('.schedule-block')?.remove();
  }
}

function collectScheduleBlocks() {
  const result = {};
  document.querySelectorAll('.schedule-day').forEach((day) => {
    const dia = day.dataset.dia;
    const blocks = [];
    day.querySelectorAll('.schedule-block').forEach((block) => {
      const inicio = block.querySelector('[data-field="inicio"]')?.value || '';
      const fim = block.querySelector('[data-field="fim"]')?.value || '';
      if (inicio || fim) blocks.push({ inicio, fim });
    });
    if (blocks.length) result[dia] = blocks;
  });
  return result;
}

function collectReforcos() {
  return Array.from(document.querySelectorAll('.reinforcement-row')).reduce((acc, row) => {
    const materia = row.dataset.materia;
    acc[materia] = Number(row.querySelector('input[type="radio"]:checked')?.value || 1);
    return acc;
  }, {});
}

function collectOnboardingForm() {
  const modo = document.getElementById('onb-objetivo')?.value || 'enem';
  const lingua = document.getElementById('onb-lingua')?.value || '';
  const materias = currentBaseMaterias();
  const disponibilidade = collectScheduleBlocks();
  return {
    objetivo: modo,
    modo_estudo: modo,
    data_prova: document.getElementById('onb-data-prova')?.value || '',
    lingua_estrangeira: modo === 'enem' ? lingua : '',
    materias_base: materias,
    disponibilidade,
    horarios: disponibilidade,
    reforcos: collectReforcos(),
    conteudos_reforco: splitList(document.getElementById('onb-conteudos-reforco')?.value),
    preferencia: document.getElementById('onb-preferencia')?.value || 'misto',
    preferencia_estudo: document.getElementById('onb-preferencia')?.value || 'misto',
    intensidade: document.getElementById('onb-intensidade')?.value || 'ia',
    exercicios_dia: document.getElementById('onb-exercicios-dia')?.value || 'ia',
    frequencia_simulados: document.getElementById('onb-frequencia-simulados')?.value || 'ia',
    obstaculos: checkedValues('#onb-obstaculos input:checked'),
    informacao_livre: document.getElementById('onb-informacao-livre')?.value.trim() || '',
    meta_semanal: document.getElementById('onb-meta-semanal')?.value.trim() || '',
    notificacoes: !!document.getElementById('onb-notificacoes')?.checked,
    salvo_em: new Date().toISOString()
  };
}

function validateOnboardingStep(step) {
  const data = collectOnboardingForm();
  if (step === 0) {
    if (!data.modo_estudo) return 'Escolha seu objetivo principal.';
    if (data.modo_estudo === 'enem' && !data.lingua_estrangeira) return 'Escolha a lingua estrangeira do ENEM.';
    if (data.modo_estudo !== 'enem' && !data.materias_base.length) return 'Informe pelo menos uma materia ou conteudo.';
  }
  if (step === 1) {
    const dias = Object.keys(data.disponibilidade);
    if (!dias.length) return 'Selecione pelo menos um dia e horario valido.';
    for (const dia of dias) {
      const seen = [];
      for (const bloco of data.disponibilidade[dia]) {
        if (!bloco.inicio || !bloco.fim) return 'Preencha hora inicial e final dos blocos.';
        if (bloco.fim <= bloco.inicio) return 'Hora final deve ser maior que hora inicial.';
        if (seen.some((prev) => bloco.inicio < prev.fim && bloco.fim > prev.inicio)) return 'Ha horarios sobrepostos no mesmo dia.';
        seen.push(bloco);
      }
    }
  }
  if (step === 2 && !data.materias_base.length) return 'Defina as materias base antes de escolher reforcos.';
  return '';
}

function fillOnboardingForm(data) {
  const setValue = (id, value) => {
    const el = document.getElementById(id);
    if (el && value !== undefined && value !== null) el.value = value;
  };
  setValue('onb-objetivo', data.modo_estudo || data.objetivo);
  setValue('onb-data-prova', data.data_prova);
  setValue('onb-lingua', data.lingua_estrangeira || 'ingles');
  setValue('onb-materias-manuais', (data.modo_estudo || data.objetivo) === 'enem' ? '' : (data.materias_base || []).join(', '));
  setValue('onb-conteudos-reforco', (data.conteudos_reforco || []).join(', '));
  setValue('onb-intensidade', data.intensidade || 'ia');
  setValue('onb-exercicios-dia', data.exercicios_dia || 'ia');
  setValue('onb-frequencia-simulados', data.frequencia_simulados || 'ia');
  setValue('onb-informacao-livre', data.informacao_livre);
  setValue('onb-meta-semanal', data.meta_semanal);
  document.querySelectorAll('#onb-dias input').forEach((input) => {
    input.checked = Object.keys(data.disponibilidade || {}).includes(input.value);
  });
  renderScheduleBlocks();
  Object.entries(data.disponibilidade || {}).forEach(([dia, blocks]) => {
    const target = document.querySelector(`[data-dia-blocks="${CSS.escape(dia)}"]`);
    if (target) target.innerHTML = blocks.map((b) => scheduleBlockHtml(b.inicio, b.fim)).join('');
  });
  document.querySelectorAll('#onb-obstaculos input').forEach((input) => {
    input.checked = (data.obstaculos || []).includes(input.value);
  });
  const notif = document.getElementById('onb-notificacoes');
  if (notif) notif.checked = !!data.notificacoes;
  handleObjectiveChange();
  Object.entries(data.reforcos || {}).forEach(([materia, peso]) => {
    const row = Array.from(document.querySelectorAll('.reinforcement-row')).find((item) => item.dataset.materia === materia);
    const radio = row?.querySelector(`input[value="${Number(peso)}"]`);
    if (radio) radio.checked = true;
  });
}

function openOnboardingModal(force = false) {
  if (state.onboarding) fillOnboardingForm(state.onboarding);
  setOnboardingAlert(force ? 'Antes de gerar seu plano, precisamos entender sua rotina de estudos.' : '', force ? 'info' : '');
  document.getElementById('modal-onboarding')?.classList.add('active');
}

function setOnboardingAlert(message, type) {
  const el = document.getElementById('onboarding-alert');
  if (!el) return;
  el.textContent = message || '';
  el.className = 'form-alert' + (type ? ` ${type}` : '');
}

async function saveOnboarding() {
  for (let step = 0; step <= 3; step++) {
    const error = validateOnboardingStep(step);
    if (error) {
      setOnboardingStep(step);
      setOnboardingAlert(error, 'error');
      return;
    }
  }
  const data = collectOnboardingForm();
  const btn = document.getElementById('onboarding-save-btn');
  btn.disabled = true;
  btn.classList.add('loading-state');
  setOnboardingAlert('Salvando perfil e preparando diagnostico...', 'info');

  try {
    const r = await apiFetch(apiEndpoint('onboarding', 'action=salvar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...data, respostas: data })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel salvar o perfil.');

    state.onboardingCompleto = true;
    state.onboarding = normalizeOnboardingData(d.perfil || data);
    renderOnboardingSummary();
    closeModal('modal-onboarding');
    showToast('success', 'Perfil salvo', 'Agora gere seu plano semanal.');
    try { await gerarDiagnostico(); } catch (e) {}
    await loadPreferencias();
    await loadQuestoes();
    await loadHistorico();
    await loadMeta();
    await loadRevisaoStats();
    await loadEstudaiCore();
    verificarConquistas();
    showSection('plano');
  } catch (e) {
    setOnboardingAlert(e.message || 'Erro ao salvar o perfil.', 'error');
  } finally {
    btn.disabled = false;
    btn.classList.remove('loading-state');
  }
}

function renderOnboardingReview() {
  const el = document.getElementById('onb-review');
  if (!el) return;
  const data = collectOnboardingForm();
  const horarios = Object.entries(data.disponibilidade).map(([dia, blocks]) => `${DIA_LABELS[dia] || dia}: ${blocks.map((b) => `${b.inicio}-${b.fim}`).join(', ')}`).join(' | ');
  const reforcos = Object.entries(data.reforcos).filter(([, peso]) => Number(peso) > 1).map(([m, p]) => `${m}: ${p === 3 ? 'alto' : 'medio'}`).join(', ') || 'Sem reforco extra';
  el.innerHTML = `
    <div class="detail-grid">
      <span><strong>Objetivo</strong>${escapeHtml(data.modo_estudo)}</span>
      <span><strong>Data</strong>${escapeHtml(data.data_prova || 'Sem data definida')}</span>
      <span><strong>Lingua</strong>${escapeHtml(data.modo_estudo === 'enem' ? data.lingua_estrangeira : 'Nao se aplica')}</span>
      <span><strong>Horarios</strong>${escapeHtml(horarios || '-')}</span>
      <span><strong>Materias</strong>${escapeHtml(data.materias_base.join(', ') || '-')}</span>
      <span><strong>Reforcos</strong>${escapeHtml(reforcos)}</span>
      <span><strong>Intensidade</strong>${escapeHtml(data.intensidade)}</span>
      <span><strong>Simulados</strong>${escapeHtml(data.frequencia_simulados)}</span>
    </div>
  `;
}

function renderOnboardingSummary() {
  const summary = document.getElementById('study-profile-summary');
  const alert = document.getElementById('estudai-profile-alert');
  const coreStatus = document.getElementById('core-perfil-status');
  const coreDetail = document.getElementById('core-perfil-detail');
  const mobileSummary = document.getElementById('mobile-profile-study-summary');

  if (!state.onboardingCompleto || !state.onboarding) {
    if (summary) summary.textContent = 'Antes de gerar seu plano, precisamos entender sua rotina de estudos.';
    if (alert) alert.classList.remove('hidden');
    if (coreStatus) coreStatus.textContent = 'Pendente';
    if (coreDetail) coreDetail.textContent = 'Onboarding obrigatorio';
    if (mobileSummary) mobileSummary.textContent = 'Perfil de estudo pendente';
    renderProfileDetails();
    return;
  }

  const p = state.onboarding;
  const objetivo = p.modo_estudo || p.objetivo || 'objetivo';
  const dias = Object.keys(p.disponibilidade || {}).length;
  const foco = Object.entries(p.reforcos || {}).filter(([, peso]) => Number(peso) > 1).map(([materia]) => materia).slice(0, 2);
  if (summary) summary.textContent = `Objetivo: ${objetivo}. Rotina: ${dias} dia(s) com horario real. Reforcos: ${foco.join(', ') || 'normal'}.`;
  if (alert) alert.classList.add('hidden');
  if (coreStatus) coreStatus.textContent = 'Concluido';
  if (coreDetail) coreDetail.textContent = `${objetivo} | ${dias} dia(s)`;
  if (mobileSummary) mobileSummary.textContent = `${objetivo} | ${dias} dia(s)`;
  renderProfileDetails();
}

function renderProfileDetails() {
  const el = document.getElementById('profile-study-details');
  if (!el) return;
  if (!state.onboardingCompleto || !state.onboarding) {
    el.innerHTML = '<p class="text-muted">Complete o formulario obrigatorio para liberar o EstudAI.</p>';
    return;
  }
  const p = state.onboarding;
  const horariosCount = Object.keys(p.disponibilidade || {}).length;
  const reforcos = Object.entries(p.reforcos || {}).filter(([, peso]) => Number(peso) > 1).map(([m]) => m);
  el.innerHTML = `
    <div class="detail-grid">
      <span><strong>Objetivo</strong>${escapeHtml(p.modo_estudo || p.objetivo || '-')}</span>
      <span><strong>Data</strong>${escapeHtml(p.data_prova || 'Sem data')}</span>
      <span><strong>Dias</strong>${escapeHtml(horariosCount ? `${horariosCount} dia(s)` : '-')}</span>
      <span><strong>Materias</strong>${escapeHtml((p.materias_base || []).slice(0, 6).join(', ') || '-')}</span>
      <span><strong>Reforcos</strong>${escapeHtml(reforcos.join(', ') || 'Normal')}</span>
      <span><strong>Horarios</strong>${escapeHtml(horariosCount ? `${horariosCount} dia(s) com blocos` : '-')}</span>
      <span><strong>Questoes</strong>${escapeHtml(p.exercicios_dia || '-')}</span>
      <span><strong>Simulados</strong>${escapeHtml(p.frequencia_simulados || '-')}</span>
    </div>
  `;
}

// ====================================
// NUCLEO FUNCIONAL ESTUDAI
// ====================================
async function loadEstudaiCore() {
  await Promise.allSettled([
    loadEstudaiStats(),
    loadDiagnosticoUltimo(),
    loadPlanoAtivo(),
    loadRotina()
  ]);
}

async function loadEstudaiStats() {
  try {
    const r = await apiFetch(apiEndpoint('estatisticas', 'action=estudai_geral'));
    const d = await r.json();
    if (d.ok) {
      state.estudaiStats = d;
      renderEstudaiStats(d);
    }
  } catch (e) {}
}

function renderEstudaiStats(d) {
  const perfil = d.perfil || {};
  const plano = d.plano || {};
  const tarefas = d.tarefas || {};
  const tempo = d.tempo || {};

  document.getElementById('core-perfil-status').textContent = perfil.tem_perfil ? 'Concluido' : 'Pendente';
  document.getElementById('core-perfil-detail').textContent = perfil.tem_perfil ? 'Perfil salvo no banco' : 'Aguardando onboarding';
  document.getElementById('core-plano-status').textContent = plano.tem_plano_ativo ? 'Sim' : 'Nao';
  document.getElementById('core-plano-detail').textContent = plano.tem_plano_ativo ? (plano.titulo || 'Plano ativo') : 'Nenhum plano salvo';
  document.getElementById('core-tarefas-status').textContent = `${tarefas.concluidas_semana || 0}/${tarefas.total_semana || 0}`;
  document.getElementById('core-tempo-detail').textContent = `${tempo.minutos_planejados_semana || 0} min planejados`;
  document.getElementById('core-conclusao-status').textContent = `${tarefas.percentual_conclusao || 0}%`;
  document.getElementById('core-atrasadas-detail').textContent = `${tarefas.atrasadas || 0} atrasada${tarefas.atrasadas === 1 ? '' : 's'}`;
  const weekSummary = document.getElementById('dashboard-week-summary');
  if (weekSummary) {
    weekSummary.textContent = plano.tem_plano_ativo
      ? `${tarefas.percentual_conclusao || 0}% concluido nesta semana.`
      : 'Gere um plano para acompanhar progresso semanal.';
  }
  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };
  setText('dash-week-done', tarefas.concluidas_semana || 0);
  setText('dash-week-pending', tarefas.pendentes_semana || 0);
  setText('dash-week-late', tarefas.atrasadas || 0);
  setText('dash-week-time', `${tempo.minutos_planejados_semana || 0}min`);
  const suggestion = document.getElementById('dashboard-ai-suggestion');
  if (suggestion && !state.plano) {
    suggestion.textContent = perfil.tem_perfil
      ? 'Perfil salvo. O proximo passo e gerar o plano semanal para preencher a rotina.'
      : 'Comece completando o perfil de estudo para a IA montar um plano realista.';
  }
}

async function loadDiagnosticoUltimo() {
  try {
    const r = await apiFetch(apiEndpoint('diagnostico', 'action=carregar_ultimo'));
    const d = await r.json();
    state.diagnostico = d.ok && d.resultado ? d.resultado : null;
    renderDiagnostico();
  } catch (e) {
    renderDiagnostico();
  }
}

function renderDiagnostico() {
  const summary = document.getElementById('diagnostic-summary');
  const content = document.getElementById('diagnostic-content');
  if (!content) return;

  if (!state.onboarding) {
    summary.textContent = 'Complete o perfil para liberar o diagnostico.';
    content.className = 'structured-content empty';
    content.innerHTML = '<p class="text-muted">O diagnostico sera gerado depois do onboarding.</p>';
    return;
  }

  const result = state.diagnostico;
  const diag = result?.diagnostico || result;
  if (!diag) {
    summary.textContent = 'Nenhum diagnostico gerado ainda.';
    content.className = 'structured-content empty';
    content.innerHTML = '<p class="text-muted">Use o botao acima para gerar um diagnostico com IA.</p>';
    return;
  }

  summary.textContent = 'Diagnostico gerado com apoio de IA.';
  content.className = 'structured-content';
  const materiasPrioritarias = safeArray(diag.materias_prioritarias);
  const proximosPassos = safeArray(diag.proximos_passos);
  content.innerHTML = `
    <p>${escapeHtml(diag.perfil_resumido || '')}</p>
    <div class="chip-row">${materiasPrioritarias.map(item => `<span class="soft-chip">${escapeHtml(item)}</span>`).join('')}</div>
    <div class="detail-grid">
      <span><strong>Dificuldades</strong>${escapeHtml((diag.principais_dificuldades || []).join(', ') || '-')}</span>
      <span><strong>Estrategia</strong>${escapeHtml(diag.estrategia_recomendada || '-')}</span>
      <span><strong>Rotina</strong>${escapeHtml(diag.rotina_sugerida || '-')}</span>
      <span><strong>Revisao</strong>${escapeHtml(diag.estrategia_revisao || '-')}</span>
    </div>
    <ul class="clean-list">${proximosPassos.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
  `;
}

async function gerarDiagnostico() {
  if (!state.onboarding) {
    showToast('warning', 'Perfil necessario', 'Complete o perfil de estudo antes do diagnostico.');
    openOnboardingModal();
    return;
  }

  const btn = document.getElementById('btn-gerar-diagnostico');
  btn.disabled = true;
  btn.classList.add('loading-state');
  document.getElementById('diagnostic-summary').textContent = 'Gerando diagnostico...';

  try {
    const r = await apiFetch(apiEndpoint('diagnostico', 'action=gerar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({})
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel gerar o diagnostico.');
    state.diagnostico = { origem: d.origem, diagnostico: d.diagnostico };
    renderDiagnostico();
    showToast('success', 'Diagnostico pronto', 'Resultado salvo no historico de IA.');
  } catch (e) {
    document.getElementById('diagnostic-summary').textContent = e.message || 'Erro ao gerar diagnostico.';
    showToast('error', 'Diagnostico indisponivel', e.message || 'Tente novamente em alguns minutos.');
  } finally {
    btn.disabled = false;
    btn.classList.remove('loading-state');
  }
}

async function loadPlanoAtivo() {
  try {
    const r = await apiFetch(apiEndpoint('planoEstudos', 'action=carregar_ativo'));
    const d = await r.json();
    state.plano = d.ok ? d.plano : null;
    renderPlano();
    renderDashboardPlan();
  } catch (e) {
    renderPlano(e.message);
  }
}

function groupTasksByDate(tasks) {
  return (tasks || []).reduce((acc, task) => {
    const key = task.data_prevista || 'sem_data';
    if (!acc[key]) acc[key] = [];
    acc[key].push(task);
    return acc;
  }, {});
}

function renderPlano(errorMessage) {
  const status = document.getElementById('plan-status-text');
  const content = document.getElementById('plan-content');
  if (!content) return;

  if (!state.onboarding) {
    status.textContent = 'Complete o perfil antes de gerar o plano.';
    content.innerHTML = `
      <div class="empty-state">
        <p>O plano depende do perfil de estudo.</p>
        <button class="btn-primary btn-sm" type="button" onclick="openOnboardingModal()">Completar perfil</button>
      </div>
    `;
    return;
  }

  if (errorMessage) {
    status.textContent = 'Nao foi possivel carregar o plano.';
    content.innerHTML = `<div class="empty-state"><p>${escapeHtml(errorMessage)}</p></div>`;
    return;
  }

  if (!state.plano) {
    status.textContent = 'Nenhum plano ativo salvo.';
    content.innerHTML = `
      <div class="empty-state">
        <p>Gere um plano para criar tarefas reais na rotina.</p>
        <button class="btn-primary btn-sm" type="button" onclick="gerarPlanoEstudos()">Gerar plano semanal</button>
      </div>
    `;
    return;
  }

  const plano = state.plano;
  const tarefas = plano.tarefas || [];
  const grouped = groupTasksByDate(tarefas);
  const validTasks = tarefas.filter(task => task.status !== 'cancelada');
  const doneTasks = validTasks.filter(task => task.status === 'concluida').length;
  const progress = validTasks.length ? Math.round((doneTasks / validTasks.length) * 100) : 0;
  status.textContent = `${plano.titulo || 'Plano ativo'} | origem: ${(plano.origem || 'manual').toUpperCase()}`;

  const daysHtml = Object.entries(grouped).map(([date, tasks]) => `
    <div class="plan-day">
      <div class="plan-day-title">
        <strong>${date === 'sem_data' ? 'Sem data' : formatDate(date)}</strong>
        <span>${tasks.length} tarefa${tasks.length === 1 ? '' : 's'}</span>
      </div>
      <div class="task-list compact">
        ${tasks.map(task => taskCardHtml(task, true)).join('')}
      </div>
    </div>
  `).join('');

  const alertas = safeArray(plano.alertas);
  content.innerHTML = `
    <div class="plan-overview">
      <div class="plan-overview-head">
        <span class="badge badge-success">${escapeHtml(plano.origem || 'manual')}</span>
        <strong>${progress}% concluido</strong>
      </div>
      <p>${escapeHtml(plano.resumo || 'Plano ativo salvo no banco.')}</p>
      ${plano.estrategia_da_semana ? `<div class="explanation visible"><strong>Estrategia da semana</strong><p>${escapeHtml(plano.estrategia_da_semana)}</p></div>` : ''}
      <small>${escapeHtml(plano.data_inicio || plano.semana_inicio || '-')} ate ${escapeHtml(plano.data_fim || plano.semana_fim || '-')}</small>
      <div class="progress-line"><span style="width:${progress}%"></span></div>
      ${alertas.length ? `<div class="notice-card warning compact-alert">${alertas.map(alerta => `<p>${escapeHtml(alerta)}</p>`).join('')}</div>` : ''}
    </div>
    ${daysHtml || '<div class="empty-state"><p>Plano sem tarefas vinculadas.</p></div>'}
  `;
}

async function gerarPlanoEstudos() {
  if (!state.onboarding) {
    showToast('warning', 'Perfil necessario', 'Complete o perfil de estudo antes do plano.');
    openOnboardingModal();
    return;
  }

  const btn = document.getElementById('btn-gerar-plano');
  btn.disabled = true;
  btn.classList.add('loading-state');
  document.getElementById('plan-status-text').textContent = 'Gerando plano semanal, calendario e tarefas...';

  try {
    const r = await apiFetch(apiEndpoint('planoEstudos', 'action=gerar_semana'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({})
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel gerar o plano.');
    state.plano = d.plano;
    renderPlano();
    renderDashboardPlan();
    await loadRotina();
    await loadCalendarMonth();
    await loadPlannedSimulados();
    await loadEstudaiStats();
    const fallbackUsado = !!(d.fallback_usado || d.origem === 'fallback' || d.plano?.origem === 'fallback');
    if (fallbackUsado) {
      const aviso = d.aviso_usuario || 'A IA caiu ou retornou um plano invalido, mas foi gerado um plano basico com base na sua rotina.';
      document.getElementById('plan-status-text').textContent = aviso;
      showToast('warning', 'Plano basico gerado', aviso);
    } else {
      showToast('success', 'Plano semanal criado', 'Plano salvo com tarefas e calendario por IA.');
    }
  } catch (e) {
    document.getElementById('plan-status-text').textContent = e.message || 'Erro ao gerar plano.';
    showToast('error', 'Plano indisponivel', e.message || 'Tente novamente em alguns minutos.');
  } finally {
    btn.disabled = false;
    btn.classList.remove('loading-state');
  }
}

async function loadRotina() {
  try {
    const [hojeRes, semanaRes, atrasadasRes, recentesRes] = await Promise.all([
      apiFetch(apiEndpoint('tarefasEstudo', 'action=hoje')),
      apiFetch(apiEndpoint('tarefasEstudo', 'action=semana')),
      apiFetch(apiEndpoint('tarefasEstudo', 'action=atrasadas')),
      apiFetch(apiEndpoint('tarefasEstudo', 'action=recentes&limite=6'))
    ]);
    const [hoje, semana, atrasadas, recentes] = await Promise.all([
      hojeRes.json(),
      semanaRes.json(),
      atrasadasRes.json(),
      recentesRes.json()
    ]);

    state.tarefasHoje = hoje.ok ? (hoje.tarefas || []) : [];
    state.tarefasSemana = semana.ok ? (semana.tarefas || []) : [];
    state.tarefasAtrasadas = atrasadas.ok ? (atrasadas.tarefas || []) : [];
    state.tarefasRecentes = recentes.ok ? (recentes.tarefas || []) : [];
    renderRotina();
    renderDashboardRoutine();
  } catch (e) {
    renderRotina(e.message);
  }
}

function taskStatusLabel(task) {
  const status = task.status_calculado || task.status || 'pendente';
  const labels = {
    pendente: 'Pendente',
    em_andamento: 'Em andamento',
    concluida: 'Concluida',
    atrasada: 'Atrasada',
    adiada: 'Adiada',
    remarcada: 'Remarcada',
    cancelada: 'Cancelada'
  };
  return labels[status] || status;
}

function taskActionOnclick(task) {
  const tipo = task.tipo || task.metadata?.tipo_tarefa || 'custom';
  if (tipo === 'questoes' || tipo === 'exercicio') return `abrirExerciciosTarefa(${Number(task.id)})`;
  if (tipo === 'simulado') return `abrirSimuladoDaTarefa(${Number(task.id)})`;
  if (tipo === 'revisao') return `abrirRevisaoTarefa(${Number(task.id)})`;
  if (tipo === 'resumo') return "showSection('anotacoes')";
  if (tipo === 'teoria') return "showSection('anotacoes')";
  return "showSection('calendario')";
}

function taskCardHtml(task, compact) {
  const done = task.status === 'concluida';
  const canceled = task.status === 'cancelada';
  const tipo = task.tipo || task.metadata?.tipo_tarefa || 'custom';
  const materia = task.materia || task.metadata?.materia || 'Geral';
  const conteudo = task.conteudo || task.metadata?.conteudo || '';
  const prioridade = task.prioridade || task.metadata?.prioridade || 'media';
  const blockedSim = tipo === 'simulado' && task.data_prevista && task.data_prevista > localDateKey();
  return `
    <div class="task-card ${done ? 'done' : ''} ${canceled ? 'cancelled' : ''}">
      <div class="task-main">
        <div class="task-title-row">
          <strong>${escapeHtml(task.titulo)}</strong>
          <span class="task-badge ${escapeHtml(task.status_calculado || task.status)}">${taskStatusLabel(task)}</span>
        </div>
        ${compact ? '' : `<p>${escapeHtml(task.descricao || '')}</p>`}
        <div class="task-meta">
          <span>${escapeHtml(materia)}</span>
          ${conteudo ? `<span>${escapeHtml(conteudo)}</span>` : ''}
          <span>${escapeHtml(labelTipoTarefa(tipo))}</span>
          <span>${Number(task.tempo_estimado || 0)} min</span>
          ${task.tempo_real_min ? `<span>${Number(task.tempo_real_min)} min feitos</span>` : ''}
          <span>${escapeHtml(labelPrioridade(prioridade))}</span>
          ${task.hora_inicio ? `<span>${escapeHtml(task.hora_inicio)}${task.hora_fim ? `-${escapeHtml(task.hora_fim)}` : ''}</span>` : ''}
        </div>
      </div>
      <div class="task-actions">
        <button class="btn-secondary btn-sm" type="button" ${blockedSim ? 'disabled' : `onclick="${taskActionOnclick(task)}"`}>${taskActionLabel(task)}</button>
        ${(!done && !canceled && !task.sessao_ativa_id) ? `<button class="btn-primary btn-sm" type="button" onclick="comecarTarefa(${Number(task.id)})">Comecar</button>` : ''}
        ${task.sessao_ativa_id ? `<button class="btn-secondary btn-sm" type="button" onclick="pausarTarefa(${Number(task.id)})">Pausar</button><button class="btn-primary btn-sm" type="button" onclick="finalizarTempoTarefa(${Number(task.id)})">Finalizar</button>` : ''}
        ${(!canceled) ? `<button class="${done ? 'btn-secondary' : 'btn-primary'} btn-sm" type="button" onclick="${done ? `reabrirTarefa(${Number(task.id)})` : `concluirTarefa(${Number(task.id)})`}">${done ? 'Reabrir' : 'Concluir'}</button>` : ''}
        ${(!done && !canceled) ? `<button class="btn-secondary btn-sm" type="button" onclick="openTaskModal(${Number(task.id)})">Editar</button>` : ''}
        ${(!done && !canceled) ? `<button class="btn-secondary btn-sm" type="button" onclick="adiarTarefa(${Number(task.id)})">Adiar</button>` : ''}
        ${(!done && !canceled) ? `<button class="btn-secondary btn-sm danger-lite" type="button" onclick="cancelarTarefa(${Number(task.id)})">Cancelar</button>` : ''}
      </div>
    </div>
  `;
}

function renderTaskList(id, tasks, emptyMessage, compact) {
  const el = document.getElementById(id);
  if (!el) return;
  if (!tasks.length) {
    el.innerHTML = `<div class="empty-state"><p>${escapeHtml(emptyMessage)}</p></div>`;
    return;
  }
  el.innerHTML = tasks.map(task => taskCardHtml(task, compact)).join('');
}

function renderRotina(errorMessage) {
  const status = document.getElementById('routine-status-text');
  if (errorMessage) {
    status.textContent = 'Nao foi possivel carregar as tarefas.';
    renderTaskList('routine-today-list', [], errorMessage, false);
    renderTaskList('routine-week-list', [], errorMessage, true);
    renderTaskList('routine-late-list', [], errorMessage, true);
    renderTaskList('routine-done-list', [], errorMessage, true);
    return;
  }

  status.textContent = state.tarefasHoje.length
    ? `${state.tarefasHoje.length} tarefa${state.tarefasHoje.length === 1 ? '' : 's'} para hoje`
    : 'Nenhuma tarefa prevista para hoje.';

  renderTaskList('routine-today-list', state.tarefasHoje, state.plano ? 'Nenhuma tarefa para hoje.' : 'Gere um plano para criar tarefas.', false);

  const hoje = localDateKey();
  const proximas = state.tarefasSemana
    .filter(task => task.data_prevista && task.data_prevista >= hoje && task.status !== 'concluida')
    .slice(0, 6);
  renderTaskList('routine-week-list', proximas, 'Sem proximas tarefas pendentes nesta semana.', true);
  renderTaskList('routine-late-list', state.tarefasAtrasadas.slice(0, 6), 'Nenhuma tarefa atrasada.', true);
  renderTaskList('routine-done-list', state.tarefasRecentes.slice(0, 6), 'Nenhuma tarefa concluida recentemente.', true);
}

function renderDashboardPlan() {
  const summary = document.getElementById('dashboard-plan-summary');
  const next = document.getElementById('dashboard-next-task');
  if (!summary || !next) return;

  if (!state.onboardingCompleto) {
    summary.textContent = 'Complete seu perfil de estudo.';
    next.innerHTML = '<strong>Complete seu perfil de estudo</strong><span>Antes de gerar seu plano, precisamos entender sua rotina de estudos.</span><button class="btn-primary btn-sm" type="button" onclick="openOnboardingModal(true)">Preencher formulario</button>';
    return;
  }

  if (!state.plano) {
    summary.textContent = 'Seu perfil esta pronto.';
    next.innerHTML = '<strong>Seu perfil esta pronto</strong><span>O proximo passo e gerar uma semana possivel.</span><button class="btn-primary btn-sm" type="button" onclick="gerarPlanoEstudos()">Gerar plano semanal</button>';
    return;
  }

  summary.textContent = `${state.plano.titulo || 'Plano ativo'} | origem ${(state.plano.origem || 'manual').toUpperCase()}`;
  const today = localDateKey();
  const upcoming = (state.tarefasAtrasadas || [])[0]
    || (state.tarefasHoje || []).find(task => !['concluida', 'cancelada'].includes(task.status))
    || (state.plano.tarefas || []).find(task => !['concluida', 'cancelada'].includes(task.status) && (!task.data_prevista || task.data_prevista >= today))
    || null;
  if (upcoming) {
    const timing = dashboardTaskTiming(upcoming);
    const tipo = upcoming.tipo || upcoming.metadata?.tipo_tarefa || 'custom';
    const materia = upcoming.materia || upcoming.metadata?.materia || 'Geral';
    const conteudo = upcoming.conteudo || upcoming.metadata?.conteudo || '';
    const prioridade = upcoming.prioridade || upcoming.metadata?.prioridade || 'media';
    const horario = upcoming.hora_inicio
      ? `${upcoming.hora_inicio}${upcoming.hora_fim ? `-${upcoming.hora_fim}` : ''}`
      : 'Sem horario definido';
    const dataLinha = upcoming.data_prevista ? formatDate(upcoming.data_prevista) : 'Sem data';
    const detalhes = [
      materia,
      conteudo,
      labelTipoTarefa(tipo),
      labelPrioridade(prioridade)
    ].filter(Boolean).join(' | ');

    next.innerHTML = `<div class="next-action-card ${timing.className}">
        <div>
          <div class="next-action-context">
            <span class="badge ${timing.badgeClass}">${escapeHtml(timing.label)}</span>
            <span>${escapeHtml(timing.description)}</span>
          </div>
          <strong>${escapeHtml(upcoming.titulo || 'Atividade pendente')}</strong>
          <span class="next-task-specific">Tarefa exibida: ${escapeHtml(detalhes)}</span>
          <small>${escapeHtml(dataLinha)} | ${escapeHtml(horario)} | ${Number(upcoming.tempo_estimado || 0)} min | ${escapeHtml(taskStatusLabel(upcoming))}</small>
        </div>
        <button class="btn-primary btn-sm" type="button" onclick="${taskActionOnclick(upcoming)}">${taskActionLabel(upcoming)}</button>
      </div>`;
  } else {
    next.innerHTML = '<span class="text-muted">Todas as tarefas do plano estao concluidas.</span>';
  }
  const suggestion = document.getElementById('dashboard-ai-suggestion');
  if (suggestion) {
    if (upcoming) {
      const timing = dashboardTaskTiming(upcoming);
      const tipo = upcoming.tipo || upcoming.metadata?.tipo_tarefa || 'custom';
      suggestion.textContent = `${timing.label}: ${upcoming.materia || 'Geral'} - ${labelTipoTarefa(tipo)}. Comece por "${upcoming.titulo}".`;
    } else {
      suggestion.textContent = 'Plano em dia. Use a revisao para consolidar o que ja foi concluido.';
    }
  }
}

function renderDashboardRoutine() {
  const summary = document.getElementById('dashboard-routine-summary');
  if (!summary) return;
  const done = state.tarefasHoje.filter(task => task.status === 'concluida').length;
  summary.textContent = state.tarefasHoje.length
    ? `${done}/${state.tarefasHoje.length} tarefa${state.tarefasHoje.length === 1 ? '' : 's'} de hoje concluidas.`
    : 'Nenhuma tarefa do plano para hoje.';
}

async function concluirTarefa(id) {
  await atualizarStatusTarefa(id, 'concluir');
}

async function reabrirTarefa(id) {
  await atualizarStatusTarefa(id, 'reabrir');
}

async function atualizarStatusTarefa(id, action) {
  try {
    const r = await apiFetch(apiEndpoint('tarefasEstudo', { action }), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tarefa_id: id })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel atualizar a tarefa.');
    await loadRotina();
    await loadPlanoAtivo();
    await loadEstudaiStats();
    showToast('success', action === 'concluir' ? 'Tarefa concluida' : 'Tarefa reaberta', 'Progresso atualizado.');
  } catch (e) {
    showToast('error', 'Tarefa nao atualizada', e.message || 'Tente novamente.');
  }
}

function allKnownTasks() {
  return [
    ...state.tarefasHoje,
    ...state.tarefasSemana,
    ...state.tarefasAtrasadas,
    ...state.tarefasRecentes,
    ...(state.plano?.tarefas || [])
  ];
}

function findTask(id) {
  return allKnownTasks().find(task => Number(task.id) === Number(id));
}

async function taskPost(action, payload, successTitle, successMessage) {
  const r = await apiFetch(apiEndpoint('tarefasEstudo', { action }), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const d = await r.json();
  if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel atualizar a tarefa.');
  await Promise.allSettled([
    loadRotina(),
    loadPlanoAtivo(),
    loadCalendarMonth(),
    loadEstudaiStats()
  ]);
  if (successTitle) showToast('success', successTitle, successMessage || 'Rotina atualizada.');
  return d;
}

async function comecarTarefa(id) {
  try {
    await taskPost('iniciar_tempo', { tarefa_id: id }, 'Tarefa iniciada', 'O tempo real comecou a ser registrado.');
  } catch (e) {
    showToast('error', 'Nao foi possivel iniciar', e.message || 'Tente novamente.');
  }
}

async function pausarTarefa(id) {
  try {
    await taskPost('pausar_tempo', { tarefa_id: id }, 'Tempo salvo', 'A sessao foi pausada.');
  } catch (e) {
    showToast('error', 'Tempo nao salvo', e.message || 'Tente novamente.');
  }
}

async function finalizarTempoTarefa(id) {
  try {
    await taskPost('finalizar_tempo', { tarefa_id: id }, 'Tarefa finalizada', 'Tempo real e progresso atualizados.');
  } catch (e) {
    showToast('error', 'Nao foi possivel finalizar', e.message || 'Tente novamente.');
  }
}

async function adiarTarefa(id) {
  try {
    await taskPost('adiar', { tarefa_id: id, dias: 1 }, 'Tarefa adiada', 'Ela foi movida para o proximo dia.');
  } catch (e) {
    showToast('error', 'Tarefa nao adiada', e.message || 'Tente novamente.');
  }
}

async function cancelarTarefa(id) {
  if (!confirm('Cancelar esta tarefa? Ela nao aparecera como pendente normal.')) return;
  try {
    await taskPost('cancelar', { tarefa_id: id }, 'Tarefa cancelada', 'Ela saiu da lista de pendencias.');
  } catch (e) {
    showToast('error', 'Tarefa nao cancelada', e.message || 'Tente novamente.');
  }
}

function openTaskModal(id) {
  const task = findTask(id);
  if (!task) {
    showToast('warning', 'Tarefa nao encontrada', 'Atualize a rotina e tente novamente.');
    return;
  }
  document.getElementById('task-edit-id').value = task.id;
  document.getElementById('task-edit-title').value = task.titulo || '';
  document.getElementById('task-edit-desc').value = task.descricao || '';
  document.getElementById('task-edit-date').value = task.data_prevista || '';
  document.getElementById('task-edit-start').value = task.hora_inicio || '';
  document.getElementById('task-edit-end').value = task.hora_fim || '';
  document.getElementById('task-edit-duration').value = task.tempo_estimado || 30;
  document.getElementById('task-edit-priority').value = task.prioridade || 'media';
  document.getElementById('modal-tarefa').classList.add('active');
}

async function salvarEdicaoTarefa() {
  const id = Number(document.getElementById('task-edit-id').value || 0);
  const payload = {
    tarefa_id: id,
    titulo: document.getElementById('task-edit-title').value.trim(),
    descricao: document.getElementById('task-edit-desc').value.trim(),
    data_prevista: document.getElementById('task-edit-date').value,
    hora_inicio: document.getElementById('task-edit-start').value,
    hora_fim: document.getElementById('task-edit-end').value,
    tempo_estimado: Number(document.getElementById('task-edit-duration').value || 0),
    prioridade: document.getElementById('task-edit-priority').value
  };
  if (!payload.titulo) {
    showToast('warning', 'Titulo obrigatorio', 'Informe um titulo para a tarefa.');
    return;
  }
  try {
    await taskPost('editar', payload, 'Tarefa atualizada', 'Plano e calendario foram sincronizados.');
    closeModal('modal-tarefa');
  } catch (e) {
    showToast('error', 'Tarefa nao salva', e.message || 'Confira data e horarios.');
  }
}

function openReplanModal() {
  if (!state.plano) {
    showToast('warning', 'Sem plano ativo', 'Gere um plano semanal antes de replanejar.');
    return;
  }
  document.getElementById('modal-replanejar').classList.add('active');
}

async function replanejarSemana() {
  const btn = document.getElementById('btn-replanejar');
  btn.disabled = true;
  btn.classList.add('loading-state');
  try {
    const r = await apiFetch(apiEndpoint('planoEstudos', 'action=replanejar_semana'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        motivo: document.getElementById('replan-motivo').value,
        detalhes: document.getElementById('replan-detalhes').value.trim()
      })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao conseguimos replanejar agora.');
    state.plano = d.plano;
    closeModal('modal-replanejar');
    renderPlano();
    await loadRotina();
    await loadCalendarMonth();
    await loadEstudaiStats();
    const fallbackUsado = !!(d.fallback_usado || d.origem === 'fallback' || d.plano?.origem === 'fallback');
    if (fallbackUsado) {
      showToast('warning', 'Plano basico gerado', d.aviso_usuario || 'A IA caiu ou retornou um plano invalido, mas foi gerado um plano basico com base na sua rotina.');
    } else {
      showToast('success', 'Semana replanejada', 'O novo plano respeita seu perfil, disponibilidade e tarefas atrasadas.');
    }
  } catch (e) {
    showToast('error', 'Replanejamento indisponivel', e.message || 'Tente novamente em alguns minutos.');
  } finally {
    btn.disabled = false;
    btn.classList.remove('loading-state');
  }
}

// ====================================
// CALENDARIO, EXERCICIOS E SIMULADOS DO PLANO
// ====================================
function isoDate(date) {
  return date.toISOString().slice(0, 10);
}

function monthKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function calendarVisibleTypes() {
  return checkedValues('#calendar-filters input:checked');
}

function calendarEventMatchesVisible(event, visible) {
  const tipo = event.metadata?.tipo_tarefa || event.tipo || 'tarefa';
  const statusCalc = event.status_calculado || event.status;
  if (statusCalc === 'concluido' && !visible.includes('concluido')) return false;
  if (statusCalc === 'atrasado' && !visible.includes('atrasado')) return false;
  if (tipo === 'questoes') return visible.includes('exercicio');
  if (event.tipo === 'tarefa') return visible.includes(tipo) || visible.includes('teoria');
  return visible.includes(event.tipo) || visible.includes(tipo);
}

function calendarEventsForDate(date, visible = calendarVisibleTypes()) {
  return state.calendarEvents
    .filter((event) => event.data_evento === date)
    .filter((event) => calendarEventMatchesVisible(event, visible));
}

function calendarEventTypeClass(event) {
  return event.metadata?.tipo_tarefa || (event.tipo === 'exercicio' ? 'questoes' : event.tipo) || 'tarefa';
}

async function loadCalendarMonth() {
  const status = document.getElementById('calendar-status');
  if (status) status.textContent = 'Carregando eventos do mes...';
  try {
    const mes = monthKey(state.calendarDate);
    const r = await apiFetch(apiEndpoint('calendarioEstudai', { action: 'mes', mes }));
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar o calendario.');
    state.calendarEvents = d.eventos || [];
    renderCalendar();
    await loadCalendarYear(false);
  } catch (e) {
    if (status) status.textContent = e.message || 'Erro ao carregar calendario.';
  }
}

async function loadCalendarYear(showToastOnDone = true) {
  try {
    const ano = state.calendarDate.getFullYear();
    const r = await apiFetch(apiEndpoint('calendarioEstudai', { action: 'ano', ano }));
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar o ano.');
    state.calendarYearEvents = d.eventos || [];
    renderCalendarYear();
    if (showToastOnDone) showToast('success', 'Calendario atualizado', 'Visao anual sincronizada.');
  } catch (e) {
    const el = document.getElementById('calendar-year-summary');
    if (el) el.innerHTML = `<div class="empty-state"><p>${escapeHtml(e.message || 'Erro ao carregar ano.')}</p></div>`;
  }
}

function renderCalendar() {
  const grid = document.getElementById('calendar-grid');
  const title = document.getElementById('calendar-title');
  const status = document.getElementById('calendar-status');
  if (!grid) return;

  const visible = calendarVisibleTypes();
  const date = new Date(state.calendarDate.getFullYear(), state.calendarDate.getMonth(), 1);
  const firstWeekday = (date.getDay() + 6) % 7;
  const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  const today = isoDate(new Date());
  const mes = date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
  const monthPrefix = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-`;
  const selectedDayNumber = state.selectedCalendarDay?.startsWith(monthPrefix)
    ? Number(state.selectedCalendarDay.slice(8, 10))
    : 0;
  const selectedSlot = selectedDayNumber ? firstWeekday + selectedDayNumber - 1 : -1;
  const selectedWeekEndSlot = selectedSlot >= 0
    ? Math.min(firstWeekday + daysInMonth - 1, selectedSlot + (6 - (selectedSlot % 7)))
    : -1;
  if (title) title.textContent = mes.charAt(0).toUpperCase() + mes.slice(1);
  if (status) status.textContent = `${state.calendarEvents.length} evento${state.calendarEvents.length === 1 ? '' : 's'} neste mes`;

  const cells = [];
  for (let i = 0; i < firstWeekday; i++) cells.push('<button class="calendar-day empty" type="button" aria-hidden="true"></button>');
  for (let day = 1; day <= daysInMonth; day++) {
    const dayDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const slotIndex = firstWeekday + day - 1;
    const events = calendarEventsForDate(dayDate, visible);
    const dots = events.slice(0, 4).map((event) => `<span class="calendar-dot ${escapeHtml(calendarEventTypeClass(event))}"></span>`).join('');
    const isSelected = dayDate === state.selectedCalendarDay;
    const dayClasses = [
      'calendar-day',
      events.length ? 'has-events' : '',
      dayDate === today ? 'today' : '',
      isSelected ? 'selected' : ''
    ].filter(Boolean).join(' ');
    const ariaLabel = `${day}/${String(date.getMonth() + 1).padStart(2, '0')}: ${events.length ? `${events.length} atividade${events.length === 1 ? '' : 's'}` : 'sem atividades'}`;
    cells.push(`
      <button class="${dayClasses}" type="button" onclick="selectCalendarDay('${dayDate}')" aria-label="${escapeHtml(ariaLabel)}" aria-expanded="${isSelected ? 'true' : 'false'}">
        <strong class="calendar-day-number">${day}</strong>
        <span class="calendar-event-count">${events.length ? `${events.length} atividade${events.length === 1 ? '' : 's'}` : ''}</span>
        <span class="calendar-dots" aria-hidden="true">${dots}</span>
      </button>
    `);
    if (slotIndex === selectedWeekEndSlot) {
      cells.push('<div id="calendar-mobile-day-panel" class="calendar-mobile-day-panel"></div>');
    }
  }
  grid.innerHTML = cells.join('');
  renderCalendarDay(state.selectedCalendarDay);
}

function renderCalendarYear() {
  const el = document.getElementById('calendar-year-summary');
  if (!el) return;
  const counts = {};
  state.calendarYearEvents.forEach((event) => {
    const key = String(event.data_evento || '').slice(0, 7);
    counts[key] = (counts[key] || 0) + 1;
  });
  const year = state.calendarDate.getFullYear();
  el.innerHTML = Array.from({ length: 12 }, (_, index) => {
    const key = `${year}-${String(index + 1).padStart(2, '0')}`;
    const label = new Date(year, index, 1).toLocaleDateString('pt-BR', { month: 'short' });
    return `<button class="year-chip" type="button" onclick="jumpCalendarMonth(${year}, ${index})"><strong>${escapeHtml(label)}</strong><span>${counts[key] || 0}</span></button>`;
  }).join('');
}

function selectCalendarDay(date) {
  state.selectedCalendarDay = date;
  renderCalendar();
  const panel = document.getElementById('calendar-mobile-day-panel');
  if (panel && window.matchMedia('(max-width: 860px)').matches) {
    requestAnimationFrame(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
  }
}

function renderCalendarDay(date) {
  const title = document.getElementById('calendar-day-title');
  const summary = document.getElementById('calendar-day-summary');
  const list = document.getElementById('calendar-day-events');
  if (!list) return;
  const events = calendarEventsForDate(date);
  if (title) title.textContent = formatDate(date);
  if (summary) summary.textContent = events.length ? `${events.length} atividade${events.length === 1 ? '' : 's'} no dia` : 'Sem atividades planejadas.';
  const mobilePanel = document.getElementById('calendar-mobile-day-panel');
  if (!events.length) {
    const emptyHtml = '<div class="empty-state"><p>Nenhuma atividade neste dia.</p></div>';
    list.innerHTML = emptyHtml;
    if (mobilePanel) mobilePanel.innerHTML = calendarMobilePanelHtml(date, events, emptyHtml);
    return;
  }
  const eventsHtml = events.map(calendarEventHtml).join('');
  list.innerHTML = eventsHtml;
  if (mobilePanel) mobilePanel.innerHTML = calendarMobilePanelHtml(date, events, eventsHtml);
}

function calendarMobilePanelHtml(date, events, contentHtml) {
  return `
    <div class="calendar-mobile-panel-card">
      <div class="calendar-mobile-panel-head">
        <div>
          <strong>${escapeHtml(formatDate(date))}</strong>
          <span>${events.length ? `${events.length} atividade${events.length === 1 ? '' : 's'} para este dia` : 'Sem atividades planejadas'}</span>
        </div>
      </div>
      <div class="task-list compact">${contentHtml}</div>
    </div>
  `;
}

function calendarEventHtml(event) {
  const meta = event.metadata || {};
  const task = {
    id: event.tarefa_id || 0,
    titulo: event.titulo,
    descricao: event.descricao,
    materia: meta.materia || 'Geral',
    tipo: meta.tipo_tarefa || (event.tipo === 'exercicio' ? 'questoes' : event.tipo),
    tempo_estimado: meta.tempo_estimado || 0,
    prioridade: meta.prioridade || 'media',
    data_prevista: event.data_evento,
    hora_inicio: event.hora_inicio,
    hora_fim: event.hora_fim,
    status: event.status === 'concluido' ? 'concluida' : event.status,
    status_calculado: event.status_calculado === 'concluido' ? 'concluida' : event.status_calculado,
    metadata: meta
  };
  return taskCardHtml(task, true);
}

function calendarPrevMonth() {
  state.calendarDate = new Date(state.calendarDate.getFullYear(), state.calendarDate.getMonth() - 1, 1);
  state.selectedCalendarDay = `${state.calendarDate.getFullYear()}-${String(state.calendarDate.getMonth() + 1).padStart(2, '0')}-01`;
  loadCalendarMonth();
}

function calendarNextMonth() {
  state.calendarDate = new Date(state.calendarDate.getFullYear(), state.calendarDate.getMonth() + 1, 1);
  state.selectedCalendarDay = `${state.calendarDate.getFullYear()}-${String(state.calendarDate.getMonth() + 1).padStart(2, '0')}-01`;
  loadCalendarMonth();
}

function calendarToday() {
  state.calendarDate = new Date();
  state.selectedCalendarDay = isoDate(new Date());
  loadCalendarMonth();
}

function jumpCalendarMonth(year, monthIndex) {
  state.calendarDate = new Date(year, monthIndex, 1);
  state.selectedCalendarDay = `${year}-${String(monthIndex + 1).padStart(2, '0')}-01`;
  loadCalendarMonth();
}

async function abrirExerciciosTarefa(tarefaId) {
  state.currentExerciseTaskId = tarefaId;
  showSection('exercicios');
  await loadExercisesForTask(tarefaId, false);
}

async function loadExercisesForTask(tarefaId, regenerate) {
  const status = document.getElementById('exercises-status');
  const content = document.getElementById('exercises-content');
  if (status) status.textContent = regenerate ? 'Buscando novo lote na base de questoes...' : 'Carregando pratica com questoes salva...';
  if (content) content.innerHTML = '<div class="empty-state"><p>Carregando...</p></div>';
  try {
    const endpoint = regenerate
      ? apiEndpoint('exerciciosIa', 'action=gerar_por_tarefa')
      : apiEndpoint('exerciciosIa', { action: 'carregar_por_tarefa', tarefa_id: tarefaId });
    const options = regenerate
      ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ tarefa_id: tarefaId }) }
      : undefined;
    const r = await apiFetch(endpoint, options);
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar questoes.');
    state.currentExerciseLote = d.lote;
    renderExercises();
  } catch (e) {
    if (status) status.textContent = e.message || 'Erro na pratica com questoes.';
    if (content) content.innerHTML = `<div class="empty-state"><p>${escapeHtml(e.message || 'Erro ao carregar questoes.')}</p></div>`;
  }
}

function renderExercises() {
  const status = document.getElementById('exercises-status');
  const content = document.getElementById('exercises-content');
  if (!content) return;
  const lote = state.currentExerciseLote;
  if (!lote) {
    if (status) status.textContent = 'Abra uma tarefa de questoes pela rotina ou calendario.';
    content.innerHTML = '<div class="empty-state"><p>Nenhuma tarefa selecionada.</p></div>';
    return;
  }
  const respondidos = Object.keys(lote.respostas || {}).length;
  if (status) status.textContent = `${lote.quantidade || (lote.exercicios || []).length} questoes | base aprovada | ${respondidos} respondido(s)`;
  content.innerHTML = (lote.exercicios || []).map((ex, index) => {
    const resposta = lote.respostas?.[ex.id];
    const answered = resposta !== undefined;
    const alternativas = ex.alternativas || {};
    const hasAlternativas = Object.values(alternativas).filter(Boolean).length >= 2;
    const canAnswer = ex.tipo === 'aberta' || hasAlternativas;
    const options = ex.tipo === 'aberta'
      ? `<textarea class="input" id="exercise-answer-${escapeHtml(ex.id)}" rows="3" placeholder="Digite sua resposta">${escapeHtml(resposta?.resposta_usuario || '')}</textarea>`
      : hasAlternativas ? `<div class="options compact-options">${Object.entries(alternativas).map(([letter, text]) => `
          <label class="option-label">
            <input type="radio" name="exercise-${escapeHtml(ex.id)}" value="${escapeHtml(letter)}" ${resposta?.resposta_marcada === letter ? 'checked' : ''}>
            <span><strong>${escapeHtml(letter)}</strong> ${escapeHtml(text)}</span>
          </label>
        `).join('')}</div>` : '<div class="empty-state compact-empty"><p>Esta questao nao possui alternativas validas cadastradas.</p></div>';
    const reasonOptions = [
      ['nao_sabia', 'Nao sabia o conteudo'],
      ['atencao', 'Erro de atencao'],
      ['calculo', 'Erro de calculo'],
      ['interpretacao', 'Interpretacao'],
      ['duvida', 'Fiquei em duvida'],
      ['chutei', 'Chutei']
    ];
    return `
      <article class="exercise-card ${answered ? 'answered' : ''}">
        <div class="question-meta">
          <span>${index + 1}</span>
          <span>${escapeHtml(ex.materia || lote.materia || 'Geral')}</span>
          <span>${escapeHtml(ex.dificuldade || 'medio')}</span>
          ${answered ? `<span>${resposta.acertou === null ? 'Avaliar depois' : (resposta.acertou ? 'Correta' : 'Revisar')}</span>` : ''}
        </div>
        <p class="question-text">${escapeHtml(ex.pergunta)}</p>
        ${options}
        ${answered ? `<div class="explanation visible"><strong>${resposta.acertou ? 'Correta' : 'Resposta registrada para revisao'}</strong><p>${escapeHtml(resposta.avaliacao?.explicacao || ex.explicacao || 'Explicacao nao cadastrada para esta questao.')}</p></div>` : ''}
        ${answered && resposta.acertou === false ? `
          <div class="form-field compact-field">
            <label for="exercise-error-reason-${escapeHtml(ex.id)}">Por que voce acha que errou?</label>
            <select class="input" id="exercise-error-reason-${escapeHtml(ex.id)}">
              <option value="">Prefiro nao informar</option>
              ${reasonOptions.map(([value, label]) => `<option value="${value}" ${resposta.motivo_erro === value ? 'selected' : ''}>${label}</option>`).join('')}
            </select>
          </div>
        ` : ''}
        <button class="btn-primary btn-sm" type="button" ${canAnswer ? '' : 'disabled'} onclick="responderExercicio(${lote.id}, ${JSON.stringify(ex.id)}, ${JSON.stringify(ex.tipo || 'multipla_escolha')})">
          ${answered ? 'Atualizar resposta' : 'Responder'}
        </button>
      </article>
    `;
  }).join('');
}

async function responderExercicio(loteId, key, tipo) {
  const selector = `input[name="exercise-${CSS.escape(key)}"]:checked`;
  const marcada = document.querySelector(selector)?.value || '';
  const aberta = document.getElementById(`exercise-answer-${key}`)?.value || '';
  const motivoErro = document.getElementById(`exercise-error-reason-${key}`)?.value || '';
  if (tipo !== 'aberta' && !marcada) {
    showToast('warning', 'Resposta pendente', 'Escolha uma alternativa.');
    return;
  }
  try {
    const r = await apiFetch(apiEndpoint('exerciciosIa', 'action=responder'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        exercicio_planejado_id: loteId,
        exercise_key: key,
        resposta_marcada: marcada,
        resposta_usuario: aberta,
        motivo_erro: motivoErro
      })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel salvar resposta.');
    await loadExercisesForTask(state.currentExerciseTaskId, false);
    await loadEstudaiStats();
    showToast('success', 'Resposta salva', d.acertou === null ? 'Resposta aberta registrada.' : (d.acertou ? 'Boa, resposta correta.' : 'Resposta salva para revisao.'));
  } catch (e) {
    showToast('error', 'Resposta nao salva', e.message || 'Tente novamente.');
  }
}

function regenerarExerciciosTarefa() {
  if (!state.currentExerciseTaskId) {
    showToast('warning', 'Selecione uma tarefa', 'Abra uma tarefa de questoes primeiro.');
    return;
  }
  loadExercisesForTask(state.currentExerciseTaskId, true);
}

async function loadPlannedSimulados() {
  const status = document.getElementById('planned-sims-status');
  const list = document.getElementById('planned-sims-list');
  if (status) status.textContent = 'Carregando simulados do plano...';
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=listar'));
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar simulados planejados.');
    state.plannedSimulados = d.simulados || [];
    if (status) status.textContent = state.plannedSimulados.length ? `${state.plannedSimulados.length} simulado(s) planejado(s)` : 'Nenhum simulado planejado ainda.';
    if (list) list.innerHTML = state.plannedSimulados.length
      ? state.plannedSimulados.map(plannedSimCardHtml).join('')
      : '<div class="empty-state"><p>Simulados do plano aparecem quando houver conteudo suficiente.</p><button class="btn-secondary btn-sm" type="button" onclick="gerarSimuladoDaSemana()">Gerar simulado da semana</button></div>';
  } catch (e) {
    if (status) status.textContent = e.message || 'Erro ao carregar simulados.';
    if (list) list.innerHTML = `<div class="empty-state"><p>${escapeHtml(e.message || 'Erro ao carregar simulados.')}</p></div>`;
  }
}

async function gerarSimuladoDaSemana() {
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=gerar_para_semana'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({})
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel gerar simulado.');
    await loadPlannedSimulados();
    showToast('success', 'Simulado preparado', 'Use apenas quando estiver liberado pelo plano.');
  } catch (e) {
    showToast('warning', 'Simulado ainda nao liberado', e.message || 'Conclua mais conteudos da semana.');
  }
}

function plannedSimCardHtml(sim) {
  const blocked = sim.status === 'bloqueado';
  const questoes = sim.questoes || [];
  const materias = [...new Set(questoes.map(q => q.materia || sim.materia || 'Geral'))].slice(0, 4);
  const tempoEstimado = Math.max(20, questoes.length * 3);
  const button = blocked
    ? `<button class="btn-secondary btn-sm" type="button" disabled>Liberado em ${escapeHtml(formatDate(sim.data_liberacao))}</button>`
    : `<button class="btn-primary btn-sm" type="button" onclick="iniciarSimuladoPlanejado(${sim.id})">${sim.status === 'finalizado' ? 'Ver resultado' : (sim.status === 'iniciado' ? 'Continuar simulado' : 'Iniciar simulado')}</button>`;
  return `
    <div class="task-card">
      <div class="task-main">
        <div class="task-title-row">
          <strong>${escapeHtml(sim.titulo)}</strong>
          <span class="task-badge ${escapeHtml(sim.status)}">${escapeHtml(sim.status === 'bloqueado' ? 'Bloqueado' : sim.status === 'finalizado' ? 'Finalizado' : 'Liberado')}</span>
        </div>
        <p>${escapeHtml(sim.descricao || '')}</p>
        <div class="task-meta">
          <span>${escapeHtml(materias.join(', ') || sim.materia || 'Geral')}</span>
          <span>${questoes.length} questoes</span>
          <span>${escapeHtml(formatDate(sim.data_liberacao))}</span>
          <span>${tempoEstimado} min estimados</span>
          <span>BASE</span>
        </div>
      </div>
      ${button}
    </div>
  `;
}

async function abrirSimuladoDaTarefa(tarefaId) {
  showSection('simulados');
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=gerar_para_tarefa'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tarefa_id: tarefaId })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel abrir simulado.');
    await loadPlannedSimulados();
    if (d.simulado && d.simulado.status !== 'bloqueado') {
      await iniciarSimuladoPlanejado(d.simulado.id);
    } else if (d.simulado) {
      showToast('info', 'Simulado planejado', `Liberado em ${formatDate(d.simulado.data_liberacao)}.`);
    }
  } catch (e) {
    showToast('error', 'Simulado indisponivel', e.message || 'Tente novamente.');
  }
}

async function iniciarSimuladoPlanejado(id) {
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=iniciar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ simulado_id: id })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel iniciar simulado.');
    state.plannedSimAtivo = d.simulado;
    renderPlannedSimActive();
  } catch (e) {
    showToast('warning', 'Simulado bloqueado', e.message || 'Aguarde a liberacao.');
  }
}

function renderPlannedSimActive() {
  const el = document.getElementById('planned-sim-active');
  if (!el) return;
  const sim = state.plannedSimAtivo;
  if (!sim) {
    el.classList.add('hidden');
    el.innerHTML = '';
    return;
  }
  el.classList.remove('hidden');
  const respostas = sim.respostas || {};
  const total = (sim.questoes || []).length;
  const respondidas = Object.keys(respostas).length;
  el.innerHTML = `
    <div class="section-card-header">
      <div>
        <h3>${escapeHtml(sim.titulo)}</h3>
        <p class="text-muted">${respondidas}/${total} respondidas | ${Math.max(0, total - respondidas)} pendentes</p>
      </div>
      <button class="btn-primary btn-sm" type="button" onclick="finalizarSimuladoPlanejado(${sim.id})">Finalizar</button>
    </div>
    <div class="exercise-list">
      ${(sim.questoes || []).map((q, index) => `
        <article class="exercise-card ${respostas[q.id] ? 'answered' : ''}">
          <div class="question-meta">
            <span>${index + 1}</span><span>${escapeHtml(q.materia || sim.materia || 'Geral')}</span><span>${escapeHtml(q.dificuldade || 'medio')}</span>
          </div>
          <p class="question-text">${escapeHtml(q.pergunta)}</p>
          <div class="options compact-options">
            ${Object.entries(q.alternativas || {}).map(([letter, text]) => `
              <label class="option-label">
                <input type="radio" name="planned-sim-${escapeHtml(q.id)}" value="${escapeHtml(letter)}" ${respostas[q.id]?.resposta_marcada === letter ? 'checked' : ''}>
                <span><strong>${escapeHtml(letter)}</strong> ${escapeHtml(text)}</span>
              </label>
            `).join('')}
          </div>
          ${respostas[q.id] ? `<div class="explanation"><strong>${respostas[q.id].acertou ? 'Correta' : 'Revisar'}</strong><p>${escapeHtml(respostas[q.id].avaliacao?.explicacao || q.explicacao || '')}</p></div>` : ''}
          <button class="btn-secondary btn-sm" type="button" onclick="responderSimuladoPlanejado(${sim.id}, ${JSON.stringify(q.id)})">Salvar resposta</button>
        </article>
      `).join('')}
    </div>
  `;
}

async function responderSimuladoPlanejado(simId, key) {
  const marcada = document.querySelector(`input[name="planned-sim-${CSS.escape(key)}"]:checked`)?.value || '';
  if (!marcada) {
    showToast('warning', 'Resposta pendente', 'Escolha uma alternativa.');
    return;
  }
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=responder'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ simulado_planejado_id: simId, question_key: key, resposta_marcada: marcada })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel responder.');
    const reload = await apiFetch(apiEndpoint('simuladosPlanejados', { action: 'carregar', id: simId }));
    const body = await reload.json();
    if (body.ok) state.plannedSimAtivo = body.simulado;
    renderPlannedSimActive();
  } catch (e) {
    showToast('error', 'Resposta nao salva', e.message || 'Tente novamente.');
  }
}

async function finalizarSimuladoPlanejado(simId) {
  const sim = state.plannedSimAtivo;
  const total = sim?.questoes?.length || 0;
  const respondidas = Object.keys(sim?.respostas || {}).length;
  if (!confirm(`Finalizar simulado planejado com ${respondidas}/${total} respondidas?`)) return;
  try {
    const r = await apiFetch(apiEndpoint('simuladosPlanejados', 'action=finalizar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ simulado_id: simId })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel finalizar.');
    state.plannedSimAtivo = null;
    renderPlannedSimActive();
    await loadPlannedSimulados();
    await loadEstudaiStats();
    const pct = d.resultado.percentual || 0;
    showToast('success', 'Simulado finalizado', `${pct}% de acerto. Revise os conteudos com mais erro.`);
  } catch (e) {
    showToast('error', 'Simulado nao finalizado', e.message || 'Tente novamente.');
  }
}

async function abrirRevisaoTarefa(tarefaId) {
  state.currentReviewTaskId = tarefaId;
  showSection('revisao');
  const content = document.getElementById('revisao-content');
  if (content) content.innerHTML = '<div class="empty-state"><p>Carregando revisao do conteudo...</p></div>';
  try {
    let r = await apiFetch(apiEndpoint('revisoesIa', { action: 'carregar_por_tarefa', tarefa_id: tarefaId }));
    let d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar revisao.');
    if (!d.revisao) {
      r = await apiFetch(apiEndpoint('revisoesIa', 'action=gerar_por_tarefa'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tarefa_id: tarefaId })
      });
      d = await r.json();
      if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel gerar revisao.');
    }
    state.currentReview = d.revisao;
    renderContentReview();
  } catch (e) {
    if (content) content.innerHTML = `<div class="empty-state"><p>${escapeHtml(e.message || 'Erro ao carregar revisao.')}</p></div>`;
  }
}

function renderContentReview() {
  const content = document.getElementById('revisao-content');
  if (!content || !state.currentReview) return;
  const rev = state.currentReview.revisao || {};
  content.innerHTML = `
    <div class="card">
      <div class="section-card-header">
        <div>
          <h3 class="card-title" style="margin-bottom:.35rem">${escapeHtml(rev.materia || state.currentReview.materia || 'Revisao')}</h3>
          <p class="text-muted">${escapeHtml(rev.conteudo || state.currentReview.conteudo || '')} | base de questoes</p>
        </div>
        <button class="btn-primary btn-sm" type="button" onclick="concluirTarefa(${Number(state.currentReviewTaskId)})">Marcar revisao concluida</button>
      </div>
      <div class="structured-content">
        <p>${escapeHtml(rev.resumo_revisao || '')}</p>
        <div>
          <h4>Pontos importantes</h4>
          <ul class="clean-list">${(rev.pontos_importantes || []).map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
        </div>
        <div>
          <h4>Erros comuns</h4>
          <ul class="clean-list">${(rev.erros_comuns || []).map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
        </div>
        <div class="explanation"><strong>Exemplo resolvido</strong><p>${escapeHtml(rev.exemplo_resolvido || '')}</p></div>
        <div class="exercise-list">
          ${(rev.questoes_revisao || []).map((q) => `
            <article class="exercise-card">
              <div class="question-meta">
                <span>${escapeHtml(q.materia || rev.materia || 'Geral')}</span>
                ${q.conteudo ? `<span>${escapeHtml(q.conteudo)}</span>` : ''}
                ${q.motivo_erro ? `<span>${escapeHtml(q.motivo_erro)}</span>` : ''}
              </div>
              <p class="question-text">${escapeHtml(q.pergunta || '')}</p>
              <div class="explanation visible">
                <strong>Historico da tentativa</strong>
                ${q.resposta_marcada ? `<p>Voce marcou: ${escapeHtml(q.resposta_marcada)}</p>` : ''}
                <p>${escapeHtml(q.resposta || '')}</p>
                <p>${escapeHtml(q.explicacao || '')}</p>
              </div>
            </article>
          `).join('')}
        </div>
      </div>
    </div>
  `;
}

// ====================================
// QUESTOES DO DIA
// ====================================
async function loadQuestoes() {
  const r = await apiFetch(apiEndpoint('questoes', 'action=carregar'));
  const d = await r.json();

  state.questoes = d.questoes || [];
  state.respostas = d.respostas || {};
  state.gabarito = d.gabarito || null;
  state.finalizado = d.finalizado;

  updateDashboard(d);

  if (state.questoes.length) {
    renderDots();
    renderQuestion(state.currentIdx);
    if (state.finalizado) showResultCard(d.desempenho);
  } else if (d.aviso) {
    const questionText = document.getElementById('q-enunciado');
    const options = document.getElementById('q-options');
    const progress = document.getElementById('progress-txt');
    if (questionText) questionText.textContent = d.aviso;
    if (options) options.innerHTML = '<div class="empty-state"><p>A base de questoes ainda precisa ser alimentada para este treino.</p></div>';
    if (progress) progress.textContent = '0/0 respondidas';
  }
}

function updateDashboard(d) {
  const qs = d.questoes || [];
  const rs = d.respostas || {};
  const mat = qs.filter(q => q.materia === 'matematica');
  const por = qs.filter(q => q.materia === 'portugues');
  const matResp = mat.filter(q => rs[q.id]).length;
  const porResp = por.filter(q => rs[q.id]).length;

  document.getElementById('prog-mat').style.width = (matResp / 10 * 100) + '%';
  document.getElementById('prog-por').style.width = (porResp / 10 * 100) + '%';
  document.getElementById('prog-mat-txt').textContent = matResp + '/10';
  document.getElementById('prog-por-txt').textContent = porResp + '/10';

  const total = Object.keys(rs).length;
  if (d.finalizado) {
    document.getElementById('dash-btn-txt').textContent = 'Ver resultado';
  } else if (total > 0) {
    document.getElementById('dash-btn-txt').textContent = `Continuar (${total}/20)`;
  }
}

function renderDots() {
  const container = document.getElementById('dots-nav');
  container.innerHTML = '';
  state.questoes.forEach((q, i) => {
    const d = document.createElement('button');
    d.className = 'dot';
    d.textContent = i + 1;
    d.title = q.materia === 'matematica' ? 'Matematica' : 'Portugues';
    d.onclick = () => { state.currentIdx = i; renderQuestion(i); renderDots(); };
    if (state.respostas[q.id]) d.classList.add('answered');
    if (i === state.currentIdx) d.classList.add('current');
    container.appendChild(d);
  });

  const resp = Object.keys(state.respostas).length;
  const total = state.questoes.length;
  document.getElementById('progress-txt').textContent =
    state.finalizado ? `Dia finalizado - ${resp}/${total} respondidas`
                     : `${resp}/${total} respondidas`;
}

function renderQuestion(idx) {
  const q = state.questoes[idx];
  if (!q) return;

  const isRespondida = !!state.respostas[q.id];
  const respUsuario = state.respostas[q.id]?.marcada;
  const gab = state.gabarito?.[q.id];

  document.getElementById('q-meta').innerHTML = `
    <span class="badge ${q.materia === 'matematica' ? 'badge-mat' : 'badge-por'}">
      ${escapeHtml(q.materia || 'Questao')}
    </span>
    <span class="badge badge-num">Questao ${idx + 1} de ${state.questoes.length}</span>
  `;

  document.getElementById('q-enunciado').textContent = q.enunciado;

  const opts = document.getElementById('q-options');
  opts.innerHTML = '';
  ['a','b','c','d','e'].forEach(letra => {
    const div = document.createElement('div');
    const upper = letra.toUpperCase();
    let extraClass = '';
    
    if (isRespondida) {
      if (gab) {
        if (upper === gab.correta) extraClass = ' correct';
        else if (upper === respUsuario && upper !== gab.correta) extraClass = ' wrong';
      } else if (upper === respUsuario) {
        extraClass = ' selected';
      }
    }
    
    div.className = 'option' + extraClass + (state.finalizado ? ' disabled' : '');
    div.innerHTML = `
      <div class="opt-letter">${upper}</div>
      <div class="opt-text">${escapeHtml(q['alternativa_' + letra])}</div>
    `;

    if (!state.finalizado && !isRespondida) {
      div.onclick = () => responder(q.id, upper);
    }
    opts.appendChild(div);
  });

  // Explanation
  const exp = document.getElementById('q-explanation');
  if (gab && state.finalizado) {
    exp.innerHTML = `<strong>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
      </svg>
      Explicacao
    </strong> ${escapeHtml(gab.explicacao)}`;
    exp.classList.add('visible');
  } else {
    exp.classList.remove('visible');
  }

  // Anotacao section
  document.getElementById('anotacao-section').style.display = state.finalizado ? 'block' : 'none';
  loadAnotacaoQuestao(q.id);

  // Nav buttons
  document.getElementById('btn-prev').disabled = idx === 0;
  document.getElementById('btn-next').disabled = idx === state.questoes.length - 1;

  const allAnswered = Object.keys(state.respostas).length === state.questoes.length;
  const finBtn = document.getElementById('btn-finish');
  finBtn.disabled = !allAnswered || state.finalizado;
  finBtn.innerHTML = state.finalizado 
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Finalizado`
    : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Finalizar dia`;

  state.currentIdx = idx;
}

function navigateQ(dir) {
  const next = state.currentIdx + dir;
  if (next < 0 || next >= state.questoes.length) return;
  state.currentIdx = next;
  renderQuestion(next);
  renderDots();
}

async function responder(questaoId, resposta) {
  const fd = new FormData();
  fd.append('action', 'responder');
  fd.append('questao_id', questaoId);
  fd.append('resposta', resposta);

  const r = await apiFetch(apiEndpoint('questoes'), { method: 'POST', body: fd });
  const d = await r.json();

  if (d.ok) {
    state.respostas[questaoId] = { marcada: resposta, acertou: d.acertou };
    renderQuestion(state.currentIdx);
    renderDots();
    updateDashboard({ questoes: state.questoes, respostas: state.respostas, finalizado: false });

    if (state.currentIdx < state.questoes.length - 1) {
      setTimeout(() => { navigateQ(1); }, 600);
    }
  }
}

async function finalizarDia() {
  if (!confirm('Finalizar o dia? Voce nao podera alterar suas respostas.')) return;

  stopTimer();

  const fd = new FormData();
  fd.append('action', 'finalizar');
  fd.append('tempo_seg', state.timerSec);

  const r = await apiFetch(apiEndpoint('questoes'), { method: 'POST', body: fd });
  const d = await r.json();

  if (d.ok) {
    state.gabarito = d.gabarito;
    state.finalizado = true;
    renderQuestion(state.currentIdx);
    renderDots();
    showResultCard({ acertos: d.acertos, erros: d.erros, tempo_seg: state.timerSec });
    await loadHistorico();
    await loadMeta();
    verificarConquistas();
  }
}

function showResultCard(desemp) {
  if (!desemp) return;
  const total = state.questoes.length || 20;
  const acertos = desemp.acertos || 0;
  const erros = desemp.erros || 0;
  const pct = Math.round(acertos / total * 100);

  const feedback = pct >= 80 ? 'Excelente desempenho! Voce arrasou!' : pct >= 50 ? 'Bom trabalho! Continue assim!' : 'Nao desista! Revise o conteudo e tente novamente.';

  document.getElementById('res-icon').innerHTML = resultIcon(pct);
  document.getElementById('res-score').textContent = `${acertos}/${total}`;
  document.getElementById('res-feedback').textContent = feedback;
  document.getElementById('res-acertos').textContent = acertos;
  document.getElementById('res-erros').textContent = erros;
  document.getElementById('res-tempo').textContent = formatTime(desemp.tempo_seg || 0);

  document.getElementById('result-card').classList.remove('hidden');
}

// ====================================
// TIMER
// ====================================
function startTimer() {
  if (state.timerInterval || state.finalizado) return;
  state.timerInterval = setInterval(() => {
    state.timerSec++;
    document.getElementById('timer-display').textContent = formatTime(state.timerSec);
  }, 1000);
}

function stopTimer() {
  clearInterval(state.timerInterval);
  state.timerInterval = null;
}

function formatTime(sec) {
  const m = String(Math.floor(sec / 60)).padStart(2, '0');
  const s = String(sec % 60).padStart(2, '0');
  return `${m}:${s}`;
}

// ====================================
// HISTORICO
// ====================================
async function loadHistorico() {
  const r = await apiFetch(apiEndpoint('historico'));
  const d = await r.json();

  const t = d.totais || {};
  const dias = t.dias_estudados || 0;
  const acertos = t.total_acertos || 0;
  const total = (acertos + (t.total_erros || 0)) || 1;
  const pct = Math.round(acertos / total * 100);

  document.getElementById('stat-dias').textContent = dias;
  document.getElementById('stat-acertos').textContent = acertos;
  document.getElementById('stat-pct').textContent = pct + '%';
  document.getElementById('stat-streak').textContent = d.streak || 0;

  if (d.streak > 0) {
    document.getElementById('streak-banner').classList.remove('hidden');
    document.getElementById('streak-val').textContent = `${d.streak} dia${d.streak > 1 ? 's' : ''} seguido${d.streak > 1 ? 's' : ''}!`;
  }

  const list = document.getElementById('hist-list');
  if (!d.historico?.length) {
    list.innerHTML = `
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p>Nenhum dia concluido ainda. Comece suas questoes!</p>
      </div>
    `;
    return;
  }

  list.innerHTML = d.historico.slice(0, 7).map(h => `
    <div class="hist-row">
      <span class="hist-date">${formatDate(h.data)}</span>
      <div class="hist-bar-wrap">
        <div class="hist-bar"><div class="hist-bar-fill" style="width:${h.percentual}%"></div></div>
      </div>
      <span class="hist-pct" style="color:${h.percentual>=70?'var(--green)':h.percentual>=50?'var(--accent)':'var(--red)'}">${h.percentual}%</span>
      <span class="hist-detail">${h.acertos}/${parseInt(h.acertos)+parseInt(h.erros)}</span>
    </div>
  `).join('');
}

function formatDate(iso) {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}`;
}

// ====================================
// META SEMANAL
// ====================================
async function loadMeta() {
  try {
    const r = await apiFetch(apiEndpoint('metas', 'action=carregar'));
    const d = await r.json();
    if (d.ok && d.meta) {
      document.getElementById('meta-progress').style.width = d.meta.percentual + '%';
      document.getElementById('meta-feitas').textContent = d.meta.questoes_feitas;
      document.getElementById('meta-total').textContent = d.meta.meta_questoes;
      document.getElementById('meta-pct').textContent = d.meta.percentual + '%';
      
      if (d.meta.concluida) {
        document.getElementById('meta-pct').innerHTML = '<span style="color:var(--green)">Concluida!</span>';
      }
    }
  } catch (e) {}
}

function openMetaModal() {
  document.getElementById('modal-meta').classList.add('active');
}

async function salvarMeta() {
  const meta = parseInt(document.getElementById('meta-input').value) || 100;
  const fd = new FormData();
  fd.append('action', 'definir');
  fd.append('meta_questoes', meta);
  
  await apiFetch(apiEndpoint('metas'), { method: 'POST', body: fd });
  closeModal('modal-meta');
  await loadMeta();
  showToast('success', 'Meta atualizada!', `Nova meta: ${meta} questoes por semana`);
}

// ====================================
// REVISAO
// ====================================
async function loadRevisaoStats() {
  try {
    const r = await apiFetch(apiEndpoint('revisao', 'action=estatisticas'));
    const d = await r.json();
    if (d.ok) {
      const total = d.total || 0;
      document.getElementById('revisao-mat').textContent = (d.pendentes?.matematica || 0) + ' matematica';
      document.getElementById('revisao-por').textContent = (d.pendentes?.portugues || 0) + ' portugues';
      
      if (total > 0) {
        document.getElementById('revisao-count').textContent = `Revisao (${total})`;
        const navRevisao = document.getElementById('nav-revisao');
        if (!navRevisao.querySelector('.nav-badge')) {
          const badge = document.createElement('span');
          badge.className = 'nav-badge';
          navRevisao.appendChild(badge);
        }
      }
    }
  } catch (e) {}
}

async function loadRevisao(materia, trigger) {
  document.querySelectorAll('#revisao-tabs .tab').forEach(t => t.classList.remove('active'));
  const activeTab = trigger || document.querySelector(`#revisao-tabs .tab[data-revisao="${materia}"]`);
  activeTab?.classList.add('active');
  
  const url = materia === 'todas'
    ? apiEndpoint('revisao', { action: 'carregar', limite: 20 })
    : apiEndpoint('revisao', { action: 'carregar', materia, limite: 20 });
  
  try {
    const r = await apiFetch(url);
    const d = await r.json();
    
    if (!d.ok || !d.questoes?.length) {
      document.getElementById('revisao-content').innerHTML = `
        <div class="card">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p>Nenhuma questao para revisar nesta categoria. Continue praticando!</p>
          </div>
        </div>
      `;
      return;
    }
    
    state.revisaoQuestoes = d.questoes;
    state.revisaoIdx = 0;
    state.revisaoRespostas = {};
    renderRevisao();
  } catch (e) {
    console.error(e);
  }
}

function renderRevisao() {
  const q = state.revisaoQuestoes[state.revisaoIdx];
  if (!q) return;
  
  const isRespondida = !!state.revisaoRespostas[q.id];
  const resp = state.revisaoRespostas[q.id];
  
  let html = `
    <div class="card">
      <div class="question-meta">
        <span class="badge ${q.materia === 'matematica' ? 'badge-mat' : 'badge-por'}">
          ${q.materia === 'matematica' ? 'Matematica' : 'Portugues'}
        </span>
        <span class="badge badge-num">Questao ${state.revisaoIdx + 1} de ${state.revisaoQuestoes.length}</span>
        ${q.conteudo ? `<span class="badge badge-num">${escapeHtml(q.conteudo)}</span>` : ''}
        ${q.resposta_anterior ? `<span class="badge badge-warning">Antes: ${escapeHtml(q.resposta_anterior)}</span>` : ''}
      </div>
      <p class="question-text">${escapeHtml(q.enunciado)}</p>
      <div class="options">
  `;
  
  ['a','b','c','d','e'].forEach(letra => {
    const upper = letra.toUpperCase();
    let extraClass = '';
    
    if (isRespondida) {
      if (upper === resp.correta) extraClass = ' correct';
      else if (upper === resp.marcada && upper !== resp.correta) extraClass = ' wrong';
    }
    
    html += `
      <div class="option${extraClass}${isRespondida ? ' disabled' : ''}" onclick="${isRespondida ? '' : `responderRevisao(${q.id}, '${upper}')`}">
        <div class="opt-letter">${upper}</div>
        <div class="opt-text">${escapeHtml(q['alternativa_' + letra])}</div>
      </div>
    `;
  });
  
  html += '</div>';
  
  if (isRespondida) {
    html += `
      <div class="explanation visible">
        <strong>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
          </svg>
          Explicacao
        </strong> ${escapeHtml(resp.explicacao)}
        <p>Alternativa correta: ${escapeHtml(resp.correta || '')}</p>
      </div>
    `;
  }
  
  html += `
      <div class="q-nav">
        <button class="btn-nav" onclick="navigateRevisao(-1)" ${state.revisaoIdx === 0 ? 'disabled' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
          Anterior
        </button>
        <button class="btn-nav" onclick="navigateRevisao(1)" ${state.revisaoIdx === state.revisaoQuestoes.length - 1 ? 'disabled' : ''}>
          Proxima
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </button>
      </div>
    </div>
  `;
  
  document.getElementById('revisao-content').innerHTML = html;
}

async function responderRevisao(questaoId, resposta) {
  const fd = new FormData();
  fd.append('action', 'responder');
  fd.append('questao_id', questaoId);
  fd.append('resposta', resposta);
  
  const r = await apiFetch(apiEndpoint('revisao'), { method: 'POST', body: fd });
  const d = await r.json();
  
  if (d.ok) {
    state.revisaoRespostas[questaoId] = {
      marcada: resposta,
      acertou: d.acertou,
      correta: d.correta,
      explicacao: d.explicacao
    };
    renderRevisao();
    await loadRevisaoStats();
    verificarConquistas();
  }
}

function navigateRevisao(dir) {
  const next = state.revisaoIdx + dir;
  if (next < 0 || next >= state.revisaoQuestoes.length) return;
  state.revisaoIdx = next;
  renderRevisao();
}

// ====================================
// SIMULADOS
// ====================================
async function loadSimulados() {
  if (state.simAtivo && !state.simFinalizado) {
    return; // Ja tem simulado ativo
  }
  
  try {
    const r = await apiFetch(apiEndpoint('simulados', 'action=listar'));
    const d = await r.json();
    
    if (d.ok) {
      state.simulados = d.simulados || [];
      renderSimuladosList();
    }
  } catch (e) {}
}

function renderSimuladosList() {
  document.getElementById('simulado-active').classList.add('hidden');
  document.getElementById('simulados-list').classList.remove('hidden');
  
  if (!state.simulados.length) {
    document.getElementById('simulados-list').innerHTML = `
      <div class="empty-state">
        <p>Nenhum simulado disponivel no momento.</p>
      </div>
    `;
    return;
  }
  
  document.getElementById('simulados-list').innerHTML = state.simulados.map(s => `
    <div class="simulado-card">
      <h4 class="simulado-title">${escapeHtml(s.titulo)}</h4>
      <p class="simulado-desc">${escapeHtml(s.descricao || 'Teste seus conhecimentos!')}</p>
      <div class="simulado-meta">
        <span>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          ${s.duracao_min} min
        </span>
        <span>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          ${s.total_questoes} questoes
        </span>
        ${s.tentativas > 0 ? `<span class="badge badge-success">${s.tentativas}x feito</span>` : ''}
      </div>
      ${s.melhor_nota ? `<p style="font-size:0.85rem;color:var(--text-muted)">Melhor nota: <strong style="color:var(--green)">${s.melhor_nota}%</strong></p>` : ''}
      <div class="simulado-actions">
        <button class="btn-primary btn-sm" onclick="iniciarSimulado(${Number(s.id) || 0})">
          Iniciar
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </button>
      </div>
    </div>
  `).join('');
}

async function iniciarSimulado(id) {
  const fd = new FormData();
  fd.append('action', 'iniciar');
  fd.append('simulado_id', id);
  
  const r = await apiFetch(apiEndpoint('simulados'), { method: 'POST', body: fd });
  const d = await r.json();
  
  if (d.ok) {
    state.simAtivo = {
      tentativa_id: d.tentativa_id,
      titulo: d.simulado.titulo,
      duracao_min: d.simulado.duracao_min,
      total_questoes: d.simulado.total_questoes
    };
    state.simQuestoes = d.questoes;
    state.simRespostas = d.respostas;
    state.simIdx = 0;
    state.simTimerSec = 0;
    state.simFinalizado = false;
    state.simGabarito = null;
    
    document.getElementById('simulados-list').classList.add('hidden');
    document.getElementById('simulado-active').classList.remove('hidden');
    document.getElementById('simulado-result').classList.add('hidden');
    document.getElementById('simulado-question').classList.remove('hidden');
    document.getElementById('simulado-titulo').textContent = d.simulado.titulo;
    
    renderSimuladoDots();
    renderSimuladoQuestion();
    startSimuladoTimer();
  }
}

function startSimuladoTimer() {
  if (state.simTimer) clearInterval(state.simTimer);
  state.simTimer = setInterval(() => {
    state.simTimerSec++;
    document.getElementById('simulado-timer').textContent = formatTime(state.simTimerSec);
  }, 1000);
}

function stopSimuladoTimer() {
  clearInterval(state.simTimer);
  state.simTimer = null;
}

function renderSimuladoDots() {
  const container = document.getElementById('simulado-dots');
  container.innerHTML = '';
  state.simQuestoes.forEach((q, i) => {
    const d = document.createElement('button');
    d.className = 'dot';
    d.textContent = i + 1;
    d.onclick = () => { state.simIdx = i; renderSimuladoDots(); renderSimuladoQuestion(); };
    if (state.simRespostas[q.id]) d.classList.add('answered');
    if (i === state.simIdx) d.classList.add('current');
    container.appendChild(d);
  });
  
  const resp = Object.keys(state.simRespostas).length;
  document.getElementById('simulado-progress').textContent = `${resp}/${state.simQuestoes.length} respondidas`;
}

function renderSimuladoQuestion() {
  const q = state.simQuestoes[state.simIdx];
  if (!q) return;
  
  const isRespondida = !!state.simRespostas[q.id];
  const respUsuario = state.simRespostas[q.id]?.marcada;
  const gab = state.simGabarito?.[q.id];
  
  document.getElementById('sim-q-meta').innerHTML = `
    <span class="badge ${q.materia === 'matematica' ? 'badge-mat' : 'badge-por'}">
      ${q.materia === 'matematica' ? 'Matematica' : 'Portugues'}
    </span>
    <span class="badge badge-num">Questao ${state.simIdx + 1} de ${state.simQuestoes.length}</span>
  `;
  
  document.getElementById('sim-q-enunciado').textContent = q.enunciado;
  
  const opts = document.getElementById('sim-q-options');
  opts.innerHTML = '';
  ['a','b','c','d','e'].forEach(letra => {
    const div = document.createElement('div');
    const upper = letra.toUpperCase();
    let extraClass = '';
    
    if (state.simFinalizado && gab) {
      if (upper === gab.correta) extraClass = ' correct';
      else if (upper === respUsuario && upper !== gab.correta) extraClass = ' wrong';
    } else if (upper === respUsuario) {
      extraClass = ' selected';
    }
    
    div.className = 'option' + extraClass + (state.simFinalizado ? ' disabled' : '');
    div.innerHTML = `
      <div class="opt-letter">${upper}</div>
      <div class="opt-text">${escapeHtml(q['alternativa_' + letra])}</div>
    `;
    
    if (!state.simFinalizado) {
      div.onclick = () => responderSimulado(q.id, upper);
    }
    opts.appendChild(div);
  });
  
  // Explanation
  const exp = document.getElementById('sim-q-explanation');
  if (gab && state.simFinalizado) {
    exp.innerHTML = `<strong>Explicacao</strong> ${escapeHtml(gab.explicacao)}`;
    exp.classList.remove('hidden');
    exp.classList.add('visible');
  } else {
    exp.classList.add('hidden');
    exp.classList.remove('visible');
  }
  
  // Nav buttons
  document.getElementById('sim-btn-prev').disabled = state.simIdx === 0;
  document.getElementById('sim-btn-next').disabled = state.simIdx === state.simQuestoes.length - 1;
  
  const allAnswered = Object.keys(state.simRespostas).length === state.simQuestoes.length;
  document.getElementById('sim-btn-finish').disabled = !allAnswered || state.simFinalizado;
}

function navigateSimulado(dir) {
  const next = state.simIdx + dir;
  if (next < 0 || next >= state.simQuestoes.length) return;
  state.simIdx = next;
  renderSimuladoDots();
  renderSimuladoQuestion();
}

async function responderSimulado(questaoId, resposta) {
  const fd = new FormData();
  fd.append('action', 'responder');
  fd.append('tentativa_id', state.simAtivo.tentativa_id);
  fd.append('questao_id', questaoId);
  fd.append('resposta', resposta);
  
  const r = await apiFetch(apiEndpoint('simulados'), { method: 'POST', body: fd });
  const d = await r.json();
  
  if (d.ok) {
    state.simRespostas[questaoId] = { marcada: resposta };
    renderSimuladoDots();
    renderSimuladoQuestion();
    
    if (state.simIdx < state.simQuestoes.length - 1) {
      setTimeout(() => { navigateSimulado(1); }, 400);
    }
  }
}

async function finalizarSimulado() {
  if (!confirm('Finalizar o simulado?')) return;
  
  stopSimuladoTimer();
  
  const fd = new FormData();
  fd.append('action', 'finalizar');
  fd.append('tentativa_id', state.simAtivo.tentativa_id);
  fd.append('tempo_seg', state.simTimerSec);
  
  const r = await apiFetch(apiEndpoint('simulados'), { method: 'POST', body: fd });
  const d = await r.json();
  
  if (d.ok) {
    state.simFinalizado = true;
    state.simGabarito = d.gabarito;
    
    const pct = d.percentual;
    const feedback = pct >= 80 ? 'Excelente!' : pct >= 50 ? 'Bom trabalho!' : 'Continue praticando!';
    
    document.getElementById('sim-res-icon').innerHTML = resultIcon(pct);
    document.getElementById('sim-res-score').textContent = `${d.acertos}/${d.total}`;
    document.getElementById('sim-res-feedback').textContent = feedback;
    document.getElementById('sim-res-acertos').textContent = d.acertos;
    document.getElementById('sim-res-erros').textContent = d.erros;
    document.getElementById('sim-res-tempo').textContent = formatTime(d.tempo_seg);
    
    document.getElementById('simulado-question').classList.add('hidden');
    document.getElementById('simulado-result').classList.remove('hidden');
    
    verificarConquistas();
  }
}

function voltarSimulados() {
  state.simAtivo = null;
  state.simFinalizado = false;
  loadSimulados();
}

// ====================================
// ESTATISTICAS
// ====================================
function estatisticasProximoFoco(d) {
  const tarefas = d.tarefas || {};
  const geral = d.geral || {};
  if ((tarefas.atrasadas || 0) > 0) return 'Recuperar atrasadas';
  if ((tarefas.progresso_semanal || 0) < 60) return 'Concluir rotina';
  if ((geral.total_questoes || 0) === 0) return 'Responder questoes';
  if ((geral.percentual || 0) < 70) return 'Praticar pontos fracos';
  return 'Revisao leve';
}

async function loadEstatisticas() {
  try {
    const r = await apiFetch(apiEndpoint('estatisticas', 'action=geral'));
    const d = await r.json();
    
    if (d.ok) {
      const g = d.geral;
      const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
      };
      setText('est-total', `${d.tarefas?.progresso_semanal || 0}%`);
      setText('est-acertos', d.tarefas?.concluidas || 0);
      setText('est-erros', g.total_erros);
      setText('est-pct-geral', (g.percentual || 0) + '%');
      setText('est-dias', (g.percentual || 0) + '%');
      setText('est-maior-streak', d.maior_streak);
      setText('est-tarefas', d.tarefas?.concluidas || 0);
      setText('est-atrasadas', d.tarefas?.atrasadas || 0);
      setText('est-simulados', d.simulados_finalizados || 0);
      setText('est-tempo-planejado', `${d.tarefas?.tempo_planejado || 0}min`);
      setText('est-tempo-realizado', `${d.tarefas?.tempo_realizado || 0}min`);
      setText('est-progresso-semanal', `${d.tarefas?.progresso_semanal || 0}%`);
      setText('est-proximo-foco', estatisticasProximoFoco(d));
      setText('est-questoes-respondidas', g.total_questoes);
      setText('est-dias-estudados', d.dias_estudados);
      
      const mat = d.por_materia?.matematica;
      const por = d.por_materia?.portugues;
      document.getElementById('est-mat-pct').textContent = (mat?.percentual || 0) + '%';
      document.getElementById('est-por-pct').textContent = (por?.percentual || 0) + '%';
      
      const dif = d.por_dificuldade || {};
      document.getElementById('est-facil-pct').textContent = (dif.facil?.percentual || 0) + '%';
      document.getElementById('est-medio-pct').textContent = (dif.medio?.percentual || 0) + '%';
      document.getElementById('est-dificil-pct').textContent = (dif.dificil?.percentual || 0) + '%';
    }
    
    // Evolucao
    const r2 = await apiFetch(apiEndpoint('estatisticas', 'action=evolucao&dias=14'));
    const d2 = await r2.json();
    
    if (d2.ok && d2.evolucao?.length) {
      const chart = document.getElementById('evolucao-chart');
      const maxPct = Math.max(...d2.evolucao.map(e => parseFloat(e.percentual) || 0), 100);
      
      chart.innerHTML = d2.evolucao.map(e => {
        const height = ((parseFloat(e.percentual) || 0) / maxPct * 100);
        const [y, m, day] = e.data.split('-');
        return `<div class="chart-bar" style="height:${Math.max(height, 5)}%" data-label="${day}/${m}"></div>`;
      }).join('');
    }
  } catch (e) {
    console.error(e);
  }
}

// ====================================
// CONQUISTAS
// ====================================
async function loadConquistas() {
  try {
    const r = await apiFetch(apiEndpoint('conquistas', 'action=listar'));
    const d = await r.json();
    
    if (d.ok) {
      state.conquistas = d.conquistas;
      document.getElementById('conquistas-count').textContent = `${d.desbloqueadas}/${d.total}`;
      document.getElementById('conquistas-nivel').textContent = d.gamificacao?.nivel || 1;
      document.getElementById('conquistas-xp').textContent = d.gamificacao?.xp || 0;
      
      let html = '';
      Object.entries(d.conquistas).forEach(([cat, items]) => {
        items.forEach(c => {
          html += `
            <div class="conquista-card ${c.desbloqueada ? '' : 'locked'}">
              <div class="conquista-icon">${iconSvg[c.icone] || iconSvg.award}</div>
              <div class="conquista-name">${escapeHtml(c.nome)}</div>
              <div class="conquista-desc">${escapeHtml(c.descricao)}</div>
              ${c.desbloqueada ? `<div class="conquista-date">Desbloqueada em ${formatDate(c.desbloqueada_em.split(' ')[0])}</div>` : ''}
            </div>
          `;
        });
      });
      
      document.getElementById('conquistas-grid').innerHTML = html || '<p class="text-muted">Nenhuma conquista disponivel.</p>';
    }
  } catch (e) {}
}

async function verificarConquistas() {
  try {
    const r = await apiFetch(apiEndpoint('conquistas', 'action=verificar'), { method: 'POST' });
    const d = await r.json();
    
    if (d.ok && d.novas_conquistas?.length) {
      d.novas_conquistas.forEach(c => {
        showToast('success', 'Conquista desbloqueada!', `${c.nome}: ${c.descricao}`);
      });
    }
  } catch (e) {}
}

// ====================================
// ANOTACOES
// ====================================
async function loadAnotacoes() {
  try {
    const r = await apiFetch(apiEndpoint('anotacoes', 'action=listar'));
    const d = await r.json();
    
    if (!d.ok || !d.anotacoes?.length) {
      document.getElementById('anotacoes-list').innerHTML = `
        <div class="card">
          <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <p>Voce ainda nao fez nenhuma anotacao.</p>
          </div>
        </div>
      `;
      return;
    }
    
    document.getElementById('anotacoes-list').innerHTML = d.anotacoes.map(a => `
      <div class="anotacao-item">
        <span class="badge ${a.materia === 'matematica' ? 'badge-mat' : 'badge-por'}" style="margin-bottom:0.5rem;">
          ${a.materia === 'matematica' ? 'Matematica' : 'Portugues'}
        </span>
        <div class="anotacao-preview">${escapeHtml(a.texto)}</div>
        <div class="anotacao-meta">
          <span>${escapeHtml(a.enunciado_preview)}</span>
          <span>${formatDate(a.atualizada_em.split(' ')[0])}</span>
        </div>
      </div>
    `).join('');
  } catch (e) {}
}

async function loadAnotacaoQuestao(questaoId) {
  try {
    const r = await apiFetch(apiEndpoint('anotacoes', { action: 'carregar', questao_id: questaoId }));
    const d = await r.json();
    
    const textarea = document.getElementById('anotacao-text');
    const btn = document.getElementById('btn-anotacao');
    
    if (d.ok && d.anotacao) {
      textarea.value = d.anotacao.texto;
      btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Editar anotacao
      `;
    } else {
      textarea.value = '';
      btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Anotacao
      `;
    }
  } catch (e) {}
}

function toggleAnotacao() {
  document.getElementById('anotacao-box').classList.toggle('hidden');
}

async function salvarAnotacao() {
  const q = state.questoes[state.currentIdx];
  if (!q) return;
  
  const texto = document.getElementById('anotacao-text').value.trim();
  
  const fd = new FormData();
  fd.append('action', 'salvar');
  fd.append('questao_id', q.id);
  fd.append('texto', texto);
  
  const r = await apiFetch(apiEndpoint('anotacoes'), { method: 'POST', body: fd });
  const d = await r.json();
  
  if (d.ok) {
    showToast('success', 'Anotacao salva!', 'Sua anotacao foi salva com sucesso.');
    document.getElementById('anotacao-box').classList.add('hidden');
    verificarConquistas();
  }
}

// ====================================
// REDACAO
// ====================================
async function loadRedacoes() {
  const list = document.getElementById('redacao-historico');
  if (!list) return;
  try {
    const r = await apiFetch(apiEndpoint('redacao', 'action=listar'));
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel carregar redacoes.');
    state.redacoes = d.redacoes || [];
    renderRedacoes();
  } catch (e) {
    list.innerHTML = `<div class="empty-state"><p>${escapeHtml(e.message || 'Erro ao carregar historico.')}</p></div>`;
  }
}

function renderRedacoes() {
  const list = document.getElementById('redacao-historico');
  if (!list) return;
  if (!state.redacoes.length) {
    list.innerHTML = '<div class="empty-state"><p>Nenhuma redacao salva ainda.</p></div>';
    return;
  }
  list.innerHTML = state.redacoes.map(redacao => `
    <div class="task-card">
      <div class="task-main">
        <div class="task-title-row">
          <strong>${escapeHtml(redacao.tema)}</strong>
          <span class="task-badge ${redacao.status === 'analisada' ? 'concluida' : 'pendente'}">${escapeHtml(redacao.status || 'rascunho')}</span>
        </div>
        <div class="task-meta">
          <span>${redacao.atualizada_em ? escapeHtml(formatDate(redacao.atualizada_em.split(' ')[0])) : 'Sem data'}</span>
          <span>Estimativa nao oficial</span>
        </div>
      </div>
      <div class="task-actions">
        <button class="btn-secondary btn-sm" type="button" onclick="abrirRedacao(${Number(redacao.id)})">Abrir</button>
      </div>
    </div>
  `).join('');
}

function abrirRedacao(id) {
  const redacao = state.redacoes.find(item => Number(item.id) === Number(id));
  if (!redacao) return;
  state.redacaoAtualId = redacao.id;
  document.getElementById('redacao-tema').value = redacao.tema || '';
  document.getElementById('redacao-texto').value = redacao.texto || '';
  renderRedacaoAnalise(redacao.analise || null);
}

async function salvarRedacao() {
  const tema = document.getElementById('redacao-tema').value.trim();
  const texto = document.getElementById('redacao-texto').value.trim();
  if (!tema || !texto) {
    showToast('warning', 'Redacao incompleta', 'Informe tema e texto para salvar.');
    return;
  }
  try {
    const r = await apiFetch(apiEndpoint('redacao', 'action=salvar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ redacao_id: state.redacaoAtualId, tema, texto })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao foi possivel salvar.');
    state.redacaoAtualId = d.redacao_id || state.redacaoAtualId;
    await loadRedacoes();
    verificarConquistas();
    showToast('success', 'Redacao salva', d.persistido === false ? 'Rascunho mantido nesta sessao; aplique a migration para historico.' : 'Historico atualizado.');
  } catch (e) {
    showToast('error', 'Redacao nao salva', e.message || 'Tente novamente.');
  }
}

async function analisarRedacao() {
  const tema = document.getElementById('redacao-tema').value.trim();
  const texto = document.getElementById('redacao-texto').value.trim();
  const status = document.getElementById('redacao-status');
  if (!tema || texto.length < 300) {
    showToast('warning', 'Texto curto', 'Informe um tema e um texto mais desenvolvido.');
    return;
  }
  if (status) status.textContent = 'Gerando analise orientativa...';
  try {
    const r = await apiFetch(apiEndpoint('redacao', 'action=analisar'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ redacao_id: state.redacaoAtualId, tema, texto })
    });
    const d = await r.json();
    if (!r.ok || !d.ok) throw new Error(d.erro || 'Nao conseguimos analisar agora.');
    state.redacaoAtualId = d.redacao_id || state.redacaoAtualId;
    renderRedacaoAnalise(d.analise);
    await loadRedacoes();
    verificarConquistas();
    if (status) status.textContent = 'Analise orientativa pronta. Estimativa nao oficial.';
    showToast('success', 'Analise pronta', 'Comentarios por competencia foram gerados como apoio ao estudo.');
  } catch (e) {
    if (status) status.textContent = 'Nao foi possivel analisar agora.';
    showToast('error', 'Analise indisponivel', e.message || 'Tente novamente em alguns minutos.');
  }
}

function renderRedacaoAnalise(analise) {
  const el = document.getElementById('redacao-analise');
  if (!el) return;
  if (!analise || !Object.keys(analise).length) {
    el.className = 'structured-content empty';
    el.innerHTML = '<p class="text-muted">A analise aparecera aqui com comentarios por competencia, pontos fortes e proximos passos.</p>';
    return;
  }
  const competencias = safeArray(analise.competencias);
  const pontosFortes = safeArray(analise.pontos_fortes);
  const pontosMelhoria = safeArray(analise.pontos_de_melhoria).length
    ? safeArray(analise.pontos_de_melhoria)
    : safeArray(analise.pontos_melhoria).length
      ? safeArray(analise.pontos_melhoria)
      : safeArray(analise.sugestoes);
  const proximosPassos = safeArray(analise.proximos_passos);
  el.className = 'structured-content';
  el.innerHTML = `
    <div class="notice-card warning compact-alert">
      <p>Analise orientativa. Nao substitui corretor humano nem correcao oficial.</p>
    </div>
    <p>${escapeHtml(analise.resumo || analise.aviso || '')}</p>
    <div>
      <h4>Competencias</h4>
      <ul class="clean-list">${competencias.map(item => {
        if (typeof item === 'string') return `<li>${escapeHtml(item)}</li>`;
        return `<li><strong>${escapeHtml(item?.competencia || '')}</strong> ${escapeHtml(item?.comentario || '')}</li>`;
      }).join('') || '<li>Analise parcial. Revise as competencias manualmente.</li>'}</ul>
    </div>
    <div>
      <h4>Pontos fortes</h4>
      <ul class="clean-list">${pontosFortes.map(item => `<li>${escapeHtml(item)}</li>`).join('') || '<li>Continue desenvolvendo repertorio e clareza.</li>'}</ul>
    </div>
    <div>
      <h4>Pontos de melhoria</h4>
      <ul class="clean-list">${pontosMelhoria.map(item => `<li>${escapeHtml(item)}</li>`).join('') || '<li>Revise tese, coesao e proposta de intervencao.</li>'}</ul>
    </div>
    <div class="chip-row">${proximosPassos.map(item => `<span class="soft-chip">${escapeHtml(item)}</span>`).join('')}</div>
  `;
}

// ====================================
// MODALS & SETTINGS
// ====================================
function openSettings() {
  document.getElementById('modal-settings').classList.add('active');
}

function closeModal(id) {
  if (id === 'modal-onboarding' && !state.onboardingCompleto) {
    setOnboardingAlert('O formulario e obrigatorio para liberar o EstudAI.', 'error');
    return;
  }
  document.getElementById(id).classList.remove('active');
}

// Click outside to close
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', (e) => {
    if (e.target === m) closeModal(m.id);
  });
});

// ====================================
// TOASTS
// ====================================
function friendlyMessage(message) {
  const text = String(message || '');
  if (!text) return 'Tente novamente em alguns minutos.';
  if (/csrf|token/i.test(text)) return 'Sua sessao expirou. Recarregue a pagina e tente novamente.';
  if (/Failed to fetch|NetworkError|conexao|connection/i.test(text)) return 'Falha de conexao. Verifique a internet e tente novamente.';
  if (/OpenRouter|503|IA indisponivel|rate/i.test(text)) return 'Nao conseguimos acionar a IA agora. Seus dados foram mantidos; tente novamente em alguns minutos.';
  if (/questoes suficientes|questões suficientes/i.test(text)) return 'Ainda nao ha questoes suficientes no banco para esta atividade.';
  return text;
}

function showToast(type, title, message) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  message = friendlyMessage(message);
  
  const iconMap = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" /></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
  };
  
  toast.innerHTML = `
    <div class="toast-icon">${iconMap[type]}</div>
    <div class="toast-content">
      <h4>${escapeHtml(title)}</h4>
      <p>${escapeHtml(message)}</p>
    </div>
  `;
  
  container.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideIn 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ====================================
// LOGOUT
// ====================================
async function logout() {
  await apiFetch(apiEndpoint('auth', 'action=logout'));
  window.location.href = 'index.html';
}
