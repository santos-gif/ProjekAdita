import { ApiError } from '../../shared/lib/http.js';
import { setBusy, setMessage, showFieldErrors } from '../../shared/helpers/dom.js';
import { nextFromUrl } from '../../features/auth/auth.session.js';

export const goAfterAuth = () => location.assign(nextFromUrl() || '/dashboard');

/**
 * Shared submit flow for login/register/reset.
 * Form needs a #form-message live region and fields with id + "<id>-error".
 * onError(err) may return true to say it handled the error itself.
 */
export function bindAuthForm(form, { submit, onSuccess, onError }) {
  const button = form.querySelector('button[type="submit"]');
  const message = form.querySelector('#form-message');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (button.getAttribute('aria-busy') === 'true') return;
    setMessage(message, '');
    showFieldErrors(form);
    setBusy(button, true);
    const data = Object.fromEntries(new FormData(form));
    data.remember = form.elements.remember?.checked ?? false;
    try {
      await onSuccess(await submit(data));
    } catch (err) {
      if (onError?.(err)) return;
      if (!(err instanceof ApiError)) throw err;
      showFieldErrors(form, err.errors);
      setMessage(message, err.message);
    } finally {
      setBusy(button, false);
    }
  });
}
