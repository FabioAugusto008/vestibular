(function (window) {
  async function postJson(endpointName, payload, query) {
    const endpoint = window.apiEndpoint(endpointName, query || undefined);
    const response = await window.apiFetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload || {})
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.erro || 'Nao foi possivel concluir a chamada de IA.');
      error.status = response.status;
      error.payload = data;
      throw error;
    }

    return data;
  }

  window.EstudAIApi = {
    diagnostico(perfil) {
      return postJson('diagnostico', { perfil }, { action: 'gerar' });
    },
    planoEstudos(entrada) {
      return postJson('planoEstudos', { entrada }, { action: 'gerar' });
    },
    mensagem(mensagem, contexto) {
      return postJson('ia', { action: 'mensagem', mensagem, contexto });
    }
  };
})(window);
