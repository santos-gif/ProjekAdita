import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { openForm } from '../../../shared/helpers/dialog.js';
import { chip, dateText, emptyNote, makeActions, projectStatusChip, roleChip } from '../../../shared/helpers/ui.js';
import { getProjectDetail, reportProject } from '../../../features/projects/discover.api.js';
import {
  acceptApplication,
  applyToProject,
  myApplications,
  projectApplications,
  rejectApplication,
  withdrawApplication,
} from '../../../features/projects/applications.api.js';
import {
  changeMember,
  decidePromotion,
  leaveProject,
  listPromotions,
  removeMember,
  requestPromotion,
  setApplicationsOpen,
  stepDown,
  transferOwnership,
} from '../../../features/projects/team.api.js';
import { takedownProject } from '../../../features/review/review.api.js';

const { user, notify } = await startPage({ active: 'discover' });
const projectId = Number(new URLSearchParams(location.search).get('id'));
const content = document.getElementById('content');
const { actionButton, formButton } = makeActions(notify, render);

function block(title, ...nodes) {
  return el('section', { class: 'detail-block' }, el('h2', { text: title }), ...nodes);
}

function paragraph(value) {
  return el('p', { class: 'item-text', text: value || '-' });
}

function personLink(id, name) {
  return el('a', { href: `/user?id=${id}`, text: name });
}

// ---------- top of the page ----------

function headerBlock(project) {
  const meta = el('div', { class: 'item-meta' }, projectStatusChip(project.status));
  if (project.status === 'published') {
    if (project.applications_open) {
      meta.append(chip('Menerima lamaran', 'green'));
    } else {
      meta.append(chip('Lamaran ditutup'));
    }
  }
  if (project.my_project_role) {
    meta.append(roleChip(project.my_project_role));
  }

  const owner = el('p', { class: 'item-meta' },
    'Pemilik: ',
    personLink(project.owner.id, project.owner.name),
    `, ${project.members_count} anggota`
  );
  return el('div', { class: 'detail-block' }, el('h1', { text: project.title }), meta, owner);
}

// ---------- what a visitor can do ----------

async function withdrawMine() {
  const mine = await myApplications();
  for (const application of mine) {
    if (application.project.id === projectId && application.outcome === 'pending') {
      await withdrawApplication(application.id);
      return;
    }
  }
  throw new Error('Lamaran tidak ditemukan.');
}

function visitorActions(project) {
  const box = el('div', { class: 'item-actions' });
  if (project.status !== 'published') {
    return box;
  }

  if (project.can_apply) {
    box.append(formButton('Lamar', 'btn', {
      title: 'Lamar ke proyek ini',
      confirmLabel: 'Kirim lamaran',
      fields: [
        { name: 'desired_title', label: 'Posisi yang diinginkan', type: 'text' },
        { name: 'message', label: 'Pesan untuk tim', type: 'textarea' },
      ],
      submit: (v) => applyToProject(projectId, v.desired_title, v.message),
    }, 'Lamaran terkirim.'));
  }

  if (project.my_application === 'pending') {
    box.append(actionButton('Tarik lamaran', 'btn btn--ghost', withdrawMine, 'Lamaran ditarik.', 'Tarik lamaran ini?'));
  } else if (project.my_application === 'banned') {
    box.append(emptyNote('Anda tidak dapat melamar ke proyek ini.'));
  } else if (!project.can_apply && !project.my_project_role) {
    box.append(emptyNote('Proyek ini tidak menerima lamaran saat ini.'));
  }

  if (project.my_project_role !== 'owner') {
    box.append(formButton('Laporkan proyek', 'btn btn--ghost btn--small', {
      title: 'Laporkan proyek ini',
      confirmLabel: 'Kirim laporan',
      fields: [{ name: 'reason', label: 'Alasan (5-1000 karakter)', type: 'textarea' }],
      submit: (v) => reportProject(projectId, v.reason),
    }, 'Laporan terkirim. Terima kasih.'));
  }
  return box;
}

// ---------- the team ----------

function ownerMemberButtons(member) {
  return [
    formButton('Ubah peran', 'btn btn--ghost btn--small', {
      title: `Ubah peran ${member.name}`,
      confirmLabel: 'Simpan',
      fields: [
        {
          name: 'role',
          label: 'Peran',
          type: 'select',
          value: member.project_role,
          options: [{ value: 'member', label: 'Anggota' }, { value: 'manager', label: 'Manajer' }],
        },
        { name: 'title', label: 'Nama posisi', type: 'text', value: member.title },
      ],
      submit: (v) => changeMember(projectId, member.user_id, v.role, v.title),
    }, 'Anggota diperbarui.'),
    actionButton('Jadikan pemilik', 'btn btn--ghost btn--small',
      () => transferOwnership(projectId, member.user_id), 'Kepemilikan dialihkan.',
      `Alihkan kepemilikan ke ${member.name}? Anda akan menjadi manajer.`),
    actionButton('Keluarkan', 'btn btn--ghost btn--small btn--danger',
      () => removeMember(projectId, member.user_id), 'Anggota dikeluarkan.',
      `Keluarkan ${member.name} dari proyek?`),
  ];
}

function managerMemberButtons(member) {
  return [
    actionButton('Ajukan jadi manajer', 'btn btn--ghost btn--small',
      () => requestPromotion(projectId, member.user_id), 'Permintaan dikirim ke pemilik.'),
    actionButton('Keluarkan', 'btn btn--ghost btn--small btn--danger',
      () => removeMember(projectId, member.user_id), 'Anggota dikeluarkan.',
      `Keluarkan ${member.name} dari proyek?`),
  ];
}

function ownButtons(member) {
  const buttons = [];
  if (member.project_role === 'manager') {
    buttons.push(actionButton('Turun jabatan', 'btn btn--ghost btn--small',
      () => stepDown(projectId), 'Anda sekarang anggota biasa.', 'Turun menjadi anggota biasa?'));
  }
  if (member.project_role !== 'owner') {
    buttons.push(actionButton('Keluar dari proyek', 'btn btn--ghost btn--small btn--danger',
      () => leaveProject(projectId), 'Anda telah keluar dari proyek.', 'Keluar dari proyek ini?'));
  }
  return buttons;
}

function memberRow(member, myProjectRole) {
  const meta = el('div', { class: 'item-meta' }, roleChip(member.project_role));
  if (member.title) {
    meta.append(member.title);
  }
  meta.append(`bergabung ${dateText(member.joined_at)}`);

  let buttons = [];
  if (member.user_id === user.id) {
    buttons = ownButtons(member);
  } else if (myProjectRole === 'owner') {
    buttons = ownerMemberButtons(member);
  } else if (myProjectRole === 'manager' && member.project_role === 'member') {
    buttons = managerMemberButtons(member);
  }

  return el('article', { class: 'item' },
    el('h3', { class: 'item-title' }, personLink(member.user_id, member.name)),
    meta,
    el('div', { class: 'item-actions' }, ...buttons)
  );
}

function teamBlock(members, myProjectRole) {
  const list = el('div', { class: 'item-list' });
  for (const member of members) {
    list.append(memberRow(member, myProjectRole));
  }
  return block('Tim', list);
}

// ---------- owner and manager: applications ----------

function applicationRow(application) {
  const who = application.applicant;
  const meta = el('div', { class: 'item-meta' });
  if (who.study_program) {
    meta.append(who.study_program);
  }
  if (who.cohort_year) {
    meta.append(`angkatan ${who.cohort_year}`);
  }

  const row = el('article', { class: 'item' },
    el('h3', { class: 'item-title' }, personLink(who.user_id, who.name), ` melamar sebagai ${application.desired_title}`),
    meta,
    paragraph(application.message)
  );
  if (who.bio) {
    row.append(el('p', { class: 'item-text', text: who.bio }));
  }
  row.append(el('div', { class: 'item-actions' },
    actionButton('Terima', 'btn btn--small', () => acceptApplication(application.id), 'Lamaran diterima.'),
    actionButton('Tolak', 'btn btn--ghost btn--small', () => rejectApplication(application.id, false), 'Lamaran ditolak.'),
    actionButton('Tolak dan blokir', 'btn btn--ghost btn--small btn--danger',
      () => rejectApplication(application.id, true), 'Pelamar diblokir dari proyek ini.',
      `Blokir ${who.name} dari proyek ini? Orang itu tidak bisa melamar lagi.`)
  ));
  return row;
}

async function applicationsBlock() {
  const list = el('div', { class: 'item-list' });
  let applications = [];
  try {
    applications = await projectApplications(projectId, 'pending');
  } catch (err) {
    notify(err.message, 'error');
  }
  if (applications.length === 0) {
    list.append(emptyNote('Belum ada lamaran yang menunggu.'));
  }
  for (const application of applications) {
    list.append(applicationRow(application));
  }
  return block('Lamaran masuk', list);
}

// ---------- owner: settings and promotion requests ----------

function ownerSettings(project) {
  let label = 'Tutup lamaran baru';
  if (!project.applications_open) {
    label = 'Buka lamaran baru';
  }
  return block('Pengaturan pemilik', el('div', { class: 'item-actions' },
    actionButton(label, 'btn btn--ghost btn--small',
      () => setApplicationsOpen(projectId, !project.applications_open), 'Pengaturan lamaran disimpan.')
  ));
}

async function promotionsBlock() {
  const list = el('div', { class: 'item-list' });
  let requests = [];
  try {
    requests = await listPromotions(projectId);
  } catch (err) {
    notify(err.message, 'error');
  }
  if (requests.length === 0) {
    list.append(emptyNote('Tidak ada permintaan promosi.'));
  }
  for (const request of requests) {
    list.append(el('article', { class: 'item' },
      el('p', { class: 'item-text', text: `${request.requested_by.name} meminta ${request.member.name} dijadikan manajer.` }),
      el('div', { class: 'item-actions' },
        actionButton('Setujui', 'btn btn--small', () => decidePromotion(request.id, true), 'Anggota dijadikan manajer.'),
        actionButton('Tolak', 'btn btn--ghost btn--small', () => decidePromotion(request.id, false), 'Permintaan ditolak.')
      )
    ));
  }
  return block('Permintaan promosi', list);
}

// ---------- moderators and admins ----------

function staffBlock(project) {
  const box = el('div', { class: 'item-actions' }, el('a', { class: 'btn btn--ghost btn--small', href: '/review', text: 'Buka antrean tinjau' }));
  if (user.role === 'admin' && project.status === 'published') {
    const takedown = el('button', { class: 'btn btn--small btn--danger', type: 'button', text: 'Turunkan proyek' });
    takedown.addEventListener('click', async () => {
      const done = await openForm({
        title: 'Turunkan proyek ini',
        confirmLabel: 'Turunkan',
        fields: [{ name: 'reason', label: 'Alasan (dikirim ke pemilik dan anggota)', type: 'textarea' }],
        submit: (v) => takedownProject(projectId, v.reason),
      });
      // A taken-down project is hidden from everyone who is not on it, so this page would go blank: go to the reports.
      if (done) {
        location.assign('/reports');
      }
    });
    box.append(takedown);
  }
  return block('Moderasi', box);
}

// ---------- the whole page ----------

async function render() {
  notify('');
  let data;
  try {
    data = await getProjectDetail(projectId);
  } catch (err) {
    content.replaceChildren();
    notify(err.message, 'error');
    return;
  }

  const project = data.project;
  const myProjectRole = project.my_project_role;
  document.title = `${project.title} - ProjekAdita`;

  const page = el('div', { class: 'detail' }, headerBlock(project), visitorActions(project));
  if (project.thumbnail) {
    page.append(el('img', { class: 'thumb', src: project.thumbnail, alt: `Sampul ${project.title}` }));
  }
  if (project.status === 'rejected' && project.rejection_reason) {
    page.append(el('p', { class: 'notice', text: `Ditolak: ${project.rejection_reason}` }));
  }

  const tags = el('div', { class: 'item-meta' });
  for (const tag of project.tags) {
    tags.append(chip(`#${tag}`));
  }
  page.append(
    block('Deskripsi', paragraph(project.description)),
    block('Persyaratan anggota', paragraph(project.requirements)),
    block('Metode seleksi', paragraph(project.selection_method)),
    block('Tag', tags),
    teamBlock(data.members, myProjectRole)
  );

  if (project.status === 'published' && (myProjectRole === 'owner' || myProjectRole === 'manager')) {
    page.append(await applicationsBlock());
  }
  if (project.status === 'published' && myProjectRole === 'owner') {
    page.append(ownerSettings(project), await promotionsBlock());
  }
  if (myProjectRole === 'owner') {
    page.append(el('p', {}, el('a', { href: '/projek-saya', text: 'Kelola pengajuan di Projek Saya' })));
  }
  if (user.role === 'moderator' || user.role === 'admin') {
    page.append(staffBlock(project));
  }

  content.replaceChildren(page);
}

await render();
