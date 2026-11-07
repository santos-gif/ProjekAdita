import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { emptyNote, projectItem, roleChip } from '../../../shared/helpers/ui.js';
import { listJoined } from '../../../features/projects/discover.api.js';

const { notify } = await startPage({ active: 'joined' });
const list = document.getElementById('list');

let projects = [];
try {
  projects = await listJoined();
} catch (err) {
  notify(err.message, 'error');
}

if (projects.length === 0) {
  list.append(emptyNote('Anda belum bergabung ke proyek mana pun. Lamar proyek dari halaman Jelajahi.'));
}
for (const project of projects) {
  const extra = el('div', { class: 'item-meta' });
  if (project.my_project_role) {
    extra.append(roleChip(project.my_project_role));
  }
  list.append(projectItem(project, extra));
}
