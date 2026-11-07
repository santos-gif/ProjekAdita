import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { changePassword } from '../../../features/profile/profile.api.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { bindAuthForm } from '../../layouts/auth-layout.js';
import { mountShell } from '../../layouts/shell.js';

const notification = document.getElementById('notification');
function notify(text, kind = 'info') {
  notification.hidden = !text;
  if (text) {
    notification.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    setMessage(notification, text, kind);
  }
}

try {
  const user = await requireUser();
  if (user) {
    mountShell(document.getElementById('shell'), {
      user,
      active: 'profile',
      onLogout: async () => {
        try {
          await logout();
          location.replace('/login');
        } catch (err) {
          notify(err.message, 'error');
        }
      },
      onSearch: () => notify('Pencarian proyek belum tersedia.', 'info'),
    });
  }
} catch (err) {
  notify(err.message, 'error');
}

// bindAuthForm is the shared submit flow: busy button, field errors from a 422, message line.
const form = document.getElementById('password-form');
bindAuthForm(form, {
  submit: (d) => changePassword(d.currentPassword, d.newPassword, d.confirmNewPassword),
  onSuccess() {
    form.reset();
    setMessage(form.querySelector('#form-message'), 'Kata sandi berhasil diubah. Perangkat lain telah dikeluarkan.', 'info');
  },
});
