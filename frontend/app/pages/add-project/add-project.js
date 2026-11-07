import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { el, setMessage } from '../../../shared/helpers/dom.js';
import { mountShell } from '../../layouts/shell.js';
import { fetchTags, getProject, getDraft, saveDraft, createProject, updateProject } from '../../../features/projects/projects.api.js';

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
  notify(err.message, 'error');
}

// The official tags come from the backend (the tag picker only offers these).
let ALL_MASTER_TAGS = [];
try {
  ALL_MASTER_TAGS = await fetchTags();
} catch (err) {
  notify(err.message, 'error');
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
  const headerEl = el('div', { class: 'tags-dropdown-header' },
    el('span', {}, 'Ditemukan ', el('strong', { class: 'tags-dropdown-count', text: String(matched.length) }), ' tag resmi'),
    el('span', { class: 'tags-dropdown-hint', text: 'Gulir untuk melihat semua ↓' })
  );
  tagsDropdown.appendChild(headerEl);

  // Tampilkan SEMUA tag yang cocok (tanpa limit slice) sehingga seluruh elemen terlihat
  matched.forEach((item, index) => {
    const option = document.createElement('div');
    option.className = `tags-dropdown-item ${index === 0 ? 'is-selected' : ''}`;
    option.setAttribute('role', 'option');

    // Highlight the typed part of the name. Every match includes the query, so indexOf finds it.
    const start = item.name.toLowerCase().indexOf(cleanQ);
    const end = start + cleanQ.length;
    option.append(
      el('span', { class: 'dropdown-tag-name' },
        '#',
        item.name.slice(0, start),
        el('mark', { text: item.name.slice(start, end) }),
        item.name.slice(end)
      ),
      el('span', { class: 'dropdown-tag-category', text: item.category })
    );

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

// ---------- Edit Mode / Draft ----------
const urlParams = new URLSearchParams(location.search);
const editId = urlParams.get('edit');
let editingProject = null;

const form = document.getElementById('add-project-form');
const nameInput = document.getElementById('project-name');
const nameError = document.getElementById('name-error');
const bannerTitle = document.querySelector('.banner-title');
const btnUpload = document.getElementById('btn-upload');

function fillForm(project) {
  nameInput.value = project.title || '';
  descInput.value = project.description || '';
  document.getElementById('project-requirements').value = project.requirements || '';
  if (project.selection_method) {
    document.getElementById('selection-method').value = project.selection_method;
  }
  clearAllTags();
  (project.tags || []).forEach(t => addTagElement(t));
  if (project.thumbnail) {
    renderThumbnailPreview(project.thumbnail);
  }
  updateDescCounter();
}

if (editId) {
  try {
    editingProject = await getProject(editId);
    document.title = 'Edit Proyek - ProjekAdita';
    if (bannerTitle) bannerTitle.textContent = 'Edit Pengajuan Proyek';
    if (btnUpload) {
      btnUpload.replaceChildren(el('span', { class: 'upload-arrow', 'aria-hidden': 'true', text: '✓' }), ' Simpan Perubahan');
    }
    fillForm(editingProject);
    notify(`Mode edit: Anda sedang memperbarui proyek "${editingProject.title}".`, 'info');
  } catch (err) {
    // Not mine, or gone: do not let the form turn into "create a new project" by accident.
    btnUpload.disabled = true;
    notify(err.message, 'error');
  }
} else {
  // My saved draft (if any) comes back into the form; submitting a project uses it up.
  try {
    const draft = await getDraft();
    if (draft) {
      fillForm(draft);
      notify('Draf proyek dimuat. Lanjutkan mengisi lalu ajukan.', 'info');
    }
  } catch (err) {
    notify(err.message, 'error');
  }
}

// What the backend gets. A new image goes up as a data URL; the URL of an image the backend already
// stores is sent back as it is, which means "keep it" (and, for a draft's image, "use it for this project").
function formPayload() {
  const payload = {
    title: nameInput.value.trim(),
    description: descInput.value.trim(),
    requirements: document.getElementById('project-requirements').value.trim(),
    selection_method: document.getElementById('selection-method').value,
    tags: [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag),
  };
  if (thumbnailDataUrl) payload.thumbnail = thumbnailDataUrl;
  return payload;
}

// 422 answers carry {field: message}; the two fields with their own spot on the form get it there.
function showError(err) {
  const fields = err.errors || {};
  nameError.textContent = fields.title || '';
  descError.textContent = fields.description || '';
  notify(fields.tags || fields.thumbnail || fields.requirements || fields.selection_method || err.message, 'error');
}

// ---------- Save Draft ----------
const btnDraft = document.getElementById('btn-draft');
btnDraft.addEventListener('click', async () => {
  try {
    await saveDraft(formPayload());
    notify('Draf proyek berhasil disimpan.', 'info');
  } catch (err) {
    showError(err);
  }
});

// ---------- Form Submit (Upload / Update) ----------
form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const title = nameInput.value.trim();
  const description = descInput.value.trim();

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

  btnUpload.disabled = true;
  try {
    if (editingProject) {
      await updateProject(editingProject.id, formPayload());
      notify('Perubahan proyek berhasil disimpan! Mengalihkan ke Projek Saya...', 'info');
    } else {
      await createProject(formPayload());
      notify('Proyek berhasil diajukan dan sedang menunggu persetujuan admin (pending). Mengalihkan ke Projek Saya...', 'info');
    }
    setTimeout(() => {
      location.replace('/projek-saya');
    }, 1200);
  } catch (err) {
    showError(err);
    btnUpload.disabled = false;
  }
});
