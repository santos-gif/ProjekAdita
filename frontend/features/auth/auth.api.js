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

/** Step 1 of a reset: always succeeds; a 6-digit code is emailed when the address is registered. */
export const requestReset = (email) => post('/auth/reset', { email });

/** Step 2: the code from the email plus the new password. Ends every session of that account. */
export const confirmReset = (email, code, newPassword, confirmNewPassword) =>
  post('/auth/reset/confirm', { email, code, newPassword, confirmNewPassword });
