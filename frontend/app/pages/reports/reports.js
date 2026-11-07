import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { chip, dateText, emptyNote, makeActions } from '../../../shared/helpers/ui.js';
import { adminReports, moderationReports, resolveAsAdmin, resolveAsModerator } from '../../../features/review/review.api.js';

const { user, notify } = await startPage({ active: 'reports' });
const isAdmin = user.role === 'admin';
const list = document.getElementById('list');
const statusSelect = document.getElementById('status');
const { formButton } = makeActions(notify, load);

// Moderators start with the open reports, admins with the ones forwarded to them.
if (isAdmin) {
  statusSelect.value = 'forwarded';
}
statusSelect.addEventListener('change', load);

const STATUS = {
  open: ['Terbuka', 'orange'],
  forwarded: ['Diteruskan ke admin', 'orange'],
  dismissed: ['Diabaikan', ''],
  actioned: ['Ditindak', 'green'],
};

function noteField(label) {
  return [{ name: 'note', label, type: 'textarea' }];
}

function buttonsFor(report) {
  const box = el('div', { class: 'item-actions' });

  if (report.status === 'open') {
    box.append(
      formButton('Abaikan', 'btn btn--ghost btn--small', {
        title: 'Abaikan laporan',
        confirmLabel: 'Abaikan',
        fields: noteField('Catatan (opsional)'),
        submit: (v) => resolveAsModerator(report.id, 'dismiss', v.note || undefined),
      }, 'Laporan diabaikan.'),
      formButton('Teruskan ke admin', 'btn btn--small', {
        title: 'Teruskan ke admin',
        confirmLabel: 'Teruskan',
        fields: noteField('Catatan untuk admin (opsional)'),
        submit: (v) => resolveAsModerator(report.id, 'forward', v.note || undefined),
      }, 'Laporan diteruskan ke admin.')
    );
  }

  if (report.status === 'forwarded' && isAdmin) {
    box.append(
      formButton('Abaikan laporan', 'btn btn--ghost btn--small', {
        title: 'Abaikan laporan',
        confirmLabel: 'Abaikan',
        fields: noteField('Catatan (opsional)'),
        submit: (v) => resolveAsAdmin(report.id, v.note || undefined),
      }, 'Laporan diabaikan.'),
      el('a', { class: 'btn btn--small', href: `/project?id=${report.project.id}`, text: 'Buka proyek untuk menurunkan' })
    );
  }
  return box;
}

function row(report) {
  const known = STATUS[report.status] || [report.status, ''];
  const meta = el('div', { class: 'item-meta' },
    chip(known[0], known[1]),
    `dilaporkan oleh ${report.reporter.name}`,
    dateText(report.created_at)
  );
  if (report.handled_by) {
    meta.append(`ditangani oleh ${report.handled_by.name}`);
  }

  const item = el('article', { class: 'item' },
    el('h2', { class: 'item-title' }, el('a', { href: `/project?id=${report.project.id}`, text: report.project.title })),
    meta,
    el('p', { class: 'item-text', text: report.reason })
  );
  if (report.note) {
    item.append(el('p', { class: 'item-text', text: `Catatan: ${report.note}` }));
  }
  item.append(buttonsFor(report));
  return item;
}

async function load() {
  notify('');
  let reports;
  try {
    if (isAdmin && statusSelect.value === 'forwarded') {
      reports = await adminReports('forwarded');
    } else {
      reports = await moderationReports(statusSelect.value);
    }
  } catch (err) {
    notify(err.message, 'error');
    return;
  }
  list.replaceChildren();
  if (reports.length === 0) {
    list.append(emptyNote('Tidak ada laporan dengan status ini.'));
  }
  for (const report of reports) {
    list.append(row(report));
  }
}

await load();
