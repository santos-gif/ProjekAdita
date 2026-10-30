document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"],button:not([type])');
      if (button && !button.dataset.noLoading) {
        button.dataset.originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Memproses...';
      }
    });
  });
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', event => {
      if (!confirm(el.dataset.confirm)) event.preventDefault();
    });
  });
});
