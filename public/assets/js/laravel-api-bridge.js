/*
 * Opt-in Sprint 8 bridge for the existing legacy UI.
 * Load after api.js. It changes only API calls; page and legacy routes stay intact.
 * Configure <meta name="laravel-api-url" content="https://api.example.test">.
 */
(function (global) {
  'use strict';

  if (!global.Api) throw new Error('laravel-api-bridge.js requires api.js first');

  const legacy = global.Api;
  const meta = (name) => (document.querySelector('meta[name="' + name + '"]') || {}).content || '';
  const base = (meta('laravel-api-url') || location.origin).replace(/\/$/, '');
  const prefix = '/api/v1';
  const tokenKey = 'gestao_brindes_laravel_token';
  let token = sessionStorage.getItem(tokenKey) || '';

  function endpoint(path) {
    const value = path || '/';
    if (value === '/api' || value.indexOf('/api/') === 0) return prefix + value.slice(4);
    if (value.indexOf('/v1/') === 0) return '/api' + value;
    return value.indexOf('/') === 0 ? prefix + value : prefix + '/' + value;
  }

  function url(path, query) {
    const target = new URL(base + endpoint(path), location.href);
    Object.entries(query || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') target.searchParams.set(key, value === true ? '1' : value === false ? '0' : value);
    });
    return target.toString();
  }

  async function request(method, path, body, options) {
    const opts = options || {};
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = 'Bearer ' + token;
    if (method !== 'GET') headers['X-CSRF-Token'] = legacy.csrf();
    if (opts.idempotencyKey) headers['Idempotency-Key'] = opts.idempotencyKey;
    let payload = body;
    if (!(body instanceof FormData) && body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }
    const response = await fetch(url(path, opts.query), {
      method, headers, body: payload,
      credentials: 'include', cache: 'no-store', signal: opts.signal,
    });
    let json = null;
    try { json = await response.json(); } catch (_) { /* empty response */ }
    if (!response.ok) {
      const error = json && (json.error || {
        code: response.status === 422 ? 'VALIDATION_ERROR' : response.status === 401 ? 'UNAUTHENTICATED' : 'API_ERROR',
        message: json.message || 'Erro inesperado. Tente novamente.',
        fields: json.errors || {},
      });
      throw new legacy.ApiError(response.status, error);
    }
    if (json && json.token) {
      token = json.token;
      sessionStorage.setItem(tokenKey, token);
    }
    return json && json.ok === true ? json : { ok: true, data: json && json.data !== undefined ? json.data : json, meta: json && json.meta };
  }

  global.Api = {
    get: (path, query, opts) => request('GET', path, undefined, Object.assign({}, opts, { query })),
    post: (path, body, opts) => request('POST', path, body === undefined ? {} : body, opts),
    postIdem: (path, body, opts) => {
      const options = Object.assign({}, opts, { idempotencyKey: (opts && opts.idempotencyKey) || legacy.newKey() });
      return request('POST', path, body === undefined ? {} : body, options);
    },
    put: (path, body, opts) => request('PUT', path, body === undefined ? {} : body, opts),
    del: (path, body, opts) => request('DELETE', path, body, opts),
    upload: (path, formData, opts) => request('POST', path, formData, opts),
    url,
    newKey: legacy.newKey,
    refreshCsrf: legacy.refreshCsrf,
    csrf: legacy.csrf,
    token: () => token,
    ApiError: legacy.ApiError,
    fmt: legacy.fmt,
    labels: legacy.labels,
  };
})(window);
