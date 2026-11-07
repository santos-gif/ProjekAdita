import { logout } from '../../features/auth/auth.api.js';
import { requireUser } from '../../features/auth/auth.session.js';
import { setMessage } from '../../shared/helpers/dom.js';
import { mountShell } from './shell.js';

/**
 * The start of every logged-in page: login check, header and sidebar, and notify() for the #notification line.
 * When nobody is logged in the browser is already on its way to /login, so this never resolves.
 * @returns {Promise<{user: object, notify: (text: string, kind?: string) => void}>}
 */
export async function startPage({ active, onSearch }) {
  const notification = document.getElementById('notification');

  function notify(text, kind = 'info') {
    notification.hidden = !text;
    if (text) {
      notification.setAttribute('role', kind === 'error' ? 'alert' : 'status');
      setMessage(notification, text, kind);
      notification.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  let user = null;
  try {
    user = await requireUser();
  } catch (err) {
    notify(err.message, 'error');
  }
  if (!user) {
    await new Promise(() => {});
  }

  mountShell(document.getElementById('shell'), {
    user,
    active,
    onSearch,
    onLogout: async () => {
      try {
        await logout();
        location.replace('/login');
      } catch (err) {
        notify(err.message, 'error');
      }
    },
  });
  return { user, notify };
}
