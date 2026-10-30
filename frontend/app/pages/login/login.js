import { login } from '../../../features/auth/auth.api.js';
import { redirectIfAuthed } from '../../../features/auth/auth.session.js';
import { bindAuthForm, goAfterAuth } from '../../layouts/auth-layout.js';

redirectIfAuthed();
bindAuthForm(document.getElementById('login-form'), {
  submit: (d) => login(d.email, d.password, d.remember),
  onSuccess: goAfterAuth,
});
