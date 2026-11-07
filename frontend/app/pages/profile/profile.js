import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { getMyProfile, saveMyProfile } from '../../../features/profile/profile.api.js';
import { ApiError } from '../../../shared/lib/http.js';
import { el, setBusy, setMessage, showFieldErrors } from '../../../shared/helpers/dom.js';
import { initials } from '../../../shared/helpers/format.js';
import { mountShell } from '../../layouts/shell.js';

const notification = document.getElementById('notification');
function notify(text, kind = 'info') {
  notification.hidden = !text;
  if (text) {
    notification.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    setMessage(notification, text, kind);
  }
}

const form = document.getElementById('profile-form');
const message = document.getElementById('form-message');
const fields = form.elements;
const saveButton = form.querySelector('button[type="submit"]');
const avatar = document.getElementById('profile-avatar');

function fill(profile) {
  fields.name.value = profile.name || '';
  fields.study_program.value = profile.study_program || '';
  fields.cohort_year.value = profile.cohort_year ?? '';
  fields.identity_number.value = profile.identity_number || '';
  fields.phone.value = profile.phone || '';
  fields.bio.value = profile.bio || '';
  document.getElementById('profile-email').textContent = profile.email;

  if (profile.avatar) {
    avatar.replaceChildren(el('img', { src: profile.avatar, alt: '' }));
  } else {
    avatar.textContent = initials(profile.name);
  }
}

// What the backend gets. "" clears an optional field; an empty angkatan is null.
function formValues() {
  let cohortYear = null;
  if (fields.cohort_year.value !== '') {
    cohortYear = Number(fields.cohort_year.value);
  }
  return {
    name: fields.name.value,
    study_program: fields.study_program.value,
    cohort_year: cohortYear,
    identity_number: fields.identity_number.value,
    phone: fields.phone.value,
    bio: fields.bio.value,
  };
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
    fill(await getMyProfile());
  }
} catch (err) {
  notify(err.message, 'error');
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (saveButton.getAttribute('aria-busy') === 'true') return;

  setMessage(message, '');
  showFieldErrors(form);
  setBusy(saveButton, true);
  try {
    fill(await saveMyProfile(formValues()));
    setMessage(message, 'Profil disimpan.', 'info');
  } catch (err) {
    if (!(err instanceof ApiError)) throw err;
    showFieldErrors(form, err.errors);
    setMessage(message, err.message);
  } finally {
    setBusy(saveButton, false);
  }
});
