import { get, post, del } from '../../shared/lib/http.js';

export const applyToProject = (projectId, desiredTitle, message) =>
  post(`/projects/${projectId}/applications`, { desired_title: desiredTitle, message });

/** My own applications, each with its project and an outcome (pending, accepted, declined, banned, withdrawn). */
export const myApplications = async () => (await get('/applications/mine')).applications;

/** Withdraw an application that is still pending. */
export const withdrawApplication = (applicationId) => del(`/applications/${applicationId}`);

/** For owner and manager. status: 'pending' or 'all'. */
export const projectApplications = async (projectId, status = 'pending') =>
  (await get(`/projects/${projectId}/applications?status=${status}`)).applications;

export const acceptApplication = (applicationId) => post(`/applications/${applicationId}/accept`);

/** ban false: a plain decline (may apply again). ban true: blocked from this project. */
export const rejectApplication = (applicationId, ban) => post(`/applications/${applicationId}/reject`, { ban });
