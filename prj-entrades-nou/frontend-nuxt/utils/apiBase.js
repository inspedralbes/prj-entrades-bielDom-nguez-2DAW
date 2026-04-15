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
  let base = (configuredBase || 'http://localhost:8000').replace(/\/$/, '');
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
    return u.origin;
  } catch {
    return base;
  }
}
