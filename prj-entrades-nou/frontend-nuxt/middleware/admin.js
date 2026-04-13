import { useAuthStore } from '~/stores/auth';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

/**
 * Només rols amb «admin» (Spatie) poden accedir a /admin/*.
 */
export default defineNuxtRouteMiddleware(async () => {
  if (import.meta.server) {
    return;
  }
  const auth = useAuthStore();
  if (!auth.token) {
    return navigateTo('/login');
  }
  const config = useRuntimeConfig();
  const base = resolvePublicApiBaseUrl(config.public.apiUrl);
  try {
    const me = await $fetch(`${base}/api/auth/me`, {
      headers: { Authorization: `Bearer ${auth.token}` },
      timeout: 20000,
    });
    const roles = me.roles || [];
    if (!roles.includes('admin')) {
      return navigateTo('/');
    }
  } catch {
    return navigateTo('/login');
  }
});
