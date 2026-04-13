/**
 * Origen públic (API o Socket) quan el .env apunta a localhost/127.0.0.1 però el navegador
 * és a un altre host (producció darrere Nginx al 80/443).
 *
 * @param {string} [configuredBase]
 * @param {string} devFallback URL per defecte en desenvolupament
 */
export function resolvePublicOrigin (configuredBase, devFallback) {
  let base = (configuredBase || devFallback).replace(/\/$/, '');
  if (typeof window === 'undefined' || !window.location) {
    return base;
  }
  const h = window.location.hostname;
  if (!h || h === 'localhost' || h === '127.0.0.1') {
    return base;
  }
  if (base.indexOf('localhost') === -1 && base.indexOf('127.0.0.1') === -1) {
    return base;
  }
  try {
    const u = new URL(base);
    u.hostname = h;
    u.port = '';
    return u.origin;
  } catch {
    return base;
  }
}

/**
 * URL base de l'API Laravel (NUXT_PUBLIC_API_URL).
 */
export function resolvePublicApiBaseUrl (configuredBase) {
  return resolvePublicOrigin(configuredBase, 'http://localhost:8000');
}

/**
 * URL base del Socket.IO (NUXT_PUBLIC_SOCKET_URL).
 */
export function resolvePublicSocketUrl (configuredBase) {
  return resolvePublicOrigin(configuredBase, 'http://localhost:3001');
}
