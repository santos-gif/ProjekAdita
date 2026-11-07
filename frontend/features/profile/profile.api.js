import { get, patch, post } from '../../shared/lib/http.js';

/** @returns {Promise<object>} my own profile, with the private fields (email, role, identity_number, phone) */
export const getMyProfile = async () => (await get('/users/me')).profile;

/**
 * Only the keys that are sent change; "" clears an optional field.
 * @returns {Promise<object>} the saved profile
 */
export const saveMyProfile = async (changes) => (await patch('/users/me', changes)).profile;

/** The other devices are logged out; this one stays logged in. */
export const changePassword = (currentPassword, newPassword, confirmNewPassword) =>
  post('/users/me/password', { currentPassword, newPassword, confirmNewPassword });
