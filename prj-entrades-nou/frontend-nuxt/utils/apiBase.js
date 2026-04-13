/**
 * URL base de l'API Laravel (NUXT_PUBLIC_API_URL).
 * Si el navegador és a un altre host que localhost (mòbil, xarxa local) però l'API està configurada
 * com a http://localhost:8000, les peticions no arriben — substituïm el hostname per coincidir amb la finestra.
 */
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
