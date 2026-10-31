import { el } from '../../shared/helpers/dom.js';
import { initials, firstName } from '../../shared/helpers/format.js';

const NAV_ITEMS = [
  { key: 'dashboard', label: 'Dashboard', href: '/dashboard' },
  { key: 'add-project', label: 'Add Project', href: '/add-project' },
  { key: 'projek-saya', label: 'Projek Saya', badge: 'Segera hadir' },
  { key: 'projek-diikuti', label: 'Projek yang diikuti', badge: 'Segera hadir' },
  { key: 'lamaran', label: 'Lamaran', badge: 'Segera hadir' },
];

function buildNavItem(item, activeKey) {
  const isCurrent = item.key === activeKey;
  if (item.href) {
    return el('li', {},
      el('a', {
        class: 'nav-item',
        href: item.href,
        ...(isCurrent ? { 'aria-current': 'page' } : {}),
        text: item.label
      })
    );
  }
  return el('li', {},
    el('span', { class: 'nav-item nav-item--disabled' },
      el('span', { class: 'nav-item-text', text: item.label }),
      el('span', { class: 'badge', text: item.badge })
    )
  );
}

/** Builds header and open/collapse sidebar. */
export function mountShell(root, { user, active = 'dashboard', onLogout, onSearch }) {
  // Burger button to toggle sidebar collapse
  const burger = el('button', {
    class: 'icon-btn',
    type: 'button',
    'aria-label': 'Buka / tutup menu navigasi',
    'aria-expanded': 'true',
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
    onSearch?.(search.q.value.trim());
  });

  // Notification bell button
  const notifBtn = el('button', { class: 'icon-btn notif-btn', type: 'button', 'aria-label': 'Notifikasi' },
    el('span', { class: 'notif-icon', 'aria-hidden': 'true' }),
    el('span', { class: 'notif-dot', 'aria-hidden': 'true' })
  );

  // User profile / avatar in header
  const logoutBtn = el('button', { class: 'btn btn--ghost', type: 'button', text: 'Keluar' });
  logoutBtn.addEventListener('click', onLogout);

  const panel = el('div', { id: 'user-menu', class: 'menu-panel', hidden: true },
    el('div', {},
      el('p', { class: 'who', text: user.name }),
      el('p', { class: 'mail', text: user.email })
    ),
    logoutBtn
  );

  const avatar = el('span', { class: 'avatar', text: initials(user.name) });
  const userName = el('span', { class: 'header-user-name', text: firstName(user.name) });
  const profileBtn = el('button', {
    class: 'header-profile-btn',
    type: 'button',
    'aria-label': 'Menu akun',
    'aria-expanded': 'false',
    'aria-controls': 'user-menu'
  }, avatar, userName);

  const menu = el('div', { class: 'menu' }, profileBtn, panel);
  const userWrapper = el('div', { class: 'header-user-wrapper' }, notifBtn, menu);

  // Build header
  const header = el('header', { class: 'app-header' },
    burger,
    brand,
    search,
    userWrapper
  );

  root.replaceChildren(header);

  // Setup Sidebar (open/collapse without full page scrim)
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
    el('span', { class: 'sidebar-title', text: 'ProjekAdita' }),
    closeBtn
  );

  const items = [...NAV_ITEMS];
  if (user.role === 'admin') {
    items.push({ key: 'projek-request', label: 'Projek Request', badge: 'Segera hadir' });
  }

  const navList = el('ul', { class: 'nav' }, ...items.map((it) => buildNavItem(it, active)));
  const sidebarNav = el('nav', { class: 'sidebar-nav', 'aria-label': 'Navigasi utama' }, navList);

  sidebar.replaceChildren(sidebarHead, sidebarNav);

  // Collapse / Expand state (sidebar does not cover 1 full page)
  const isInitiallyCollapsed = localStorage.getItem('projekadita_sidebar_collapsed') === 'true';
  const setSidebar = (collapsed) => {
    sidebar.dataset.collapsed = String(collapsed);
    burger.setAttribute('aria-expanded', String(!collapsed));
    localStorage.setItem('projekadita_sidebar_collapsed', String(collapsed));
  };
  setSidebar(isInitiallyCollapsed);

  burger.addEventListener('click', () => {
    const collapsed = sidebar.dataset.collapsed === 'true';
    setSidebar(!collapsed);
  });
  closeBtn.addEventListener('click', () => setSidebar(true));

  // User menu dropdown toggle
  const setMenu = (open) => {
    panel.hidden = !open;
    profileBtn.setAttribute('aria-expanded', String(open));
  };
  profileBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    setMenu(panel.hidden);
  });
  document.addEventListener('click', (e) => {
    if (!panel.hidden && !menu.contains(e.target)) setMenu(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (!panel.hidden) { setMenu(false); profileBtn.focus(); }
    }
  });

  return { search, sidebar };
}
