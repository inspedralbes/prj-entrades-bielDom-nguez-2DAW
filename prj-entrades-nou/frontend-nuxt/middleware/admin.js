import { useAuthStore } from '~/stores/auth';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

/**
 * Comprova si la llista de rols (p. ex. des de /api/auth/me) inclou «admin».
 */
function rolesIncludeAdmin (roles) {
  if (!roles || !Array.isArray(roles)) {
    return false;
  }
  for (let i = 0; i < roles.length; i++) {
    if (roles[i] === 'admin') {
      return true;
    }
  }
  return false;
}

/**
 * Només rols amb «admin» (Spatie) poden accedir a /admin/*.
 * Després del login cal tornar a la mateixa ruta: mateix patró que `authGate` (query `redirect`).
 * Si el Pinia ja té `user.roles` des de `auth-session` o login, no cal repetir GET /api/auth/me a cada canvi de pàgina (navegació client ràpida).
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) {
    return;
  }
  const auth = useAuthStore();
  if (!auth.token) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }
  if (auth.user && rolesIncludeAdmin(auth.user.roles)) {
    return;
  }
  const config = useRuntimeConfig();
  const base = resolvePublicApiBaseUrl(config.public.apiUrl);
  try {
    const me = await $fetch(`${base}/api/auth/me`, {
      headers: { Authorization: `Bearer ${auth.token}` },
      timeout: 20000,
    });
    auth.setSession({ token: auth.token, user: me });
    const roles = me.roles || [];
    if (!rolesIncludeAdmin(roles)) {
      return navigateTo('/');
    }
  } catch {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }
});
