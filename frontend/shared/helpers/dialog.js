import { ApiError } from '../lib/http.js';
import { el, setBusy, setMessage } from './dom.js';

function buildInput(field, id) {
  if (field.type === 'textarea') {
    const area = el('textarea', { id, name: field.name, rows: 4 });
    area.value = field.value || '';
    return area;
  }
  if (field.type === 'select') {
    const select = el('select', { id, name: field.name });
    for (const option of field.options) {
      select.append(el('option', { value: option.value, text: option.label }));
    }
    if (field.value) {
      select.value = field.value;
    }
    return select;
  }
  return el('input', { id, name: field.name, type: 'text', value: field.value || '' });
}

/**
 * Opens a small dialog with a form. When the person confirms, submit(values) runs; if it throws an ApiError the
 * message (and the field errors of a 422) show inside the dialog and it stays open.
 * fields: [{ name, label, type: 'text' | 'textarea' | 'select', options: [{value, label}], value }]
 * @returns {Promise<boolean>} true when submit succeeded, false when the dialog was cancelled
 */
export function openForm({ title, fields, confirmLabel = 'Kirim', submit }) {
  return new Promise((resolve) => {
    let done = false;
    const dialog = el('dialog', { class: 'ask-dialog' });
    const message = el('p', { class: 'notice', role: 'alert' });
    const form = el('form', { novalidate: true }, el('h2', { text: title }), message);

    const inputs = {};
    const errorLines = {};
    for (const field of fields) {
      const id = `ask-${field.name}`;
      inputs[field.name] = buildInput(field, id);
      errorLines[field.name] = el('p', { class: 'field-error', id: `${id}-error` });
      form.append(el('div', { class: 'field' },
        el('label', { for: id, text: field.label }),
        inputs[field.name],
        errorLines[field.name]
      ));
    }

    const confirmButton = el('button', { class: 'btn', type: 'submit', text: confirmLabel });
    const cancelButton = el('button', { class: 'btn btn--ghost', type: 'button', text: 'Batal' });
    form.append(el('div', { class: 'panel-actions' }, confirmButton, cancelButton));
    dialog.append(form);

    cancelButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      dialog.remove();
      resolve(done);
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (confirmButton.getAttribute('aria-busy') === 'true') return;

      setMessage(message, '');
      const values = {};
      for (const field of fields) {
        setMessage(errorLines[field.name], '');
        values[field.name] = inputs[field.name].value.trim();
      }

      setBusy(confirmButton, true);
      try {
        await submit(values);
        done = true;
        dialog.close();
      } catch (err) {
        if (!(err instanceof ApiError)) throw err;
        const fieldErrors = err.errors || {};
        for (const field of fields) {
          if (fieldErrors[field.name]) {
            setMessage(errorLines[field.name], fieldErrors[field.name]);
          }
        }
        setMessage(message, err.message);
      } finally {
        setBusy(confirmButton, false);
      }
    });

    document.body.append(dialog);
    dialog.showModal();
  });
}
