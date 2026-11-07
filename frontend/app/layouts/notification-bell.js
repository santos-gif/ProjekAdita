import { el } from '../../shared/helpers/dom.js';
import { chip, dateText, emptyNote } from '../../shared/helpers/ui.js';
import { getUnreadCount, listNotifications, markAllRead, markRead } from '../../features/notifications/notifications.api.js';

/** Notifications that report a decision get a small label, so the answer is visible without reading the sentence. */
const OUTCOME_LABEL = {
  project_published: ['Disetujui', 'green'],
  project_moderated: ['Lolos moderator', 'orange'],
  project_rejected: ['Ditolak', 'red'],
  project_taken_down: ['Diturunkan', 'red'],
  application_accepted: ['Diterima', 'green'],
  application_declined: ['Ditolak', 'red'],
  application_banned: ['Ditolak', 'red'],
};

/**
 * The bell in the header: a count of what is unread, and a panel with the person's notifications.
 * @returns {{element: HTMLElement}} put the element in the header
 */
export function buildBell() {
  const count = el('span', { class: 'notif-count', 'aria-hidden': 'true', hidden: true });
  const button = el('button', {
    class: 'icon-btn notif-btn',
    type: 'button',
    'aria-label': 'Notifikasi',
    'aria-haspopup': 'true',
    'aria-expanded': 'false',
    'aria-controls': 'notif-panel',
  }, el('span', { class: 'notif-icon', 'aria-hidden': 'true' }), count);

  const readAll = el('button', { class: 'link-btn', type: 'button', text: 'Tandai semua dibaca' });
  const list = el('div', { class: 'notif-list' });
  const panel = el('div', { id: 'notif-panel', class: 'notif-panel', role: 'region', 'aria-label': 'Daftar notifikasi', hidden: true },
    el('div', { class: 'notif-head' }, el('strong', { text: 'Notifikasi' }), readAll),
    list
  );
  const wrapper = el('div', { class: 'notif-wrapper' }, button, panel);

  function showCount(unread) {
    count.hidden = unread === 0;
    count.textContent = unread > 99 ? '99+' : String(unread);
    if (unread > 0) {
      button.setAttribute('aria-label', `Notifikasi (${unread} belum dibaca)`);
    } else {
      button.setAttribute('aria-label', 'Notifikasi');
    }
  }

  function itemFor(notification) {
    const head = el('div', { class: 'notif-item-head' });
    const outcome = OUTCOME_LABEL[notification.type];
    if (outcome) {
      head.append(chip(outcome[0], outcome[1]));
    }
    head.append(el('span', { class: 'notif-time', text: dateText(notification.created_at) }));

    const body = el('p', { class: 'notif-text', text: notification.message });

    // A notification about a project opens that project; one without a project is only marked as read.
    let item;
    let href = null;
    if (notification.project_id) {
      href = `/project?id=${notification.project_id}`;
      item = el('a', { class: 'notif-item', href }, head, body);
    } else {
      item = el('button', { class: 'notif-item', type: 'button' }, head, body);
    }
    item.dataset.unread = String(!notification.read);

    item.addEventListener('click', async (event) => {
      if (notification.read) {
        return;
      }
      // Mark it read first, then go: leaving the page would cancel the request.
      event.preventDefault();
      try {
        await markRead(notification.id);
      } catch {
        // The notification stays unread; opening the project is still allowed.
      }
      if (href) {
        location.assign(href);
      } else {
        await load();
      }
    });
    return item;
  }

  async function load() {
    list.replaceChildren(emptyNote('Memuat...'));
    let data;
    try {
      data = await listNotifications(false);
    } catch (err) {
      list.replaceChildren(el('p', { class: 'notif-error', text: err.message }));
      return;
    }

    list.replaceChildren();
    if (data.notifications.length === 0) {
      list.append(emptyNote('Belum ada notifikasi.'));
    }
    for (const notification of data.notifications) {
      list.append(itemFor(notification));
    }
    showCount(data.unread_count);
    readAll.disabled = data.unread_count === 0;
  }

  function setOpen(open) {
    panel.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) {
      load();
    }
  }

  button.addEventListener('click', () => setOpen(panel.hidden));

  readAll.addEventListener('click', async () => {
    try {
      await markAllRead();
    } catch {
      // The list below shows what is really unread.
    }
    await load();
  });

  // A click anywhere else closes the panel; so does Escape.
  document.addEventListener('pointerdown', (event) => {
    if (!panel.hidden && !wrapper.contains(event.target)) {
      setOpen(false);
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      setOpen(false);
      button.focus();
    }
  });

  // The count shows from the start; the list loads when the panel opens.
  getUnreadCount().then(showCount).catch(() => {
    // The bell simply stays without a count.
  });

  return { element: wrapper };
}
