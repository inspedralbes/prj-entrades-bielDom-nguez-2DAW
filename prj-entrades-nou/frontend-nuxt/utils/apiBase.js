/**
 * URL base pública (navegador): si cal, substituïm el hostname per coincidir amb la finestra (mòbil / LAN).
 *
 * @param {string} [configuredBase]
 * @param {string} defaultBase  Ex. http://localhost:8000 o http://localhost:3001
 * @returns {string}
 */
function resolvePublicOrigin (configuredBase, defaultBase) {
  let base = (configuredBase || defaultBase).replace(/\/$/, '');
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
    /* Conservar el port de la URL configurada (p. ex. :8000); buidar-lo trencava API al mateix host que Nuxt (:3000). */
    return u.origin;
  } catch {
    return base;
  }
}

/**
 * URL base de l'API Laravel (NUXT_PUBLIC_API_URL).
 * - Navegador: si cal, substituïm el hostname per coincidir amb la finestra (mòbil / LAN).
 * - SSR (Node): opcionalment NUXT_API_INTERNAL_URL (p. ex. http://backend-api:8000 a Docker);
 *   dins el contenidor «localhost:8000» no apunta al servei Laravel.
 */
export function resolveApiBaseUrlForFetch (runtimeConfig) {
  if (import.meta.server) {
    const internal = runtimeConfig.apiInternalUrl;
    if (typeof internal === 'string') {
      const t = internal.trim();
      if (t.length > 0) {
        return t.replace(/\/$/, '');
      }
    }
  }
  return resolvePublicApiBaseUrl(runtimeConfig.public.apiUrl);
}

export function resolvePublicApiBaseUrl (configuredBase) {
  return resolvePublicOrigin(configuredBase, 'http://localhost:8000');
}

/**
 * URL base del Socket.IO (NUXT_PUBLIC_SOCKET_URL).
 */
export function resolvePublicSocketUrl (configuredBase) {
  return resolvePublicOrigin(configuredBase, 'http://localhost:3001');
}
