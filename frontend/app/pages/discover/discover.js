import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { chip, emptyNote, projectItem } from '../../../shared/helpers/ui.js';
import { searchProjects } from '../../../features/projects/discover.api.js';
import { fetchTags } from '../../../features/projects/projects.api.js';

const { notify } = await startPage({ active: 'discover' });

const PER_PAGE = 10;
const form = document.getElementById('filters');
const results = document.getElementById('results');
const pager = document.getElementById('pager');
let page = 1;

form.elements.q.value = new URLSearchParams(location.search).get('q') || '';

// The tag box suggests the official tags. Failing to load them only loses the suggestions.
try {
  const tagList = document.getElementById('tag-list');
  for (const tag of await fetchTags()) {
    tagList.append(el('option', { value: tag.name }));
  }
} catch {
  // no suggestions
}

function buildQuery() {
  const query = {
    page: String(page),
    per_page: String(PER_PAGE),
    sort: form.elements.sort.value,
  };

  const keyword = form.elements.q.value.trim();
  if (keyword) {
    query.q = keyword;
  }

  const tags = [];
  for (const name of form.elements.tags.value.split(',')) {
    const clean = name.trim();
    if (clean) {
      tags.push(clean);
    }
  }
  if (tags.length > 0) {
    query.tags = tags.join(',');
  }

  if (form.elements.selection_method.value) {
    query.selection_method = form.elements.selection_method.value;
  }

  const program = form.elements.study_program.value.trim();
  if (program) {
    query.study_program = program;
  }

  if (form.elements.open.checked) {
    query.open = '1';
  }
  return query;
}

// What this person can do with the project, in one chip.
function stateChip(project) {
  if (project.my_project_role) {
    return chip('Anda ada di proyek ini', 'green');
  }
  if (project.my_application === 'pending') {
    return chip('Lamaran terkirim', 'orange');
  }
  if (project.can_apply) {
    return chip('Menerima lamaran', 'green');
  }
  return chip('Tidak menerima lamaran');
}

function showPager(total) {
  const pages = Math.max(1, Math.ceil(total / PER_PAGE));
  const previous = el('button', { class: 'btn btn--ghost btn--small', type: 'button', text: 'Sebelumnya' });
  const next = el('button', { class: 'btn btn--ghost btn--small', type: 'button', text: 'Berikutnya' });
  previous.disabled = page <= 1;
  next.disabled = page >= pages;
  previous.addEventListener('click', () => {
    page -= 1;
    load();
  });
  next.addEventListener('click', () => {
    page += 1;
    load();
  });
  pager.replaceChildren(previous, `Halaman ${page} dari ${pages} (${total} proyek)`, next);
}

async function load() {
  notify('');
  let data;
  try {
    data = await searchProjects(buildQuery());
  } catch (err) {
    notify(err.message, 'error');
    return;
  }

  results.replaceChildren();
  if (data.projects.length === 0) {
    results.append(emptyNote('Tidak ada proyek yang cocok.'));
  }
  for (const project of data.projects) {
    results.append(projectItem(project, el('div', { class: 'item-meta' }, stateChip(project))));
  }
  showPager(data.total);
}

form.addEventListener('submit', (e) => {
  e.preventDefault();
  page = 1;
  load();
});

await load();
