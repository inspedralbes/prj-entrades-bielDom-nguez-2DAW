import { useAuthStore } from '~/stores/auth';
import { resolveApiBaseUrlForFetch } from '~/utils/apiBase';
import { rolesIncludeAdmin } from '~/utils/userRoles';

/**
 * Amb sessió i rol «admin», la pàgina d’inici (/) envia al panell /admin.
 * Així, en obrir l’app a l’arrel o després del login, l’admin veu el dashboard sense quedar al feed públic.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path !== '/') {
    return;
  }

  const auth = useAuthStore();
  auth.init();

  const authToken = useCookie('auth_token');
  const rawCookie = authToken.value;
  const cookieToken = typeof rawCookie === 'string' ? rawCookie.trim() : '';
  if (!cookieToken && !auth.token) {
    return;
  }

  const bearer = auth.token || cookieToken;

  if (auth.user && rolesIncludeAdmin(auth.user.roles)) {
    return navigateTo('/admin', { replace: true });
  }

  const config = useRuntimeConfig();
  const base = resolveApiBaseUrlForFetch(config);
  try {
    const me = await $fetch(`${base}/api/auth/me`, {
      headers: { Authorization: `Bearer ${bearer}` },
      timeout: 20000,
    });
    auth.setSession({ token: bearer, user: me });
    if (rolesIncludeAdmin(me.roles)) {
      return navigateTo('/admin', { replace: true });
    }
  } catch {
    /* sense /me vàlid, es mostra la home normal */
  }
});
