import { reset } from '../../../features/auth/auth.api.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { bindAuthForm } from '../../layouts/auth-layout.js';

const form = document.getElementById('reset-form');
bindAuthForm(form, {
  submit: (d) => reset(d.email, d.newPassword, d.confirmPassword),
  onSuccess: () => {},
  // 501 is expected for now: show it as a notice, not an error.
  onError(err) {
    if (err.status !== 501) return false;
    setMessage(form.querySelector('#form-message'), err.message, 'info');
    return true;
  },
});
