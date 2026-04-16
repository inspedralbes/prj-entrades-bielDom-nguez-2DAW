//================================ IMPORTS ============
// (Sense imports; navegació segura en 403 lògics.)

/**
 * Ruta segura quan l’usuari autentificat no té permís per a `to`:
 * tornar a la vista anterior (from) si és vàlida; si no, fallback (per defecte «/»).
 * Evita redirigir a una altra URL dins el mateix àmbit protegit (p. ex. /admin/*).
 */
export function getForbiddenRedirectPath (to, from, blockedPathPrefix) {
  const fallback = '/';
  if (!from) {
    return fallback;
  }
  if (typeof from.fullPath !== 'string') {
    return fallback;
  }
  if (from.fullPath === to.fullPath) {
    return fallback;
  }
  if (typeof from.path === 'string' && from.path.indexOf(blockedPathPrefix) === 0) {
    return fallback;
  }
  return from.fullPath;
}
