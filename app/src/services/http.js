(function (window) {
  const config = window.EstudAIConfig || {};
  const endpoints = config.endpoints || {};

  function apiEndpoint(name, query) {
    const path = endpoints[name] || name;
    if (!query) return path;

    if (typeof query === 'string') {
      return `${path}?${query.replace(/^\?/, '')}`;
    }

    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, value);
      }
    });

    const serialized = params.toString();
    return serialized ? `${path}?${serialized}` : path;
  }

  function parseJsonFromText(text) {
    const clean = String(text || '').trim();
    if (!clean) return {};

    try {
      return JSON.parse(clean);
    } catch (error) {
      const start = clean.indexOf('{');
      const end = clean.lastIndexOf('}');
      if (start >= 0 && end > start) {
        try {
          return JSON.parse(clean.slice(start, end + 1));
        } catch (_) {
          // Fall through to the clearer error below.
        }
      }

      if (/<!doctype html|<html[\s>]/i.test(clean)) {
        throw new Error('A API retornou uma pagina HTML em vez de dados. Recarregue a pagina e tente novamente.');
      }

      if (/warning|fatal error|parse error|notice/i.test(clean)) {
        throw new Error('A API retornou um erro interno do PHP. Confira os logs do servidor.');
      }

      throw error;
    }
  }

  function responseMessage(response, data) {
    if (data && typeof data === 'object' && data.erro) return data.erro;
    if (response.status === 401) return 'Sua sessao expirou. Faca login novamente.';
    if (response.status === 419) return 'Sua sessao de seguranca expirou. Recarregue a pagina e tente novamente.';
    if (response.status >= 500) return 'Erro interno do servidor. Tente novamente em instantes.';
    if (!response.ok) return 'Nao foi possivel concluir a solicitacao.';
    return '';
  }

  function wrapApiResponse(response) {
    const originalJson = response.json.bind(response);
    response.json = async () => {
      const text = await response.text();
      let data;
      try {
        data = parseJsonFromText(text);
      } catch (error) {
        error.status = response.status;
        error.response = response;
        throw error;
      }

      if (!response.ok) {
        const error = new Error(responseMessage(response, data));
        error.status = response.status;
        error.response = response;
        error.data = data;
        throw error;
      }

      return data;
    };
    response.safeJson = response.json;
    response.rawJson = originalJson;
    return response;
  }

  function apiFetch(endpoint, options) {
    const fetchOptions = options || {};
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

    return fetch(endpoint, {
      credentials: 'same-origin',
      ...fetchOptions,
      headers
    }).then(wrapApiResponse);
  }

  window.parseApiJson = parseJsonFromText;
  window.apiEndpoint = apiEndpoint;
  window.apiFetch = apiFetch;
})(window);
