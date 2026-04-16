//================================ IMPORTS ============
// (Sense imports; utilitats d’URL públiques API/socket.)

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
 * Si la finestra és HTTPS i la URL encara és http, passa a https (Socket.IO farà WSS; evita Mixed Content).
 * El servidor ha d’oferir TLS al mateix host/port (p. ex. nginx reverse proxy) o la connexió fallarà després.
 */
function alignUrlProtocolWithSecurePage (baseUrl) {
  if (typeof window === 'undefined' || !window.location) {
    return baseUrl;
  }
  if (window.location.protocol !== 'https:') {
    return baseUrl;
  }
  if (typeof baseUrl !== 'string' || baseUrl.length === 0) {
    return baseUrl;
  }
  if (baseUrl.indexOf('http://') !== 0) {
    return baseUrl;
  }
  return 'https://' + baseUrl.slice('http://'.length);
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
  const r = resolvePublicOrigin(configuredBase, 'http://localhost:8000');
  return alignUrlProtocolWithSecurePage(r);
}

/**
 * Docker / build sovint defineixen `NUXT_PUBLIC_SOCKET_URL=http://localhost:3001`.
 * `resolvePublicOrigin` substitueix el hostname però **conserva el port 3001** → al navegador queda
 * `https://el-teu-domini:3001` i el WSS falla (el certificat és al 443, no al 3001).
 * Si la finestra és al mateix host en 80/443 i la URL resolta porta :3001, usem `window.location.origin`
 * (Nginx ha de fer proxy de `/socket.io/` al Node a 127.0.0.1:3001).
 *
 * @param {string} resolvedBase
 * @returns {string}
 */
export function rewriteSocketUrlWhenProxiedSameHost (resolvedBase) {
  if (typeof window === 'undefined' || !window.location) {
    return resolvedBase;
  }
  if (typeof resolvedBase !== 'string' || resolvedBase.length === 0) {
    return resolvedBase;
  }
  try {
    const u = new URL(resolvedBase);
    const loc = window.location;
    if (u.hostname !== loc.hostname) {
      return resolvedBase;
    }
    if (u.port !== '3001') {
      return resolvedBase;
    }
    let locPort = loc.port;
    if (locPort === '') {
      if (loc.protocol === 'https:') {
        locPort = '443';
      } else {
        locPort = '80';
      }
    }
    if (locPort === '443' || locPort === '80') {
      return loc.origin;
    }
  } catch {
    return resolvedBase;
  }
  return resolvedBase;
}

/**
 * URL base del Socket.IO (NUXT_PUBLIC_SOCKET_URL).
 */
export function resolvePublicSocketUrl (configuredBase) {
  const r = resolvePublicOrigin(configuredBase, 'http://localhost:3001');
  const aligned = alignUrlProtocolWithSecurePage(r);
  if (import.meta.client) {
    return rewriteSocketUrlWhenProxiedSameHost(aligned);
  }
  return aligned;
}
