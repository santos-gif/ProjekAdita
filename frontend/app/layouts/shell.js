import { el } from '../../shared/helpers/dom.js';
import { initials, firstName } from '../../shared/helpers/format.js';
import { buildBell } from './notification-bell.js';

/** The menu depends on the account role: moderators and admins get the review screens, admins also the user list. */
function navItemsFor(user) {
  const items = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard' },
    { key: 'discover', label: 'Jelajahi', href: '/discover' },
    { key: 'projek-saya', label: 'Projek Saya', href: '/projek-saya' },
    { key: 'joined', label: 'Projek yang diikuti', href: '/joined' },
    { key: 'applications', label: 'Lamaran', href: '/applications' },
  ];
  if (user.role === 'moderator' || user.role === 'admin') {
    items.push({ key: 'review', label: 'Tinjau Projek', href: '/review' });
    items.push({ key: 'reports', label: 'Laporan', href: '/reports' });
  }
  if (user.role === 'admin') {
    items.push({ key: 'users', label: 'Pengguna', href: '/users' });
  }
  return items;
}

function buildNavItem(item, activeKey) {
  const isCurrent = item.key === activeKey;
  return el('li', {},
    el('a', {
      class: 'nav-item',
      href: item.href,
      ...(isCurrent ? { 'aria-current': 'page' } : {}),
      text: item.label
    })
  );
}

/** Builds the header and the navigation drawer. */
export function mountShell(root, { user, active = 'dashboard', onLogout, onSearch }) {
  // Burger button that opens the navigation drawer
  const burger = el('button', {
    class: 'icon-btn',
    type: 'button',
    'aria-label': 'Buka menu navigasi',
    'aria-expanded': 'false',
    'aria-controls': 'sidebar'
  }, el('span', { class: 'burger', 'aria-hidden': 'true' }));

  const brand = el('a', { class: 'brand', href: '/dashboard', text: 'ProjekAdita' });

  // Search input with Cari button
  const search = el('form', { class: 'search', role: 'search' },
    el('label', { class: 'sr-only', for: 'q', text: 'Cari proyek' }),
    el('input', { id: 'q', name: 'q', type: 'search', placeholder: 'Cari proyek...', autocomplete: 'off' }),
    el('button', { type: 'submit', text: 'Cari' })
  );
  search.addEventListener('submit', (e) => {
    e.preventDefault();
    const keyword = search.q.value.trim();
    if (onSearch) {
      onSearch(keyword);
    } else {
      location.assign(`/discover?q=${encodeURIComponent(keyword)}`);
    }
  });

  // The bell opens a panel with the person's notifications (decisions on their projects and applications).
  const bell = buildBell();

  // Avatar and name open the profile page; logout is its own button next to them.
  const avatar = el('span', { class: 'avatar', text: initials(user.name) });
  const userName = el('span', { class: 'header-user-name', text: firstName(user.name) });
  const profileLink = el('a', {
    class: 'header-profile-btn',
    href: '/profile',
    'aria-label': 'Profil saya',
    ...(active === 'profile' ? { 'aria-current': 'page' } : {})
  }, avatar, userName);

  const logoutBtn = el('button', { class: 'btn header-logout', type: 'button', text: 'Keluar' });
  logoutBtn.addEventListener('click', onLogout);

  const userWrapper = el('div', { class: 'header-user-wrapper' }, bell.element, profileLink, logoutBtn);

  // Build header
  const header = el('header', { class: 'app-header' },
    burger,
    brand,
    search,
    userWrapper
  );

  root.replaceChildren(header);

  // The drawer: the page's own <aside id="sidebar"> when it has one, otherwise one made here.
  let sidebar = document.getElementById('sidebar');
  if (!sidebar) {
    sidebar = el('aside', { id: 'sidebar', class: 'sidebar', 'aria-label': 'Menu navigasi' });
    const appLayout = document.querySelector('.app-layout');
    if (appLayout) {
      appLayout.prepend(sidebar);
    } else {
      const main = document.getElementById('main');
      if (main && main.parentNode) {
        const layout = el('div', { class: 'app-layout' });
        main.parentNode.insertBefore(layout, main);
        layout.append(sidebar, main);
      }
    }
  }

  const closeBtn = el('button', {
    class: 'icon-btn sidebar-close',
    type: 'button',
    'aria-label': 'Tutup navigasi',
    text: '✕'
  });

  const sidebarHead = el('div', { class: 'sidebar-head' },
    el('span', { class: 'sidebar-title', text: 'Menu Navigasi' }),
    closeBtn
  );

  const navList = el('ul', { class: 'nav' }, ...navItemsFor(user).map((it) => buildNavItem(it, active)));
  const sidebarNav = el('nav', { class: 'sidebar-nav', 'aria-label': 'Navigasi utama' }, navList);

  sidebar.replaceChildren(sidebarHead, sidebarNav);

  // The dimmed part of the page behind the drawer; a click on it closes the drawer.
  const scrim = el('div', { class: 'scrim', 'aria-hidden': 'true' });
  document.body.append(scrim);

  // The drawer starts closed on every page.
  const setDrawer = (open) => {
    sidebar.dataset.collapsed = String(!open);
    scrim.dataset.open = String(open);
    burger.setAttribute('aria-expanded', String(open));
    if (open) {
      closeBtn.focus();
    }
  };
  setDrawer(false);

  burger.addEventListener('click', () => setDrawer(sidebar.dataset.collapsed === 'true'));
  closeBtn.addEventListener('click', () => {
    setDrawer(false);
    burger.focus();
  });
  scrim.addEventListener('click', () => setDrawer(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar.dataset.collapsed === 'false') {
      setDrawer(false);
      burger.focus();
    }
  });

  return { search, sidebar };
}
