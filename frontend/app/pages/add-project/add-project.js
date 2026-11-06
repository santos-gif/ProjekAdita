import { logout } from '../../../features/auth/auth.api.js';
import { requireUser } from '../../../features/auth/auth.session.js';
import { setMessage } from '../../../shared/helpers/dom.js';
import { mountShell } from '../../layouts/shell.js';

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
      active: 'add-project',
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
    active: 'add-project',
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

thumbnailInput.addEventListener('change', async () => {
  const file = thumbnailInput.files?.[0];
  if (!file) return;

  if (file.size > 2 * 1024 * 1024) {
    notify('Ukuran file melebihi 2MB. Silakan pilih gambar yang lebih kecil.', 'error');
    thumbnailInput.value = '';
    return;
  }

  // Read base64 data for storage/upload
  const reader = new FileReader();
  reader.onload = (e) => {
    thumbnailDataUrl = e.target.result;
  };
  reader.readAsDataURL(file);

  // Render preview safely using Canvas (bypasses CSP restrictions on data: URLs)
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
});

// ---------- Tags Handling (All Gray) ----------
const tagsBox = document.getElementById('tags-box');
const tagInput = document.getElementById('tag-input');

function createTagElement(tagName) {
  const cleanTag = tagName.replace(/^#/, '').trim();
  if (!cleanTag) return;

  // Check duplicate
  const existingTags = [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag.toLowerCase());
  if (existingTags.includes(cleanTag.toLowerCase())) {
    tagInput.value = '';
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
}

// Bind existing remove buttons
tagsBox.querySelectorAll('.tag-remove').forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    btn.closest('.tag-pill')?.remove();
  });
});

tagInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    createTagElement(tagInput.value);
  } else if (e.key === 'Backspace' && !tagInput.value) {
    const pills = tagsBox.querySelectorAll('.tag-pill');
    if (pills.length > 0) {
      pills[pills.length - 1].remove();
    }
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

// ---------- Save Draft ----------
const btnDraft = document.getElementById('btn-draft');
btnDraft.addEventListener('click', () => {
  const form = document.getElementById('add-project-form');
  const tags = [...tagsBox.querySelectorAll('.tag-pill')].map(p => p.dataset.tag);
  const draft = {
    title: form.title.value.trim(),
    description: form.description.value.trim(),
    requirements: form.requirements.value.trim(),
    selection_method: form.selection_method.value,
    max_members: form.max_members.value,
    tags,
    thumbnail: thumbnailDataUrl,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem('projekadita_draft', JSON.stringify(draft));
  notify('Draf proyek berhasil disimpan.', 'info');
});

// Load draft if available
try {
  const savedDraft = localStorage.getItem('projekadita_draft');
  if (savedDraft) {
    const d = JSON.parse(savedDraft);
    if (d.savedAt) {
      // Draft exists
    }
  }
} catch { /* ignore */ }

// ---------- Form Submit (Upload) ----------
const form = document.getElementById('add-project-form');
const nameInput = document.getElementById('project-name');
const nameError = document.getElementById('name-error');

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  const title = nameInput.value.trim();
  const description = descInput.value.trim();
  const requirements = document.getElementById('project-requirements').value.trim();
  const selectionMethod = document.getElementById('selection-method').value;
  const maxMembers = parseInt(document.getElementById('quota-input').value, 10) || 4;
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

  const projectData = {
    id: Date.now(),
    title,
    description,
    requirements,
    selection_method: selectionMethod,
    max_members: maxMembers,
    tags,
    thumbnail: thumbnailDataUrl,
    createdAt: new Date().toISOString()
  };

  // Save to localStorage list of projects
  try {
    const list = JSON.parse(localStorage.getItem('projekadita_projects') || '[]');
    list.unshift(projectData);
    localStorage.setItem('projekadita_projects', JSON.stringify(list));
    localStorage.removeItem('projekadita_draft');
  } catch (err) {
    console.warn('Could not save to localStorage:', err);
  }

  notify('Proyek berhasil diupload dan dipublikasikan! Mengalihkan ke dashboard...', 'info');

  setTimeout(() => {
    location.replace('/dashboard');
  }, 1200);
});
