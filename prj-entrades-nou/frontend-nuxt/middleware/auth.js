import { enforceAuthCookie } from '~/utils/authGate';

export default defineNuxtRouteMiddleware((to) => {
  return enforceAuthCookie(to);
});
