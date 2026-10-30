import { register } from '../../../features/auth/auth.api.js';
import { redirectIfAuthed } from '../../../features/auth/auth.session.js';
import { bindAuthForm, goAfterAuth } from '../../layouts/auth-layout.js';

redirectIfAuthed();
bindAuthForm(document.getElementById('register-form'), {
  submit: (d) => register(d.name, d.email, d.password, d.confirmPassword),
  onSuccess: goAfterAuth,
});
