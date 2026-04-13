import { enforceAuthCookie } from '~/utils/authGate';

/**
 * Reserva de seguretat: compra / quantitat / checkout sempre exigeixen sessió,
 * encara que alguna pàgina oblidés `definePageMeta({ middleware: 'auth' })`.
 */
export default defineNuxtRouteMiddleware((to) => {
  const path = to.path || '';
  const isCheckout = path === '/checkout';
  const isSeats = /^\/events\/[^/]+\/seats\/?$/.test(path);
  if (!isCheckout && !isSeats) {
    return;
  }
  return enforceAuthCookie(to);
});
