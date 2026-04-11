import { useAuthStore } from '~/stores/auth';

const TOKEN_KEY = 'speckit_auth_token';
const USER_KEY = 'speckit_auth_user';

/**
 * Persistència mínima token + usuari (T029) per a middleware i proves Cypress.
 */
export default defineNuxtPlugin(() => {
  const auth = useAuthStore();

  const rawT = localStorage.getItem(TOKEN_KEY);
  const rawU = localStorage.getItem(USER_KEY);
  if (rawT && rawU) {
    try {
      auth.setSession({ token: rawT, user: JSON.parse(rawU) });
    } catch {
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
