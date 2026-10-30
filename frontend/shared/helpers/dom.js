export const $ = (sel, root = document) => root.querySelector(sel);

/** Create an element; text goes through textContent, never innerHTML. */
export function el(tag, props = {}, ...kids) {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (k === 'text') n.textContent = v;
    else if (k === 'class') n.className = v;
    else if (v === true) n.setAttribute(k, '');
    else if (v !== false && v != null) n.setAttribute(k, v);
  }
  n.append(...kids);
  return n;
}

export function setMessage(node, text, kind = 'error') {
  node.textContent = text || '';
  if (text) node.dataset.kind = kind;
  else delete node.dataset.kind;
}

/** Fields use id="<id>" and an error element id="<id>-error". */
export function showFieldErrors(form, errors = {}) {
  let first = null;
  for (const input of form.querySelectorAll('input')) {
    const out = form.querySelector(`#${input.id}-error`);
    const msg = errors[input.name] || '';
    if (out) setMessage(out, msg);
    if (msg) {
      input.setAttribute('aria-invalid', 'true');
      first ||= input;
    } else input.removeAttribute('aria-invalid');
  }
  first?.focus();
}

export function setBusy(btn, busy, busyLabel = 'Memproses...') {
  if (busy) btn.dataset.label = btn.textContent;
  btn.textContent = busy ? busyLabel : btn.dataset.label || btn.textContent;
  btn.setAttribute('aria-disabled', String(busy));
  btn.setAttribute('aria-busy', String(busy));
}
