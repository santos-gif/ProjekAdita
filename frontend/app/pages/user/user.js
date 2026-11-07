import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { initials } from '../../../shared/helpers/format.js';
import { dateText, emptyNote, roleChip } from '../../../shared/helpers/ui.js';
import { getPublicProfile } from '../../../features/projects/discover.api.js';

const { user, notify } = await startPage({ active: 'discover' });
const userId = Number(new URLSearchParams(location.search).get('id'));
const content = document.getElementById('content');

let profile;
try {
  profile = await getPublicProfile(userId);
} catch (err) {
  notify(err.message, 'error');
  await new Promise(() => {});
}

const avatar = el('span', { class: 'avatar avatar--lg', 'aria-hidden': 'true' });
if (profile.avatar) {
  avatar.append(el('img', { src: profile.avatar, alt: '' }));
} else {
  avatar.textContent = initials(profile.name);
}

const meta = el('div', { class: 'item-meta' });
if (profile.study_program) {
  meta.append(profile.study_program);
}
if (profile.cohort_year) {
  meta.append(`angkatan ${profile.cohort_year}`);
}
meta.append(`anggota sejak ${dateText(profile.member_since)}`);

const heading = el('div', {}, el('h1', { text: profile.name }), meta);
const head = el('div', { class: 'panel-head' }, avatar, heading);
if (profile.id === user.id) {
  heading.append(el('a', { href: '/profile', text: 'Ubah profil saya' }));
}

const projects = el('div', { class: 'item-list' });
if (profile.projects.length === 0) {
  projects.append(emptyNote('Belum ada proyek terbit.'));
}
for (const project of profile.projects) {
  const projectMeta = el('div', { class: 'item-meta' }, roleChip(project.project_role));
  if (project.position) {
    projectMeta.append(project.position);
  }
  projects.append(el('article', { class: 'item' },
    el('h3', { class: 'item-title' }, el('a', { href: `/project?id=${project.id}`, text: project.title })),
    projectMeta
  ));
}

content.append(el('div', { class: 'detail' },
  head,
  el('section', { class: 'detail-block' }, el('h2', { text: 'Tentang' }), el('p', { class: 'item-text', text: profile.bio || 'Belum ada bio.' })),
  el('section', { class: 'detail-block' }, el('h2', { text: 'Proyek' }), projects)
));
