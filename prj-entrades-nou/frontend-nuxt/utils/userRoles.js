/**
 * Normalitza una entrada de rol (string o objecte Spatie { name }) a nom de rol.
 * @param {unknown} entry
 * @returns {string}
 */
function roleNameFromEntry (entry) {
  if (entry === null || entry === undefined) {
    return '';
  }
  if (typeof entry === 'string') {
    return entry;
  }
  if (typeof entry === 'object' && entry !== null && typeof entry.name === 'string') {
    return entry.name;
  }
  return '';
}

/**
 * @param {unknown} roles
 * @param {string} roleName
 * @returns {boolean}
 */
function rolesIncludeNamedRole (roles, roleName) {
  if (!roles || !Array.isArray(roles)) {
    return false;
  }
  let i = 0;
  for (; i < roles.length; i++) {
    if (roleNameFromEntry(roles[i]) === roleName) {
      return true;
    }
  }
  return false;
}

/**
 * @param {unknown} roles
 * @returns {boolean}
 */
export function rolesIncludeAdmin (roles) {
  return rolesIncludeNamedRole(roles, 'admin');
}

/**
 * @param {unknown} roles
 * @returns {boolean}
 */
export function rolesIncludeValidator (roles) {
  return rolesIncludeNamedRole(roles, 'validator');
}
