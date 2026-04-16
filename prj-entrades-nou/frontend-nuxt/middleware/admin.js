import { useAuthStore } from '~/stores/auth';
import { resolveApiBaseUrlForFetch } from '~/utils/apiBase';
import { getForbiddenRedirectPath } from '~/utils/routeForbiddenRedirect';
import { rolesIncludeAdmin } from '~/utils/userRoles';

/**
 * Només rols amb «admin» (Spatie) poden accedir a /admin/*.
 * Sense sessió → login amb `redirect` cap a la ruta demanada.
 * Amb sessió sense rol → tornada a la pàgina anterior (from) quan sigui segur; si no, «/».
 * La comprovació corre també en SSR (cookie + GET /api/auth/me) per no servir HTML del panell sense permís.
 */
export default defineNuxtRouteMiddleware(async (to, from) => {
  const auth = useAuthStore();
  auth.init();

  const authToken = useCookie('auth_token');
  const rawCookie = authToken.value;
  const cookieToken = typeof rawCookie === 'string' ? rawCookie.trim() : '';
  if (!cookieToken && !auth.token) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }

  if (auth.user && rolesIncludeAdmin(auth.user.roles)) {
    return;
  }

  const config = useRuntimeConfig();
  const base = resolveApiBaseUrlForFetch(config);
  const bearer = auth.token || cookieToken;
  try {
    const me = await $fetch(`${base}/api/auth/me`, {
      headers: { Authorization: `Bearer ${bearer}` },
      timeout: 20000,
    });
    auth.setSession({ token: bearer, user: me });
    const roles = me.roles || [];
    if (!rolesIncludeAdmin(roles)) {
      const target = getForbiddenRedirectPath(to, from, '/admin');
      return navigateTo(target);
    }
  } catch (err) {
    const status = err?.statusCode || err?.status;
    if (status === 401) {
      return navigateTo({
        path: '/login',
        query: { redirect: to.fullPath },
      });
    }
    const target = getForbiddenRedirectPath(to, from, '/admin');
    return navigateTo(target);
  }
});
