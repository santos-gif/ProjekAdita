import { get, patch } from '../../shared/lib/http.js';

/** query: {q, role, page, per_page} @returns {Promise<{users: object[], total: number, page: number, per_page: number}>} */
export const listUsers = (query) => get(`/admin/users?${new URLSearchParams(query)}`);

/** role: user, moderator or admin. Not your own role, and never the last admin. */
export const setUserRole = (userId, role) => patch(`/admin/users/${userId}/role`, { role });

export const getStats = () => get('/admin/stats');
