import { io } from 'socket.io-client';
import { useAuthStore } from '~/stores/auth';
import { useTicketsStore } from '~/stores/tickets';

/**
 * Namespace /private amb JWT d’API; escolta ticket:validated (T031/T033).
 */
export function usePrivateTicketSocket () {
  let socket = null;

  onMounted(() => {
    if (!import.meta.client) {
      return;
    }

    const config = useRuntimeConfig();
    const auth = useAuthStore();
    const ticketsStore = useTicketsStore();

    const base = (config.public.socketUrl || '').replace(/\/$/, '');
    if (!base || !auth.token) {
      return;
    }

    socket = io(`${base}/private`, {
      auth: { token: auth.token },
      transports: ['websocket', 'polling'],
    });

    socket.on('ticket:validated', (payload) => {
      ticketsStore.applyValidatedPayload(payload);
    });
  });

  onUnmounted(() => {
    if (socket) {
      socket.disconnect();
      socket = null;
    }
  });
}
