import { el } from './dom.js';
import { openForm } from './dialog.js';

export function chip(text, tone = '') {
  let className = 'chip';
  if (tone) {
    className = `chip chip--${tone}`;
  }
  return el('span', { class: className, text });
}

const PROJECT_STATUS = {
  draft: ['Draf', ''],
  pending_moderation: ['Menunggu moderator', 'orange'],
  pending_admin: ['Menunggu admin', 'orange'],
  published: ['Terbit', 'green'],
  rejected: ['Ditolak', 'red'],
};

export function projectStatusChip(status) {
  const known = PROJECT_STATUS[status];
  if (!known) {
    return chip(status);
  }
  return chip(known[0], known[1]);
}

const ROLE_LABEL = { owner: 'Pemilik', manager: 'Manajer', member: 'Anggota', user: 'Pengguna', moderator: 'Moderator', admin: 'Admin' };

export function roleChip(role) {
  return chip(ROLE_LABEL[role] || role);
}

/** Unix seconds -> "7 Nov 2026". */
export function dateText(unixSeconds) {
  if (!unixSeconds) {
    return '-';
  }
  return new Date(unixSeconds * 1000).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function emptyNote(text) {
  return el('p', { class: 'empty-note', text });
}

function shorten(text, max = 180) {
  const clean = text || '';
  if (clean.length <= max) {
    return clean;
  }
  return `${clean.slice(0, max)}...`;
}

/** One row of a project list: title (a link to the project page), status, owner, tags, and any extra nodes (buttons). */
export function projectItem(project, ...extra) {
  const meta = el('div', { class: 'item-meta' }, projectStatusChip(project.status));
  if (project.owner) {
    meta.append(`oleh ${project.owner.name}`);
  }
  if (project.members_count !== undefined) {
    meta.append(`${project.members_count} anggota`);
  }

  const tags = el('div', { class: 'item-meta' });
  for (const tag of project.tags || []) {
    tags.append(chip(`#${tag}`));
  }

  return el('article', { class: 'item' },
    el('h2', { class: 'item-title' }, el('a', { href: `/project?id=${project.id}`, text: project.title })),
    meta,
    el('p', { class: 'item-text', text: shorten(project.description) }),
    tags,
    ...extra
  );
}

/**
 * Buttons that call the API and then reload the page.
 * notify(text, kind) shows a message; reload() redraws the page.
 */
export function makeActions(notify, reload) {
  async function run(work, successText) {
    try {
      await work();
    } catch (err) {
      notify(err.message, 'error');
      return;
    }
    await reload();
    if (successText) {
      notify(successText);
    }
  }

  /** A button that runs work(); confirmText (optional) asks "are you sure" first. */
  function actionButton(label, className, work, successText, confirmText) {
    const button = el('button', { class: className, type: 'button', text: label });
    button.addEventListener('click', async () => {
      if (confirmText && !confirm(confirmText)) {
        return;
      }
      await run(work, successText);
    });
    return button;
  }

  /** A button that opens a form dialog (see openForm); on success the page reloads. */
  function formButton(label, className, formOptions, successText) {
    const button = el('button', { class: className, type: 'button', text: label });
    button.addEventListener('click', async () => {
      const saved = await openForm(formOptions);
      if (!saved) {
        return;
      }
      await reload();
      if (successText) {
        notify(successText);
      }
    });
    return button;
  }

  return { actionButton, formButton };
}
