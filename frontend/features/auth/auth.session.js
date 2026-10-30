import { me } from './auth.api.js';

/** @returns {Promise<import('./auth.types.js').User|null>} */
export async function getUser() {
  return (await me()).user ?? null;
}

/** Only same-origin relative paths: one leading '/', no '//' or '/\', no control chars. */
export function safeNext(value) {
  return typeof value === 'string' && /^\/(?![/\\])[^\x00-\x1f\\]*$/.test(value) ? value : null;
}

export function nextFromUrl() {
  return safeNext(new URLSearchParams(location.search).get('next'));
}

/** Returns the user, or redirects to login and returns null. Network errors propagate. */
export async function requireUser() {
  const user = await getUser();
  if (!user) {
    location.replace(`/login?next=${encodeURIComponent('/dashboard')}`);
    return null;
  }
  return user;
}

/** Logged-in visitors of /login or /register go to the dashboard. */
export async function redirectIfAuthed() {
  try {
    if (await getUser()) location.replace(nextFromUrl() || '/dashboard');
  } catch { /* offline: leave the form usable */ }
}
