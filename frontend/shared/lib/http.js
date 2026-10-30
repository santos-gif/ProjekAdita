const BASE = '/api/v1';
let csrf = null;

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

/** Replace the cached CSRF token; null/undefined clears it so the next mutating call refetches /csrf. */
export function setCsrfToken(token) {
  csrf = token || null;
}

async function csrfToken() {
  if (!csrf) {
    const res = await fetch(`${BASE}/csrf`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    csrf = (await res.json()).data.token;
  }
  return csrf;
}

/** Returns envelope.data; throws ApiError for network failures, non-2xx or success:false. */
export async function request(method, path, body, retried = false) {
  const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
  let res;
  try {
    if (method !== 'GET') {
      opts.headers['X-CSRF-Token'] = await csrfToken();
      if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
      }
    }
    res = await fetch(BASE + path, opts);
  } catch {
    throw new ApiError(0, 'Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.');
  }
  if (res.status === 419 && !retried) {
    csrf = null; // stale session/token: fetch a fresh one once
    return request(method, path, body, true);
  }
  let json = null;
  try { json = await res.json(); } catch { /* non-JSON error page */ }
  if (!res.ok || !json || json.success === false) {
    throw new ApiError(res.status, json?.message || 'Terjadi kesalahan pada server. Coba lagi nanti.', json?.errors);
  }
  return json.data;
}

export const get = (path) => request('GET', path);
export const post = (path, body = {}) => request('POST', path, body);
