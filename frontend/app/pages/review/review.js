import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { chip, dateText, emptyNote, makeActions, projectItem } from '../../../shared/helpers/ui.js';
import {
  adminDetail,
  adminQueue,
  moderationDetail,
  moderationHistory,
  moderationQueue,
  reviewAsAdmin,
  reviewAsModerator,
} from '../../../features/review/review.api.js';

const { user, notify } = await startPage({ active: 'review' });
const tabs = document.getElementById('tabs');
const list = document.getElementById('list');
const { actionButton, formButton } = makeActions(notify, load);

// moderation = first-stage queue (moderator and admin), admin = final queue (admin only), history = my decisions
let view = 'moderation';
const VIEWS = [['moderation', 'Antrean tahap 1'], ['history', 'Riwayat keputusan saya']];
if (user.role === 'admin') {
  VIEWS.splice(1, 0, ['admin', 'Antrean akhir (admin)']);
}

function drawTabs() {
  tabs.replaceChildren();
  for (const [key, label] of VIEWS) {
    let className = 'btn btn--ghost btn--small';
    if (key === view) {
      className = 'btn btn--small';
    }
    const button = el('button', { class: className, type: 'button', text: label });
    button.addEventListener('click', () => {
      view = key;
      load();
    });
    tabs.append(button);
  }
}

function showDetail(box, data) {
  const project = data.project;
  box.append(
    el('p', { class: 'item-text', text: `Deskripsi: ${project.description}` }),
    el('p', { class: 'item-text', text: `Persyaratan: ${project.requirements || '-'}` }),
    el('p', { class: 'item-text', text: `Metode seleksi: ${project.selection_method || '-'}` })
  );
  for (const entry of data.reviews) {
    let verdict = 'menolak';
    if (entry.approved) {
      verdict = 'menyetujui';
    }
    box.append(el('p', { class: 'item-meta', text: `${entry.reviewer.name} (${entry.stage}) ${verdict}: ${entry.reason || '-'}` }));
  }
}

function detailButton(project, stage, box) {
  const button = el('button', { class: 'btn btn--ghost btn--small', type: 'button', text: 'Lihat detail' });
  button.addEventListener('click', async () => {
    if (box.childElementCount > 0) {
      box.replaceChildren();
      return;
    }
    try {
      let data;
      if (stage === 'admin') {
        data = await adminDetail(project.id);
      } else {
        data = await moderationDetail(project.id);
      }
      showDetail(box, data);
    } catch (err) {
      notify(err.message, 'error');
    }
  });
  return button;
}

function queueItem(project, stage) {
  const review = stage === 'admin' ? reviewAsAdmin : reviewAsModerator;
  let approveLabel = 'Teruskan ke admin';
  if (stage === 'admin') {
    approveLabel = 'Terbitkan';
  }

  const detail = el('div', { class: 'detail-block' });
  const buttons = el('div', { class: 'item-actions' },
    detailButton(project, stage, detail),
    actionButton(approveLabel, 'btn btn--small', () => review(project.id, true, undefined), 'Keputusan disimpan.'),
    formButton('Tolak', 'btn btn--ghost btn--small btn--danger', {
      title: `Tolak "${project.title}"`,
      confirmLabel: 'Tolak proyek',
      fields: [{ name: 'reason', label: 'Alasan (wajib, dikirim ke pemilik)', type: 'textarea' }],
      submit: (v) => review(project.id, false, v.reason),
    }, 'Proyek ditolak.')
  );
  const item = projectItem(project, buttons, detail);
  // The project page hides unpublished projects from people who are not on them, so the title stays plain text here.
  const titleLink = item.querySelector('.item-title a');
  titleLink.replaceWith(titleLink.textContent);
  return item;
}

function historyItem(entry) {
  let verdict = chip('Ditolak', 'red');
  if (entry.approved) {
    verdict = chip('Disetujui', 'green');
  }
  return el('article', { class: 'item' },
    el('h2', { class: 'item-title' }, el('a', { href: `/project?id=${entry.project_id}`, text: entry.project_title })),
    el('div', { class: 'item-meta' }, verdict, `tahap ${entry.stage}`, dateText(entry.created_at)),
    el('p', { class: 'item-text', text: entry.reason || '' })
  );
}

async function load() {
  notify('');
  drawTabs();
  list.replaceChildren();
  try {
    if (view === 'history') {
      const entries = await moderationHistory();
      if (entries.length === 0) {
        list.append(emptyNote('Anda belum membuat keputusan.'));
      }
      for (const entry of entries) {
        list.append(historyItem(entry));
      }
      return;
    }

    let projects;
    if (view === 'admin') {
      projects = await adminQueue('pending_admin');
    } else {
      projects = await moderationQueue();
    }
    if (projects.length === 0) {
      list.append(emptyNote('Antrean kosong.'));
    }
    for (const project of projects) {
      list.append(queueItem(project, view));
    }
  } catch (err) {
    notify(err.message, 'error');
  }
}

await load();
