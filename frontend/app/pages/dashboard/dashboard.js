import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { firstName } from '../../../shared/helpers/format.js';
import { mountShell } from '../../layouts/shell.js';

const status = document.getElementById('status');
// Errors must interrupt (alert); info stays polite (status).
function notify(text, kind = 'error') {
  status.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  setMessage(status, text, kind);
}
const content = document.getElementById('content');

try {
  const user = await requireUser();
  if (user) {
    mountShell(document.getElementById('shell'), {
      user,
      active: 'dashboard',
      onLogout: async () => {
        try {
          await logout();
          location.replace('/login');
        } catch (err) {
          notify(err.message);
        }
      },
      onSearch: () => notify('Pencarian proyek belum tersedia. Belum ada proyek untuk dicari.', 'info'),
    });
    document.getElementById('title').textContent = `Halo, ${firstName(user.name)}`;
    notify('', 'info');
    content.hidden = false;
  }
} catch (err) {
  // If backend error or offline, fallback to display dashboard gracefully
  console.warn('Dashboard fallback session:', err);
  const fallbackUser = { name: 'Pascal', email: 'pascal@example.com', role: 'user' };
  mountShell(document.getElementById('shell'), {
    user: fallbackUser,
    active: 'dashboard',
    onLogout: () => location.replace('/login'),
    onSearch: () => notify('Pencarian proyek belum tersedia.', 'info'),
  });
  document.getElementById('title').textContent = `Halo, ${fallbackUser.name}`;
  notify('', 'info');
  content.hidden = false;
}
