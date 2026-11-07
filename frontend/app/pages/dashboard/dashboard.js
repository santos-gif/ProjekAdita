import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { firstName } from '../../../shared/helpers/format.js';
import { getDashboard } from '../../../features/dashboard/dashboard.api.js';
import { getStats } from '../../../features/admin/admin.api.js';

const { user, notify } = await startPage({ active: 'dashboard' });
const content = document.getElementById('content');
document.getElementById('title').textContent = `Halo, ${firstName(user.name)}`;

/** A number that links to the page where you act on it. */
function stat(label, value, href) {
  return el('a', { class: 'stat', href },
    el('span', { class: 'stat-number', text: String(value) }),
    el('span', { class: 'stat-label', text: label })
  );
}

function section(title, ...tiles) {
  return el('section', { class: 'detail-block' }, el('h2', { text: title }), el('div', { class: 'stat-grid' }, ...tiles));
}

/** {name: count} as small tiles that do not link anywhere. */
function countTiles(counts, labels) {
  const tiles = [];
  for (const [name, total] of Object.entries(counts)) {
    tiles.push(el('div', { class: 'stat' },
      el('span', { class: 'stat-number', text: String(total) }),
      el('span', { class: 'stat-label', text: labels[name] || name })
    ));
  }
  return tiles;
}

const ROLE_LABEL = { user: 'Pengguna', moderator: 'Moderator', admin: 'Admin' };
const PROJECT_LABEL = {
  pending_moderation: 'Menunggu moderator',
  pending_admin: 'Menunggu admin',
  published: 'Terbit',
  rejected: 'Ditolak',
};
const REPORT_LABEL = { open: 'Laporan terbuka', forwarded: 'Laporan diteruskan', dismissed: 'Laporan diabaikan', actioned: 'Laporan ditindak' };

/** The API sends my projects as {status: count}, with 0 for each status that has none; the tile shows the total. */
function totalOf(counts) {
  let total = 0;
  for (const count of Object.values(counts || {})) {
    total += count;
  }
  return total;
}

function studentSection(student) {
  let draft = 'Belum ada';
  if (student.has_draft) {
    draft = 'Tersimpan';
  }
  return section('Aktivitas saya',
    stat('Projek saya', totalOf(student.my_projects), '/projek-saya'),
    stat('Projek yang diikuti', student.joined_projects, '/joined'),
    stat('Lamaran saya yang menunggu', student.my_applications_pending, '/applications'),
    stat('Lamaran masuk ke projek saya', student.applications_to_review, '/projek-saya'),
    stat('Permintaan promosi untuk diputuskan', student.promotion_requests_to_decide, '/projek-saya'),
    stat('Draf proyek', draft, '/add-project')
  );
}

function moderatorSection(moderator) {
  return section('Moderasi',
    stat('Antrean tahap 1', moderator.moderation_queue, '/review'),
    stat('Keputusan saya', moderator.my_reviews, '/review'),
    stat('Laporan terbuka', moderator.reports_open, '/reports')
  );
}

function adminSection(admin) {
  return section('Admin',
    stat('Antrean akhir', admin.admin_queue, '/review'),
    stat('Laporan diteruskan', admin.reports_forwarded, '/reports'),
    stat('Total pengguna', admin.users_total, '/users')
  );
}

async function statsSections() {
  const stats = await getStats();
  return [
    section('Pengguna menurut peran', ...countTiles(stats.users_by_role, ROLE_LABEL)),
    section('Proyek menurut status', ...countTiles(stats.projects_by_status, PROJECT_LABEL)),
    section('Laporan menurut status', ...countTiles(stats.reports_by_status, REPORT_LABEL)),
  ];
}

try {
  const data = await getDashboard();
  content.append(studentSection(data.student));
  if (data.moderator) {
    content.append(moderatorSection(data.moderator));
  }
  if (data.admin) {
    content.append(adminSection(data.admin));
    for (const block of await statsSections()) {
      content.append(block);
    }
  }
} catch (err) {
  notify(err.message, 'error');
}
