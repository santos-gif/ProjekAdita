import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { mountShell } from '../../layouts/shell.js';
import { ALL_MASTER_TAGS } from '../../../features/projects/tags.data.js';

const notification = document.getElementById('notification');
function notify(text, kind = 'info') {
  notification.hidden = !text;
  if (text) {
    notification.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    setMessage(notification, text, kind);
    notification.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

try {
  const user = await requireUser();
  if (user) {
    mountShell(document.getElementById('shell'), {
      user,
      active: 'projek-saya',
      onLogout: async () => {
        try {
          await logout();
          location.replace('/login');
        } catch (err) {
          notify(err.message, 'error');
        }
      },
      onSearch: () => notify('Pencarian proyek belum tersedia.', 'info'),
    });
  }
} catch (err) {
  // If running without backend session in dev or direct access
  mountShell(document.getElementById('shell'), {
    user: { name: 'Pascal Edu', email: 'pascal@example.com', role: 'user' },
    active: 'projek-saya',
    onLogout: () => location.replace('/login'),
  });
}

// ---------- Thumbnail Upload & Safe Canvas Preview ----------
const thumbnailBox = document.getElementById('thumbnail-box');
const thumbnailInput = document.getElementById('thumbnail-input');
const thumbnailPreview = document.getElementById('thumbnail-preview');

thumbnailBox.addEventListener('click', () => thumbnailInput.click());
thumbnailBox.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault();
    thumbnailInput.click();
  }
});

let thumbnailDataUrl = null;

async function renderThumbnailPreview(fileOrUrl) {
  if (typeof fileOrUrl === 'string') {
    thumbnailDataUrl = fileOrUrl;
    const img = document.createElement('img');
    img.alt = 'Preview thumbnail';
    img.src = fileOrUrl;
    img.style.width = '100%';
    img.style.height = '100%';
    img.style.objectFit = 'cover';
    thumbnailPreview.replaceChildren(img);
    return;
  }

  const file = fileOrUrl;
  if (file.size > 2 * 1024 * 1024) {
    notify('Ukuran file melebihi 2MB. Silakan pilih gambar yang lebih kecil.', 'error');
    thumbnailInput.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = (e) => {
    thumbnailDataUrl = e.target.result;
  };
  reader.readAsDataURL(file);

  try {
    let bitmap;
    if (window.createImageBitmap) {
      bitmap = await createImageBitmap(file);
    } else {
      bitmap = await new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
      });
    }

    const canvas = document.createElement('canvas');
    const dpr = window.devicePixelRatio || 2;
    const boxW = thumbnailBox.clientWidth || 200;
    const boxH = thumbnailBox.clientHeight || 124;
    canvas.width = boxW * dpr;
    canvas.height = boxH * dpr;
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.display = 'block';
    canvas.style.borderRadius = 'inherit';

    const ctx = canvas.getContext('2d');
    const scale = Math.max(canvas.width / bitmap.width, canvas.height / bitmap.height);
    const x = (canvas.width - bitmap.width * scale) / 2;
    const y = (canvas.height - bitmap.height * scale) / 2;
    ctx.drawImage(bitmap, x, y, bitmap.width * scale, bitmap.height * scale);

    thumbnailPreview.replaceChildren(canvas);
  } catch (err) {
    console.warn('Canvas render fallback to img:', err);
    try {
      const blobUrl = URL.createObjectURL(file);
      const img = document.createElement('img');
      img.alt = 'Preview thumbnail';
      img.src = blobUrl;
      thumbnailPreview.replaceChildren(img);
    } catch {
      notify('Gagal menampilkan preview gambar.', 'error');
    }
  }
}

thumbnailInput.addEventListener('change', () => {
  const file = thumbnailInput.files?.[0];
  if (file) renderThumbnailPreview(file);
});

// ---------- Tags Handling (Hanya dari Daftar Resmi) ----------
const tagsBox = document.getElementById('tags-box');
const tagInput = document.getElementById('tag-input');
const tagsDropdown = document.getElementById('tags-dropdown');

function getSelectedTags() {
  return [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag.toLowerCase());
}

function clearAllTags() {
  tagsBox.querySelectorAll('.tag-pill').forEach(p => p.remove());
}

function addTagElement(officialTagName) {
  const cleanTag = officialTagName.trim();
  const selected = getSelectedTags();
  if (selected.includes(cleanTag.toLowerCase())) {
    tagInput.value = '';
    tagsDropdown.hidden = true;
    return;
  }

  const pill = document.createElement('div');
  pill.className = 'tag-pill tag-pill--gray';
  pill.dataset.tag = cleanTag;

  const textSpan = document.createElement('span');
  textSpan.className = 'tag-text';
  textSpan.textContent = `#${cleanTag}`;

  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'tag-remove';
  removeBtn.setAttribute('aria-label', `Hapus tag ${cleanTag}`);
  removeBtn.textContent = '×';
  removeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    pill.remove();
  });

  pill.append(textSpan, removeBtn);
  tagsBox.insertBefore(pill, tagInput);
  tagInput.value = '';
  tagsDropdown.hidden = true;
  notify('', 'info');
}

// Bind existing remove buttons
tagsBox.querySelectorAll('.tag-remove').forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    btn.closest('.tag-pill')?.remove();
  });
});

// Dropdown Autocomplete Search
function showTagSuggestions(query) {
  const cleanQ = query.trim().toLowerCase();
  if (!cleanQ) {
    tagsDropdown.hidden = true;
    tagsDropdown.replaceChildren();
    return;
  }

  const selected = getSelectedTags();
  // Filter tag resmi yang belum dipilih dan cocok dengan kata kunci (substring search)
  let matched = ALL_MASTER_TAGS.filter(t => 
    !selected.includes(t.name.toLowerCase()) && 
    t.name.toLowerCase().includes(cleanQ)
  );

  tagsDropdown.replaceChildren();

  if (matched.length === 0) {
    const emptyNotice = document.createElement('div');
    emptyNotice.className = 'tags-dropdown-empty';
    emptyNotice.textContent = `Tidak ditemukan tag resmi untuk "${query}". Tag hanya bisa dipilih dari daftar resmi.`;
    tagsDropdown.appendChild(emptyNotice);
    tagsDropdown.hidden = false;
    return;
  }

  // Smart sort: prioritaskan tag yang diawali query, lalu alfabetis
  matched.sort((a, b) => {
    const aName = a.name.toLowerCase();
    const bName = b.name.toLowerCase();
    const aStarts = aName.startsWith(cleanQ);
    const bStarts = bName.startsWith(cleanQ);
    if (aStarts && !bStarts) return -1;
    if (!aStarts && bStarts) return 1;
    return a.name.localeCompare(b.name);
  });

  // Tambahkan header info jumlah hasil
  const headerEl = document.createElement('div');
  headerEl.className = 'tags-dropdown-header';
  headerEl.innerHTML = `
    <span>Ditemukan <strong class="tags-dropdown-count">${matched.length}</strong> tag resmi</span>
    <span style="font-size: 0.6875rem; color: #94a3b8;">Gulir untuk melihat semua ↓</span>
  `;
  tagsDropdown.appendChild(headerEl);

  // Tampilkan SEMUA tag yang cocok (tanpa limit slice) sehingga seluruh elemen terlihat
  matched.forEach((item, index) => {
    const option = document.createElement('div');
    option.className = `tags-dropdown-item ${index === 0 ? 'is-selected' : ''}`;
    option.setAttribute('role', 'option');

    // Highlight text matching query
    const escapedQ = cleanQ.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp('(' + escapedQ + ')', 'gi');
    const highlightedName = item.name.replace(regex, '<mark>$1</mark>');

    option.innerHTML = `
      <span class="dropdown-tag-name">#${highlightedName}</span>
      <span class="dropdown-tag-category">${item.category}</span>
    `;

    option.addEventListener('mousedown', (e) => {
      e.preventDefault();
      addTagElement(item.name);
      tagInput.focus();
    });

    tagsDropdown.appendChild(option);
  });

  tagsDropdown.hidden = false;
}

tagInput.addEventListener('input', (e) => {
  showTagSuggestions(e.target.value);
});

tagInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const query = tagInput.value.trim().toLowerCase();
    if (!query) return;

    // Cek apakah ada yang cocok persis di master tags
    const exactMatch = ALL_MASTER_TAGS.find(t => t.name.toLowerCase() === query);
    if (exactMatch) {
      addTagElement(exactMatch.name);
      return;
    }

    // Jika tidak persis, ambil pilihan teratas di dropdown jika ada
    const firstOption = tagsDropdown.querySelector('.tags-dropdown-item');
    if (firstOption) {
      const selectedItem = ALL_MASTER_TAGS.find(t => 
        !getSelectedTags().includes(t.name.toLowerCase()) && 
        t.name.toLowerCase().includes(query)
      );
      if (selectedItem) {
        addTagElement(selectedItem.name);
        return;
      }
    }

    // Jika tidak ada yang cocok di daftar resmi, tolak dan beri tahu pengguna
    notify(`Tag "${tagInput.value.trim()}" tidak ditemukan dalam daftar tag resmi. Silakan pilih tag dari daftar saran yang tersedia.`, 'error');
  } else if (e.key === 'Escape') {
    tagsDropdown.hidden = true;
  } else if (e.key === 'Backspace' && !tagInput.value) {
    const pills = tagsBox.querySelectorAll('.tag-pill');
    if (pills.length > 0) {
      pills[pills.length - 1].remove();
    }
  }
});

// Sembunyikan dropdown jika klik di luar
document.addEventListener('click', (e) => {
  if (!tagsBox.contains(e.target) && !tagsDropdown.contains(e.target)) {
    tagsDropdown.hidden = true;
  }
});

// ---------- Description Character Counter ----------
const descInput = document.getElementById('project-desc');
const descCountLabel = document.getElementById('desc-count-label');
const descError = document.getElementById('desc-error');

function updateDescCounter() {
  const len = descInput.value.trim().length;
  if (len < 50) {
    descCountLabel.textContent = `Minimal 50 karakter (${len}/50)`;
    descCountLabel.style.color = '#ef4444';
  } else {
    descCountLabel.textContent = `${len} karakter (memenuhi syarat)`;
    descCountLabel.style.color = '#15803d';
    descError.textContent = '';
  }
}
descInput.addEventListener('input', updateDescCounter);
updateDescCounter();

// ---------- Edit Mode Detection ----------
const urlParams = new URLSearchParams(location.search);
const editId = urlParams.get('edit');
let editingProject = null;

const form = document.getElementById('add-project-form');
const nameInput = document.getElementById('project-name');
const nameError = document.getElementById('name-error');
const bannerTitle = document.querySelector('.banner-title');
const btnUpload = document.getElementById('btn-upload');

if (editId) {
  try {
    const list = JSON.parse(localStorage.getItem('projekadita_projects') || '[]');
    editingProject = list.find(p => String(p.id) === String(editId));

    if (editingProject) {
      document.title = 'Edit Proyek - ProjekAdita';
      if (bannerTitle) bannerTitle.textContent = 'Edit Pengajuan Proyek';
      if (btnUpload) {
        btnUpload.innerHTML = '<span class="upload-arrow" aria-hidden="true">✓</span> Simpan Perubahan';
      }

      // Populate form values
      nameInput.value = editingProject.title || '';
      descInput.value = editingProject.description || '';
      document.getElementById('project-requirements').value = editingProject.requirements || '';
      if (editingProject.selection_method) {
        document.getElementById('selection-method').value = editingProject.selection_method;
      }

      // Populate tags
      clearAllTags();
      if (Array.isArray(editingProject.tags)) {
        editingProject.tags.forEach(t => addTagElement(t));
      }

      // Populate thumbnail if exists
      if (editingProject.thumbnail) {
        renderThumbnailPreview(editingProject.thumbnail);
      }

      updateDescCounter();
      notify(`Mode edit: Anda sedang memperbarui proyek "${editingProject.title}".`, 'info');
    }
  } catch (err) {
    console.warn('Gagal memuat data proyek untuk edit:', err);
  }
}

// ---------- Save Draft ----------
const btnDraft = document.getElementById('btn-draft');
btnDraft.addEventListener('click', () => {
  const tags = [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag);
  const draft = {
    title: form.title.value.trim(),
    description: form.description.value.trim(),
    requirements: form.requirements.value.trim(),
    selection_method: form.selection_method.value,
    tags,
    thumbnail: thumbnailDataUrl,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem('projekadita_draft', JSON.stringify(draft));
  notify('Draf proyek berhasil disimpan.', 'info');
});

// ---------- Form Submit (Upload / Update) ----------
form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const title = nameInput.value.trim();
  const description = descInput.value.trim();
  const requirements = document.getElementById('project-requirements').value.trim();
  const selectionMethod = document.getElementById('selection-method').value;
  const tags = [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag);

  let hasError = false;

  if (!title) {
    nameError.textContent = 'Nama proyek tidak boleh kosong.';
    nameInput.focus();
    hasError = true;
  } else {
    nameError.textContent = '';
  }

  if (description.length < 50) {
    descError.textContent = 'Deskripsi proyek minimal harus 50 karakter.';
    if (!hasError) descInput.focus();
    hasError = true;
  } else {
    descError.textContent = '';
  }

  if (hasError) return;

  try {
    const list = JSON.parse(localStorage.getItem('projekadita_projects') || '[]');

    if (editingProject) {
      // Update existing project
      const index = list.findIndex(p => String(p.id) === String(editingProject.id));
      const updatedProject = {
        ...editingProject,
        title,
        description,
        requirements,
        selection_method: selectionMethod,
        tags,
        thumbnail: thumbnailDataUrl !== null ? thumbnailDataUrl : editingProject.thumbnail,
        status: 'pending', // Tetap pending karena admin belum ada yang approve
        updatedAt: new Date().toISOString()
      };

      if (index !== -1) {
        list[index] = updatedProject;
      } else {
        list.unshift(updatedProject);
      }

      localStorage.setItem('projekadita_projects', JSON.stringify(list));
      notify('Perubahan proyek berhasil disimpan! Mengalihkan ke Projek Saya...', 'info');
    } else {
      // Create new project
      const projectData = {
        id: Date.now(),
        title,
        category: 'Riset Terapan',
        description,
        requirements,
        selection_method: selectionMethod,
        status: 'pending', // Semua projek yang dibuat masih pending karena belum ada admin
        members: '1/4 Anggota Terisi',
        members_count: 1,
        max_members: 4,
        tags,
        thumbnail: thumbnailDataUrl,
        timeAgo: 'Baru saja',
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString()
      };

      list.unshift(projectData);
      localStorage.setItem('projekadita_projects', JSON.stringify(list));
      localStorage.removeItem('projekadita_draft');
      notify('Proyek berhasil diajukan dan sedang menunggu persetujuan admin (pending). Mengalihkan ke Projek Saya...', 'info');
    }

    setTimeout(() => {
      location.replace('/projek-saya');
    }, 1200);
  } catch (err) {
    console.warn('Gagal menyimpan ke localStorage:', err);
    notify('Terjadi kesalahan saat menyimpan proyek.', 'error');
  }
});
