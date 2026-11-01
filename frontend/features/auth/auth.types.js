/**
 * @typedef {Object} User
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {'user'|'moderator'|'admin'} role
 */

/**
 * API envelope; http.js unwraps it and throws on success:false.
 * @typedef {Object} ApiEnvelope
 * @property {boolean} success
 * @property {string} [message]
 * @property {*} [data]
 * @property {Object<string,string>} [errors] field -> message (422)
 */

export {};
