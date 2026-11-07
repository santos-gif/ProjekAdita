import { get, post } from '../../shared/lib/http.js';

/**
 * Published projects. query: {q, tags, selection_method, open, study_program, sort, page, per_page}
 * @returns {Promise<{projects: object[], total: number, page: number, per_page: number}>}
 */
export const searchProjects = (query) => get(`/projects?${new URLSearchParams(query)}`);

/** @returns {Promise<{project: object, members: object[]}>} project has owner, my_project_role, my_application, can_apply */
export const getProjectDetail = (id) => get(`/projects/${id}`);

/** Projects where I am manager or member. */
export const listJoined = async () => (await get('/projects/joined')).projects;

/** Someone's public profile, with their published projects. */
export const getPublicProfile = async (id) => (await get(`/users/${id}`)).profile;

export const reportProject = (id, reason) => post(`/projects/${id}/reports`, { reason });
