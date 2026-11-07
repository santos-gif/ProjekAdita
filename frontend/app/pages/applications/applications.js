import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { chip, dateText, emptyNote, makeActions } from '../../../shared/helpers/ui.js';
import { myApplications, withdrawApplication } from '../../../features/projects/applications.api.js';

const { notify } = await startPage({ active: 'applications' });
const list = document.getElementById('list');
const { actionButton } = makeActions(notify, load);

const OUTCOME = {
  pending: ['Menunggu keputusan', 'orange'],
  accepted: ['Diterima', 'green'],
  declined: ['Ditolak', 'red'],
  banned: ['Diblokir dari proyek', 'red'],
  withdrawn: ['Ditarik', ''],
};

function outcomeChip(outcome) {
  const known = OUTCOME[outcome];
  if (!known) {
    return chip(outcome);
  }
  return chip(known[0], known[1]);
}

function row(application) {
  const buttons = el('div', { class: 'item-actions' });
  if (application.outcome === 'pending') {
    buttons.append(actionButton('Tarik lamaran', 'btn btn--ghost btn--small',
      () => withdrawApplication(application.id), 'Lamaran ditarik.', 'Tarik lamaran ini?'));
  }
  return el('article', { class: 'item' },
    el('h2', { class: 'item-title' }, el('a', { href: `/project?id=${application.project.id}`, text: application.project.title })),
    el('div', { class: 'item-meta' },
      outcomeChip(application.outcome),
      `sebagai ${application.desired_title}`,
      `dikirim ${dateText(application.created_at)}`
    ),
    el('p', { class: 'item-text', text: application.message }),
    buttons
  );
}

async function load() {
  notify('');
  let applications;
  try {
    applications = await myApplications();
  } catch (err) {
    notify(err.message, 'error');
    return;
  }
  list.replaceChildren();
  if (applications.length === 0) {
    list.append(emptyNote('Anda belum melamar ke proyek mana pun. Mulai dari halaman Jelajahi.'));
  }
  for (const application of applications) {
    list.append(row(application));
  }
}

await load();
