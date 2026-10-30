import { get, post, setCsrfToken } from '../../shared/lib/http.js';

/** @typedef {import('./auth.types.js').User} User */

/** @returns {Promise<{user: User}>} */
export const login = (email, password, remember) => post('/auth/login', { email, password, remember });

/** @returns {Promise<{user: User}>} */
export const register = (name, email, password, confirmPassword) =>
  post('/auth/register', { name, email, password, confirmPassword });

/** Logout rotates the session; the response carries the new CSRF token. */
export async function logout() {
  const data = await post('/auth/logout');
  setCsrfToken(data?.token);
  return data;
}

/** @returns {Promise<{user: User|null}>} */
export const me = () => get('/auth/me');

/** Currently always rejects with status 501 (decision #4). */
export const reset = (email, newPassword, confirmPassword) =>
  post('/auth/reset', { email, newPassword, confirmPassword });
