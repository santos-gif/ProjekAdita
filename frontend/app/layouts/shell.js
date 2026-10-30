import { el } from '../../shared/helpers/dom.js';
import { initials } from '../../shared/helpers/format.js';

const NAV = ['Add Project', 'Projek Saya', 'Projek yang diikuti', 'Lamaran'];

function navItem(label, current) {
  if (current) return el('li', {}, el('a', { class: 'nav-item', href: '/dashboard', 'aria-current': 'page', text: label }));
  return el('li', {}, el('span', { class: 'nav-item' }, label, el('span', { class: 'badge', text: 'Segera hadir' })));
}

/** Builds header + drawer into root. Returns { search } form element. */
export function mountShell(root, { user, onLogout, onSearch }) {
  const burger = el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Buka menu navigasi', 'aria-expanded': 'false', 'aria-controls': 'drawer' }, el('span', { class: 'burger', 'aria-hidden': 'true' }));
  const closeBtn = el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Tutup menu navigasi', text: 'X' });
  const items = [navItem('Dashboard', true), ...NAV.map((n) => navItem(n))];
  if (user.role === 'admin') items.push(navItem('Projek Request'));
  const drawer = el('aside', { id: 'drawer', class: 'drawer', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Menu navigasi' },
    el('div', { class: 'drawer-head' }, el('span', { text: 'Campus Collab' }), closeBtn),
    el('nav', { 'aria-label': 'Navigasi utama' }, el('ul', { class: 'nav' }, ...items)));
  const scrim = el('div', { class: 'scrim' });

  const search = el('form', { class: 'search', role: 'search' },
    el('label', { class: 'sr-only', for: 'q', text: 'Cari proyek' }),
    el('input', { id: 'q', name: 'q', type: 'search', placeholder: 'Cari proyek...', autocomplete: 'off' }),
    el('button', { type: 'submit', text: 'Cari' }));
  search.addEventListener('submit', (e) => { e.preventDefault(); onSearch?.(search.q.value.trim()); });

  const logoutBtn = el('button', { class: 'btn btn--ghost', type: 'button', text: 'Keluar' });
  logoutBtn.addEventListener('click', onLogout);
  const panel = el('div', { id: 'user-menu', class: 'menu-panel', hidden: true },
    el('div', {}, el('p', { class: 'who', text: user.name }), el('p', { class: 'mail', text: user.email })), logoutBtn);
  const avatar = el('button', { class: 'icon-btn avatar', type: 'button', 'aria-label': 'Menu akun', 'aria-expanded': 'false', 'aria-controls': 'user-menu', text: initials(user.name) });
  const menu = el('div', { class: 'menu' }, avatar, panel);

  root.append(
    el('header', { class: 'app-header' }, burger, el('a', { class: 'brand', href: '/dashboard', text: 'Campus Collab' }), search, menu),
    scrim, drawer);

  const setDrawer = (open) => {
    drawer.dataset.open = scrim.dataset.open = String(open);
    burger.setAttribute('aria-expanded', String(open));
    document.documentElement.classList.toggle('no-scroll', open);
    (open ? closeBtn : burger).focus();
  };
  const setMenu = (open) => {
    panel.hidden = !open;
    avatar.setAttribute('aria-expanded', String(open));
  };
  const drawerOpen = () => drawer.dataset.open === 'true';

  burger.addEventListener('click', () => setDrawer(true));
  closeBtn.addEventListener('click', () => setDrawer(false));
  scrim.addEventListener('click', () => setDrawer(false));
  avatar.addEventListener('click', () => setMenu(panel.hidden));
  document.addEventListener('click', (e) => { if (!panel.hidden && !menu.contains(e.target)) setMenu(false); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (drawerOpen()) setDrawer(false);
      else if (!panel.hidden) { setMenu(false); avatar.focus(); }
    } else if (e.key === 'Tab' && drawerOpen()) {
      const f = [...drawer.querySelectorAll('a[href], button:not([disabled])')];
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  return { search };
}
