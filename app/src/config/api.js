(function (window) {
  const API_BASE = '../../server/api';

  const config = {
    version: '0.1.0-alpha',
    appName: 'EstudAI',
    apiBase: API_BASE,
    csrfToken: '',
    pwa: {
      manifest: 'manifest.webmanifest',
      serviceWorker: 'sw.js'
    },
    endpoints: {
      anotacoes: `${API_BASE}/anotacoes.php`,
      auth: `${API_BASE}/auth.php`,
      calendarioEstudai: `${API_BASE}/calendario-estudai.php`,
      conquistas: `${API_BASE}/conquistas.php`,
      diagnostico: `${API_BASE}/diagnostico.php`,
      estatisticas: `${API_BASE}/estatisticas.php`,
      exerciciosIa: `${API_BASE}/exercicios-ia.php`,
      historico: `${API_BASE}/historico.php`,
      ia: `${API_BASE}/ia.php`,
      metas: `${API_BASE}/metas.php`,
      onboarding: `${API_BASE}/onboarding.php`,
      planoEstudos: `${API_BASE}/plano-estudos.php`,
      preferencias: `${API_BASE}/preferencias.php`,
      questoes: `${API_BASE}/questoes.php`,
      redacao: `${API_BASE}/redacao.php`,
      revisao: `${API_BASE}/revisao.php`,
      revisoesIa: `${API_BASE}/revisoes-ia.php`,
      revisaoSemanalIa: `${API_BASE}/revisao-semanal-ia.php`,
      simulados: `${API_BASE}/simulados.php`,
      simuladosPlanejados: `${API_BASE}/simulados-planejados.php`,
      tarefasEstudo: `${API_BASE}/tarefas-estudo.php`
    }
  };

  window.EstudAIConfig = config;
})(window);
