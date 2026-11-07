import { startPage } from '../../layouts/page.js';
import { el } from '../../../shared/helpers/dom.js';
import { emptyNote, makeActions, roleChip } from '../../../shared/helpers/ui.js';
import { listUsers, setUserRole } from '../../../features/admin/admin.api.js';

const { user, notify } = await startPage({ active: 'users' });

const PER_PAGE = 20;
const form = document.getElementById('filters');
const list = document.getElementById('list');
const pager = document.getElementById('pager');
const { formButton } = makeActions(notify, load);
let page = 1;

function buildQuery() {
  const query = { page: String(page), per_page: String(PER_PAGE) };
  const keyword = form.elements.q.value.trim();
  if (keyword) {
    query.q = keyword;
  }
  if (form.elements.role.value) {
    query.role = form.elements.role.value;
  }
  return query;
}

function row(person) {
  const meta = el('div', { class: 'item-meta' }, roleChip(person.role), person.email);
  if (person.study_program) {
    meta.append(person.study_program);
  }
  if (person.cohort_year) {
    meta.append(`angkatan ${person.cohort_year}`);
  }

  const buttons = el('div', { class: 'item-actions' });
  // The API refuses to change your own role, so the button is only offered for other people.
  if (person.id !== user.id) {
    buttons.append(formButton('Ubah peran', 'btn btn--ghost btn--small', {
      title: `Ubah peran ${person.name}`,
      confirmLabel: 'Simpan',
      fields: [{
        name: 'role',
        label: 'Peran',
        type: 'select',
        value: person.role,
        options: [
          { value: 'user', label: 'Pengguna' },
          { value: 'moderator', label: 'Moderator' },
          { value: 'admin', label: 'Admin' },
        ],
      }],
      submit: (v) => setUserRole(person.id, v.role),
    }, 'Peran diubah.'));
  }

  return el('article', { class: 'item' },
    el('h2', { class: 'item-title' }, el('a', { href: `/user?id=${person.id}`, text: person.name })),
    meta,
    buttons
  );
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
  pager.replaceChildren(previous, `Halaman ${page} dari ${pages} (${total} pengguna)`, next);
}

async function load() {
  notify('');
  let data;
  try {
    data = await listUsers(buildQuery());
  } catch (err) {
    notify(err.message, 'error');
    return;
  }
  list.replaceChildren();
  if (data.users.length === 0) {
    list.append(emptyNote('Tidak ada pengguna yang cocok.'));
  }
  for (const person of data.users) {
    list.append(row(person));
  }
  showPager(data.total);
}

form.addEventListener('submit', (e) => {
  e.preventDefault();
  page = 1;
  load();
});

await load();
