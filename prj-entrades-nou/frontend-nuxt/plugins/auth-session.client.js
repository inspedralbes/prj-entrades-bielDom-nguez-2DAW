import { useAuthStore } from '~/stores/auth';

const TOKEN_KEY = 'speckit_auth_token';
const USER_KEY = 'speckit_auth_user';

/**
 * Persistència token + usuari per a proves E2E i UX.
 * Important: no crear sessió només des de localStorage; cal cookie `auth_token`
 * (alineada amb middleware SSR). Abans setSession() des de localStorage
 * escrivia la cookie i saltava l’autenticació real.
 */
export default defineNuxtPlugin(() => {
  const auth = useAuthStore();
  const tokenCookie = useCookie('auth_token');

  auth.init();

  const fromCookie =
    typeof tokenCookie.value === 'string'
      ? tokenCookie.value.trim()
      : tokenCookie.value;

  if (!fromCookie) {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  } else {
    const rawT = localStorage.getItem(TOKEN_KEY);
    const rawU = localStorage.getItem(USER_KEY);
    if (rawT === fromCookie && rawU) {
      try {
        auth.user = JSON.parse(rawU);
      } catch {
        localStorage.removeItem(USER_KEY);
      }
    } else if (rawT && rawT !== fromCookie) {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
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
});
