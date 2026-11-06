import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { mountShell } from '../../layouts/shell.js';
import { listMine, deleteProject } from '../../../features/projects/projects.api.js';

const notification = document.getElementById('notification');
function notify(text, kind = 'info') {
  notification.hidden = !text;
  if (text) {
    notification.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    setMessage(notification, text, kind);
    notification.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

// Inisialisasi User & Shell Layout
let currentUser = null;
try {
  currentUser = await requireUser();
} catch (err) {
  notify(err.message, 'error');
}

if (currentUser) {
  mountShell(document.getElementById('shell'), {
    user: currentUser,
    active: 'projek-saya',
    onLogout: async () => {
      try {
        await logout();
        location.replace('/login');
      } catch (err) {
        notify(err.message, 'error');
      }
    },
    onSearch: (keyword) => handleSearch(keyword),
  });
}

let allProjects = [];
let currentFilter = 'all'; // 'all' | 'approved' | 'pending'
let searchQuery = '';

// My projects come from the backend once; the tabs, the counts and the search then work on this list.
async function refresh() {
  try {
    allProjects = await listMine();
  } catch (err) {
    notify(err.message, 'error');
  }
  renderProjects();
}

// DOM Elements
const listContainer = document.getElementById('projects-list');
const emptyState = document.getElementById('empty-state');
const countAll = document.getElementById('count-all');
const countApproved = document.getElementById('count-approved');
const countPending = document.getElementById('count-pending');
const paginationInfo = document.getElementById('pagination-info');

// Dialog Detail Elements
const detailDialog = document.getElementById('project-detail-dialog');
const modalCloseBtn = document.getElementById('modal-close-btn');
const modalDismissBtn = document.getElementById('modal-dismiss-btn');
const modalBtnEdit = document.getElementById('modal-btn-edit');
const modalBtnCancel = document.getElementById('modal-btn-cancel');
const modalTitle = document.getElementById('modal-title');
const modalCategory = document.getElementById('modal-category');
const modalStatus = document.getElementById('modal-status');
const modalTime = document.getElementById('modal-time');
const modalDesc = document.getElementById('modal-desc');
const modalReq = document.getElementById('modal-req');
const modalReqSection = document.getElementById('modal-req-section');
const modalSelection = document.getElementById('modal-selection');
const modalMembers = document.getElementById('modal-members');
const modalTags = document.getElementById('modal-tags');

// Confirmation Cancel Dialog Elements
const cancelDialog = document.getElementById('cancel-confirm-dialog');
const cancelConfirmDesc = document.getElementById('cancel-confirm-desc');
const btnAbortCancel = document.getElementById('btn-abort-cancel');
const btnProceedCancel = document.getElementById('btn-proceed-cancel');

let projectToCancel = null;

function closeDetailDialog() {
  if (detailDialog.open) detailDialog.close();
}
modalCloseBtn?.addEventListener('click', closeDetailDialog);
modalDismissBtn?.addEventListener('click', closeDetailDialog);
detailDialog?.addEventListener('click', (e) => {
  if (e.target === detailDialog) closeDetailDialog();
});

function openCancelConfirmation(project) {
  projectToCancel = project;
  cancelConfirmDesc.textContent = `Apakah Anda yakin ingin membatalkan pengajuan proyek "${project.title}"? Pengajuan ini akan dihapus dari antrean persetujuan.`;
  cancelDialog.showModal();
}

function closeCancelDialog() {
  projectToCancel = null;
  if (cancelDialog.open) cancelDialog.close();
}

btnAbortCancel?.addEventListener('click', closeCancelDialog);
cancelDialog?.addEventListener('click', (e) => {
  if (e.target === cancelDialog) closeCancelDialog();
});

btnProceedCancel?.addEventListener('click', async () => {
  if (!projectToCancel) return;
  const targetId = projectToCancel.id;
  const targetTitle = projectToCancel.title;

  closeCancelDialog();
  closeDetailDialog();

  try {
    await deleteProject(targetId);
  } catch (err) {
    notify(err.message, 'error');
    return;
  }
  await refresh();
  notify(`Pengajuan proyek "${targetTitle}" berhasil dibatalkan.`, 'info');
});

function openProjectDetail(project) {
  modalTitle.textContent = project.title || 'Tanpa Judul';
  modalCategory.textContent = project.category || 'Riset Terapan';
  
  const isApproved = project.status === 'approved';
  modalStatus.textContent = isApproved ? 'Disetujui' : 'Menunggu Persetujuan';
  modalStatus.className = `status-badge ${isApproved ? 'status-badge--approved' : 'status-badge--pending'}`;
  
  modalTime.textContent = project.timeAgo || 'Baru saja';
  modalDesc.textContent = project.description || '-';
  
  if (project.requirements) {
    modalReqSection.hidden = false;
    modalReq.textContent = project.requirements;
  } else {
    modalReqSection.hidden = true;
  }

  modalSelection.textContent = project.selection_method || 'Kurasi Portofolio & CV';
  modalMembers.textContent = project.members || `${project.members_count || 1}/${project.max_members || 4} Anggota Terisi`;

  modalTags.replaceChildren();
  if (Array.isArray(project.tags)) {
    project.tags.forEach((tag) => {
      const pill = document.createElement('span');
      pill.className = 'card-tag-pill';
      pill.textContent = `#${tag.replace(/^#/, '')}`;
      modalTags.appendChild(pill);
    });
  }

  // Tampilkan tombol Edit & Batal jika proyek berstatus pending
  if (project.status === 'pending') {
    modalBtnEdit.hidden = false;
    modalBtnEdit.href = `/add-project?edit=${project.id}`;
    
    modalBtnCancel.hidden = false;
    modalBtnCancel.onclick = () => {
      closeDetailDialog();
      openCancelConfirmation(project);
    };
  } else {
    modalBtnEdit.hidden = true;
    modalBtnCancel.hidden = true;
  }

  detailDialog.showModal();
}

// Generate Thumbnail Poster HTML/Canvas
function renderThumbnail(project) {
  const thumbWrap = document.createElement('div');
  thumbWrap.className = 'card-thumb-wrap';

  if (project.thumbnail) {
    const img = document.createElement('img');
    img.className = 'card-thumb-img';
    img.src = project.thumbnail;
    img.alt = `Cover ${project.title}`;
    thumbWrap.appendChild(img);
    return thumbWrap;
  }

  const poster = document.createElement('div');
  if (project.thumbnailType === 'cyber') {
    poster.className = 'mock-poster mock-poster--cyber';
    poster.innerHTML = `
      <div class="poster-graphic-glow"></div>
      <div class="poster-header-tag">CYBER SECURITY PROJECT</div>
      <div style="font-size: 7px; color: #38bdf8; letter-spacing: 0.5px;">CAMPUS NETWORK DEFENSE</div>
      <div>
        <div class="poster-title">CYBER SECURITY</div>
        <div class="poster-subtitle">Shielding Against Cyber Threats</div>
      </div>
    `;
  } else if (project.thumbnailType === 'ai') {
    poster.className = 'mock-poster mock-poster--ai';
    poster.innerHTML = `
      <div class="poster-graphic-glow" style="background: radial-gradient(circle, rgba(168, 85, 247, 0.3) 0%, transparent 70%);"></div>
      <div class="poster-header-tag" style="color: #c084fc;">ARTIFICIAL INTELLIGENCE ROBOTICS</div>
      <div style="font-size: 7px; color: #a855f7; letter-spacing: 0.5px;">INNOVATION & FUTURE FRONTIERS</div>
      <div>
        <div class="poster-title">ARTIFICIAL INTELLIGENCE</div>
        <div class="poster-subtitle">Robotics & Automation Presentation</div>
      </div>
    `;
  } else {
    poster.className = 'mock-poster mock-poster--generic';
    const cleanTitle = (project.title || 'PROJECT').toUpperCase();
    poster.innerHTML = `
      <div class="poster-graphic-glow" style="background: radial-gradient(circle, rgba(34, 197, 94, 0.25) 0%, transparent 70%);"></div>
      <div class="poster-header-tag" style="color: #4ade80;">PROYEK KOLABORASI</div>
      <div style="font-size: 7px; color: #86efac; letter-spacing: 0.5px;">PROJEKADITA KAMPUS</div>
      <div>
        <div class="poster-title">${cleanTitle.slice(0, 30)}</div>
        <div class="poster-subtitle">${project.category || 'Riset Terapan'}</div>
      </div>
    `;
  }

  thumbWrap.appendChild(poster);
  return thumbWrap;
}

// Render Card List
function renderProjects() {

  const totalCount = allProjects.length;
  const approvedCount = allProjects.filter(p => p.status === 'approved').length;
  const pendingCount = allProjects.filter(p => p.status === 'pending').length;

  countAll.textContent = totalCount;
  countApproved.textContent = approvedCount;
  countPending.textContent = pendingCount;

  let filtered = allProjects;
  if (currentFilter === 'approved') {
    filtered = filtered.filter(p => p.status === 'approved');
  } else if (currentFilter === 'pending') {
    filtered = filtered.filter(p => p.status === 'pending');
  }

  if (searchQuery) {
    const q = searchQuery.toLowerCase();
    filtered = filtered.filter(p => 
      (p.title && p.title.toLowerCase().includes(q)) ||
      (p.description && p.description.toLowerCase().includes(q)) ||
      (Array.isArray(p.tags) && p.tags.some(t => t.toLowerCase().includes(q)))
    );
  }

  paginationInfo.textContent = `Menampilkan ${filtered.length} dari ${totalCount} proyek aktif`;
  listContainer.replaceChildren();

  if (filtered.length === 0) {
    emptyState.hidden = false;
    listContainer.hidden = true;
    return;
  }

  emptyState.hidden = true;
  listContainer.hidden = false;

  filtered.forEach((project) => {
    const card = document.createElement('article');
    card.className = 'project-card-item';
    card.setAttribute('aria-labelledby', `project-title-${project.id}`);

    // Thumbnail
    const thumb = renderThumbnail(project);

    // Info Tengah
    const info = document.createElement('div');
    info.className = 'card-info';

    // Meta Top (Kategori + Waktu)
    const metaTop = document.createElement('div');
    metaTop.className = 'card-meta-top';
    const catBadge = document.createElement('span');
    catBadge.className = 'card-cat-badge';
    catBadge.textContent = project.category || 'Riset Terapan';
    const dot = document.createElement('span');
    dot.className = 'card-meta-dot';
    dot.textContent = '•';
    const timeText = document.createElement('span');
    timeText.className = 'card-time-text';
    timeText.textContent = project.timeAgo || 'Baru saja';
    metaTop.append(catBadge, dot, timeText);

    // Title
    const title = document.createElement('h2');
    title.id = `project-title-${project.id}`;
    title.className = 'card-title';
    title.textContent = project.title || 'Tanpa Judul';

    // Desc
    const desc = document.createElement('p');
    desc.className = 'card-desc';
    desc.textContent = project.description || '';

    // Meta Bottom (Members + Tags)
    const metaBottom = document.createElement('div');
    metaBottom.className = 'card-meta-bottom';

    // Members
    const membersWrap = document.createElement('div');
    membersWrap.className = 'card-members';
    membersWrap.innerHTML = `
      <svg class="members-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>${project.members || `${project.members_count || 1}/${project.max_members || 4} Anggota Terisi`}</span>
    `;

    // Tags
    const tagsWrap = document.createElement('div');
    tagsWrap.className = 'card-tags';
    if (Array.isArray(project.tags)) {
      project.tags.forEach((tag) => {
        const tagPill = document.createElement('span');
        tagPill.className = 'card-tag-pill';
        tagPill.textContent = `#${tag.replace(/^#/, '')}`;
        tagsWrap.appendChild(tagPill);
      });
    }

    metaBottom.append(membersWrap, tagsWrap);
    info.append(metaTop, title, desc, metaBottom);

    // Kanan (Status + Aksi)
    const rightSection = document.createElement('div');
    rightSection.className = 'card-actions-right';

    const statusBadge = document.createElement('span');
    const isApproved = project.status === 'approved';
    statusBadge.className = `status-badge ${isApproved ? 'status-badge--approved' : 'status-badge--pending'}`;
    statusBadge.textContent = isApproved ? 'Disetujui' : 'Pending';

    // Links / Action Buttons
    const actionLinks = document.createElement('div');
    actionLinks.className = 'card-action-links';

    const detailBtn = document.createElement('button');
    detailBtn.type = 'button';
    detailBtn.className = 'btn-view-detail';
    detailBtn.textContent = 'Lihat Rincian';
    detailBtn.setAttribute('aria-label', `Lihat rincian proyek ${project.title}`);
    detailBtn.addEventListener('click', () => openProjectDetail(project));
    actionLinks.appendChild(detailBtn);

    // Jika proyek masih diajukan (pending), tambahkan tombol Edit dan Batalkan
    if (project.status === 'pending') {
      const editBtn = document.createElement('a');
      editBtn.className = 'btn-card-action btn-card-edit';
      editBtn.href = `/add-project?edit=${project.id}`;
      editBtn.textContent = 'Edit';
      editBtn.setAttribute('aria-label', `Edit pengajuan proyek ${project.title}`);

      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn-card-action btn-card-cancel';
      cancelBtn.textContent = 'Batalkan';
      cancelBtn.setAttribute('aria-label', `Batalkan pengajuan proyek ${project.title}`);
      cancelBtn.addEventListener('click', () => openCancelConfirmation(project));

      actionLinks.append(editBtn, cancelBtn);
    }

    rightSection.append(statusBadge, actionLinks);
    card.append(thumb, info, rightSection);
    listContainer.appendChild(card);
  });
}

// Bind Tabs
const tabs = document.querySelectorAll('.tab-pill');
tabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => {
      t.classList.remove('is-active');
      t.setAttribute('aria-selected', 'false');
    });
    tab.classList.add('is-active');
    tab.setAttribute('aria-selected', 'true');
    currentFilter = tab.dataset.filter || 'all';
    renderProjects();
  });
});

// Search Handler
function handleSearch(keyword) {
  searchQuery = keyword.trim();
  renderProjects();
  if (searchQuery) {
    notify(`Menampilkan hasil pencarian untuk: "${searchQuery}"`, 'info');
  } else {
    notify('', 'info');
  }
}

// Search input realtime di header
const searchInput = document.getElementById('q');
if (searchInput) {
  searchInput.addEventListener('input', (e) => {
    searchQuery = e.target.value.trim();
    renderProjects();
  });
}

// Initial render
await refresh();
