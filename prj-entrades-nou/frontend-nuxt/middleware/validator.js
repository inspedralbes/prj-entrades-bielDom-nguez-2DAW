//================================ IMPORTS ============

import { useAuthStore } from '~/stores/auth';
import { storeAuthIntendedPath } from '~/utils/authGate';
import { resolveApiBaseUrlForFetch } from '~/utils/apiBase';
import { getForbiddenRedirectPath } from '~/utils/routeForbiddenRedirect';
import { rolesIncludeValidator } from '~/utils/userRoles';

/**
 * Rutes de validació d’entrades: cal rol «validator».
 * Sense sessió → login amb `redirect`. Sense rol → tornada a la vista anterior o «/».
 */
export default defineNuxtRouteMiddleware(async (to, from) => {
  const auth = useAuthStore();
  auth.init();

  const authToken = useCookie('auth_token');
  const rawCookie = authToken.value;
  const cookieToken = typeof rawCookie === 'string' ? rawCookie.trim() : '';
  if (!cookieToken && !auth.token) {
    storeAuthIntendedPath(to);
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }

  if (auth.user && rolesIncludeValidator(auth.user.roles)) {
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
    if (!rolesIncludeValidator(roles)) {
      const target = getForbiddenRedirectPath(to, from, '/validator');
      return navigateTo(target);
    }
  } catch (err) {
    const status = err?.statusCode || err?.status;
    if (status === 401) {
      storeAuthIntendedPath(to);
      return navigateTo({
        path: '/login',
        query: { redirect: to.fullPath },
      });
    }
    const target = getForbiddenRedirectPath(to, from, '/validator');
    return navigateTo(target);
  }
});
