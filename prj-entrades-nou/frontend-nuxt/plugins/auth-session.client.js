//================================ IMPORTS ============

import { useAuthStore } from '~/stores/auth';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

const TOKEN_KEY = 'speckit_auth_token';
const USER_KEY = 'speckit_auth_user';

/**
 * Cookie `auth_token` + usuari des de GET /api/auth/me (rols actuals: admin, user, …).
 * Abans només s’hidratava des de localStorage i podia quedar sense rol «admin» després de syncRoles al backend.
 */
export default defineNuxtPlugin({
  name: 'auth-session',
  async setup () {
    const auth = useAuthStore();
    const tokenCookie = useCookie('auth_token');
    const config = useRuntimeConfig();

    auth.init();

    const fromCookie =
      typeof tokenCookie.value === 'string'
        ? tokenCookie.value.trim()
        : tokenCookie.value;

    if (!fromCookie) {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
      return;
    }

    const base = resolvePublicApiBaseUrl(config.public.apiUrl);

    try {
      const me = await $fetch(`${base}/api/auth/me`, {
        headers: { Authorization: `Bearer ${fromCookie}` },
        timeout: 20000,
      });
      auth.setSession({ token: fromCookie, user: me });
    } catch (err) {
      const code = err.statusCode !== undefined ? err.statusCode : err.status;
      if (code === 401) {
        auth.clearSession();
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        return;
      }
      const rawU = localStorage.getItem(USER_KEY);
      const rawT = localStorage.getItem(TOKEN_KEY);
      if (rawT === fromCookie && rawU) {
        try {
          auth.user = JSON.parse(rawU);
        } catch {
          localStorage.removeItem(USER_KEY);
        }
      }
    }

    auth.$subscribe((_mutation, state) => {
      if (state.token && state.user) {
        localStorage.setItem(TOKEN_KEY, state.token);
        localStorage.setItem(USER_KEY, JSON.stringify(state.user));
      } else {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
      }
    });
  },
});
