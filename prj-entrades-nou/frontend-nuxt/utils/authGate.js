import { useAuthStore } from '~/stores/auth';

/**
 * Comprovació d’auth alineada amb cookie `auth_token` (SSR + client).
 * Cridar des de middleware Nuxt.
 */
export function enforceAuthCookie (to) {
  const authToken = useCookie('auth_token');
  const raw = authToken.value;
  const token = typeof raw === 'string' ? raw.trim() : raw;
  if (!token) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    });
  }
  const auth = useAuthStore();
  auth.init();
}
