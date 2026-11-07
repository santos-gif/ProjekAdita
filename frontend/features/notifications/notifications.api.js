import { get, post } from '../../shared/lib/http.js';

/** @returns {Promise<{notifications: object[], unread_count: number}>} newest first */
export const listNotifications = (unreadOnly) => {
  if (unreadOnly) {
    return get('/notifications?unread=1&limit=50');
  }
  return get('/notifications?limit=50');
};

/** @returns {Promise<number>} how many notifications are still unread (the list itself is not sent) */
export const getUnreadCount = async () => {
  const data = await get('/notifications?unread=1&limit=1');
  return data.unread_count;
};

export const markRead = (id) => post(`/notifications/${id}/read`);

export const markAllRead = () => post('/notifications/read-all');
