import { get, post, put, patch, del } from '../../shared/lib/http.js';

/**
 * @typedef {Object} Project  what the pages work with (toUi() builds it from the API's project)
 * @property {number} id
 * @property {string} title
 * @property {string} description
 * @property {string} requirements
 * @property {string} selection_method
 * @property {string[]} tags
 * @property {string|null} thumbnail  URL of the stored image, e.g. /uploads/ab12.png
 * @property {'pending'|'approved'|'rejected'|'draft'} status  what the pages know; backendStatus has the real one
 * @property {string} backendStatus   draft | pending_moderation | pending_admin | published | rejected
 * @property {string} members         e.g. "1 Anggota"
 * @property {string} timeAgo         e.g. "Diajukan kemarin"
 */

/** The review steps are two "pending" states for the owner; the pages only know pending / approved. */
const UI_STATUS = { pending_moderation: 'pending', pending_admin: 'pending', published: 'approved' };

function timeAgo(createdAt, updatedAt) {
  const edited = updatedAt - createdAt > 60;
  const prefix = edited ? 'Diperbarui' : 'Diajukan';
  const seconds = Date.now() / 1000 - (edited ? updatedAt : createdAt);
  if (seconds < 3600) return 'Baru saja';
  if (seconds < 86400) return `${prefix} ${Math.floor(seconds / 3600)} jam lalu`;
  const days = Math.floor(seconds / 86400);
  return days === 1 ? `${prefix} kemarin` : `${prefix} ${days} hari lalu`;
}

/** API project -> the shape the pages already render. */
function toUi(p) {
  return {
    id: p.id,
    title: p.title,
    description: p.description,
    requirements: p.requirements,
    selection_method: p.selection_method,
    tags: p.tags,
    thumbnail: p.thumbnail,
    status: UI_STATUS[p.status] ?? p.status,
    backendStatus: p.status,
    rejectionReason: p.rejection_reason,
    members_count: p.members_count,
    members: `${p.members_count} Anggota`,
    timeAgo: timeAgo(p.created_at, p.updated_at),
  };
}

/** @returns {Promise<{name:string, category:string}[]>} the official tags */
export const fetchTags = async () => (await get('/tags')).tags;

/** @returns {Promise<Project[]>} all of my submitted projects, newest first (the draft is not one of them) */
export const listMine = async () => (await get('/projects/mine')).projects.map(toUi);

/** @returns {Promise<Project>} */
export const getProject = async (id) => toUi((await get(`/projects/${id}`)).project);

/** @returns {Promise<Project>} the payload is {title, description, requirements, selection_method, tags, thumbnail?} */
export const createProject = async (payload) => toUi((await post('/projects', payload)).project);

/** @returns {Promise<Project>} */
export const updateProject = async (id, payload) => toUi((await patch(`/projects/${id}`, payload)).project);

/** Withdraws a submission that is still pending. */
export const deleteProject = (id) => del(`/projects/${id}`);

/** @returns {Promise<Project|null>} my saved form, if I have one */
export const getDraft = async () => {
  const { project } = await get('/projects/draft');
  return project ? toUi(project) : null;
};

/** @returns {Promise<Project>} */
export const saveDraft = async (payload) => toUi((await put('/projects/draft', payload)).project);
