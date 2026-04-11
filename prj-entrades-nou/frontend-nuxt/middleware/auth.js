import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware((to, from) => {
  if (import.meta.server) {
    return;
  }
  const auth = useAuthStore();
  if (!auth.token) {
    return navigateTo('/login');
  }
});
