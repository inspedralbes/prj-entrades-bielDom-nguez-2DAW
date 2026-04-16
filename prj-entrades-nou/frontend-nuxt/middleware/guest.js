//================================ IMPORTS ============

import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware((to, from) => {
  const auth = useAuthStore();
  auth.init();
  const authToken = useCookie('auth_token');
  const raw = authToken.value;
  const cookieTok = typeof raw === 'string' ? raw.trim() : '';
  if (cookieTok.length > 0 || auth.token) {
    return navigateTo('/');
  }
});
