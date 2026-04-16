//================================ IMPORTS ============

import { useAuthStore } from '~/stores/auth';

/**
 * Desa la ruta volguda quan es redirigeix cap al login (reserva si es perd el query ?redirect=).
 */
export function storeAuthIntendedPath (to) {
  if (!to || !import.meta.client) {
    return;
  }
  try {
    sessionStorage.setItem('auth_intended_path', to.fullPath);
  } catch {
    /* sense sessionStorage */
  }
}

/**
 * Comprovació d’auth alineada amb cookie `auth_token` (SSR + client).
 * Cridar des de middleware Nuxt.
 */
export function enforceAuthCookie (to) {
  const auth = useAuthStore();
  auth.init();
  const authToken = useCookie('auth_token');
  const raw = authToken.value;
  const cookieTok = typeof raw === 'string' ? raw.trim() : raw;
  if (cookieTok && cookieTok.length > 0) {
    return;
  }
  /* Just després de registre/login: Pinia ja té token (setSession) i la cookie pot no haver fet flush encara. */
  const storeTok = auth.token ? String(auth.token).trim() : '';
  if (storeTok.length > 0) {
    return;
  }
  storeAuthIntendedPath(to);
  return navigateTo({
    path: '/login',
    query: { redirect: to.fullPath },
  });
}
