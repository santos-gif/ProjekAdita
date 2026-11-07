import { requestReset, confirmReset } from '../../../features/auth/auth.api.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { bindAuthForm } from '../../layouts/auth-layout.js';

// One form, two steps: first the email, then (same form) the code and the new password.
const form = document.getElementById('reset-form');
const codeStep = document.getElementById('code-step');
const submitButton = form.querySelector('button[type="submit"]');
let step = 'request';

bindAuthForm(form, {
  submit(d) {
    if (step === 'request') {
      return requestReset(d.email);
    }
    return confirmReset(d.email, d.code, d.newPassword, d.confirmNewPassword);
  },
  onSuccess() {
    if (step === 'confirm') {
      location.assign('/login');
      return;
    }
    step = 'confirm';
    form.elements.email.readOnly = true;
    codeStep.hidden = false;
    form.elements.code.focus();
    submitButton.dataset.label = 'Ubah kata sandi';
    setMessage(
      form.querySelector('#form-message'),
      'Jika email terdaftar, kode 6 digit sudah dikirim. Masukkan kode itu dan kata sandi baru. Kode berlaku 10 menit.',
      'info'
    );
  },
});
