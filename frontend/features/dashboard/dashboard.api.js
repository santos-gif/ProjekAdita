import { get } from '../../shared/lib/http.js';

/** @returns {Promise<{user: object, student: object, moderator: object|null, admin: object|null}>} counts, by account role */
export const getDashboard = () => get('/dashboard');
