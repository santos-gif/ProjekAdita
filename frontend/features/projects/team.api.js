import { get, post, patch, del } from '../../shared/lib/http.js';

/** Owner: close or reopen the project for NEW applications. */
export const setApplicationsOpen = (projectId, open) => patch(`/projects/${projectId}/applications-open`, { open });

/** Owner: role is 'manager' or 'member'; title is the position name. */
export const changeMember = (projectId, userId, role, title) =>
  patch(`/projects/${projectId}/members/${userId}`, { role, title });

export const removeMember = (projectId, userId) => del(`/projects/${projectId}/members/${userId}`);

/** Manager: ask the owner to make a plain member a manager. */
export const requestPromotion = (projectId, userId) => post(`/projects/${projectId}/members/${userId}/promotion-requests`);

/** Owner: the pending promotion requests. */
export const listPromotions = async (projectId) => (await get(`/projects/${projectId}/promotion-requests`)).requests;

export const decidePromotion = (requestId, approve) => {
  if (approve) {
    return post(`/promotion-requests/${requestId}/approve`);
  }
  return post(`/promotion-requests/${requestId}/deny`);
};

/** Manager: back to plain member. */
export const stepDown = (projectId) => post(`/projects/${projectId}/step-down`);

/** Manager or member leaves. The owner must transfer ownership first. */
export const leaveProject = (projectId) => post(`/projects/${projectId}/leave`);

/** Owner: hand the project to a current member; the old owner becomes a manager. */
export const transferOwnership = (projectId, userId) => post(`/projects/${projectId}/transfer-ownership`, { user_id: userId });
