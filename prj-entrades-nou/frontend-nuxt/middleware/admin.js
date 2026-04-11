import { useAuthStore } from '~/stores/auth';

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
  const base = (config.public.apiUrl || '').replace(/\/$/, '');
  try {
    const me = await $fetch(`${base}/api/auth/me`, {
      headers: { Authorization: `Bearer ${auth.token}` },
    });
    const roles = me.roles || [];
    if (!roles.includes('admin')) {
      return navigateTo('/');
    }
  } catch {
    return navigateTo('/login');
  }
});
