import { get, post } from '../../shared/lib/http.js';

/** First-stage queue (moderator, admin), oldest first. */
export const moderationQueue = async () => (await get('/moderation/projects')).projects;

/** Final queue (admin only). status: 'pending_admin' or 'pending_moderation'. */
export const adminQueue = async (status = 'pending_admin') => (await get(`/admin/projects?status=${status}`)).projects;

/** @returns {Promise<{project: object, reviews: object[]}>} the project with its review history */
export const moderationDetail = (id) => get(`/moderation/projects/${id}`);
export const adminDetail = (id) => get(`/admin/projects/${id}`);

/** My own review decisions. */
export const moderationHistory = async () => (await get('/moderation/history')).reviews;

/** approve: true sends it to the admin; false rejects it and needs a reason. */
export const reviewAsModerator = (id, approve, reason) => post(`/moderation/projects/${id}/review`, { approve, reason });

/** approve: true publishes (only from pending_admin); false rejects from either stage and needs a reason. */
export const reviewAsAdmin = (id, approve, reason) => post(`/admin/projects/${id}/review`, { approve, reason });

/** Admin: take a published project down (becomes rejected). */
export const takedownProject = (id, reason) => post(`/admin/projects/${id}/takedown`, { reason });

/** status: open, forwarded, dismissed, actioned or all */
export const moderationReports = async (status) => (await get(`/moderation/reports?status=${status}`)).reports;
export const adminReports = async (status = 'forwarded') => (await get(`/admin/reports?status=${status}`)).reports;

/** action: 'dismiss' or 'forward'; note is optional. */
export const resolveAsModerator = (reportId, action, note) => post(`/moderation/reports/${reportId}/resolve`, { action, note });
export const resolveAsAdmin = (reportId, note) => post(`/admin/reports/${reportId}/resolve`, { action: 'dismiss', note });
