import { enforceAuthCookie } from '~/utils/authGate';

/**
 * Reserva de seguretat: mapa de seients (compra) exigeix sessió,
 * encara que alguna pàgina oblidés `definePageMeta({ middleware: 'auth' })`.
 */
export default defineNuxtRouteMiddleware((to) => {
  const path = to.path || '';
  const isSeats = /^\/events\/[^/]+\/seats\/?$/.test(path);
  if (!isSeats) {
    return;
  }
  return enforceAuthCookie(to);
});
