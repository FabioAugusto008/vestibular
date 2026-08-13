(function (window, navigator) {
  const config = window.EstudAIConfig || {};
  const swPath = config.pwa?.serviceWorker || 'sw.js';

  if (!('serviceWorker' in navigator)) return;
  if (!['http:', 'https:'].includes(window.location.protocol)) return;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register(swPath).then((registration) => {
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        if (!worker) return;
        worker.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) {
            window.dispatchEvent(new CustomEvent('estudai:pwa-update'));
          }
        });
      });
    }).catch((error) => {
      console.warn('Service worker nao registrado:', error);
    });
  });

  window.addEventListener('estudai:pwa-update', () => {
    if (window.showToast) {
      window.showToast('info', 'Nova versao disponivel', 'Recarregue a pagina para atualizar o EstudAI.');
    }
  });
})(window, navigator);
