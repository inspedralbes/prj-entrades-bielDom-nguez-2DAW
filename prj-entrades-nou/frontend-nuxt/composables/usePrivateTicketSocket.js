//================================ IMPORTS ============

import { io } from 'socket.io-client';
import { onMounted, onUnmounted, watch } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { resolvePublicSocketUrl } from '~/utils/apiBase.js';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';
import { useSocialToastsStore } from '~/stores/socialToasts';
import { useTicketsStore } from '~/stores/tickets';

/* URL del socket: `resolvePublicSocketUrl` (apiBase) reescriu :3001 → origen 80/443 quan cal proxy Nginx. */

/** Una sola connexió compartida (layout); evita desconnexió en canvi de ruta. */
let sharedSocket = null;

function handleNotificationPayload (payload) {
  if (!payload || typeof payload !== 'object') {
    return;
  }
  const nType = payload.type;
  let suppressToast = false;
  if (nType === 'event_shared' || nType === 'ticket_shared') {
    const peer = payload.thread_peer_user_id;
    if (peer !== undefined && peer !== null) {
      const mutes = useSocialThreadMutesStore();
      if (mutes.isMuted(String(peer))) {
        suppressToast = true;
      }
    }
  }
  const toast = payload.toast;
  if (!suppressToast && toast && typeof toast.body === 'string' && toast.body.trim() !== '') {
    const toasts = useSocialToastsStore();
    toasts.push(toast.body.trim(), 'social');
  }
  if (nType === 'event_shared' || nType === 'ticket_shared') {
    const peer = payload.thread_peer_user_id;
    if (peer !== undefined && peer !== null && typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:social-share-thread', {
        detail: { peerUserId: String(peer) },
      }));
    }
  }
  if (typeof window !== 'undefined') {
    let socketEventType = '';
    if (payload.type !== undefined && payload.type !== null) {
      socketEventType = String(payload.type);
    }
    window.dispatchEvent(new CustomEvent('app:socket-notification', {
      detail: { type: socketEventType },
    }));
  }
}

function attachSocketHandlers (socket, ticketsStore) {
  socket.on('ticket:validated', (payload) => {
    ticketsStore.applyValidatedPayload(payload);
  });
  socket.on('notification:new', handleNotificationPayload);
}

/**
 * Namespace /private amb JWT d’API; escolta ticket:validated i notificacions socials en temps real.
 */
export function usePrivateTicketSocket () {
  let stopWatch = null;

  onMounted(() => {
    if (!import.meta.client) {
      return;
    }

    const config = useRuntimeConfig();
    const auth = useAuthStore();
    const ticketsStore = useTicketsStore();

    function tryConnect () {
      const base = resolvePublicSocketUrl(config.public.socketUrl).replace(/\/$/, '');
      if (!base || !auth.token) {
        return;
      }
      if (sharedSocket !== null) {
        return;
      }

      /* Polling primer: menys errors visibles si el WS inicial falla; mateix servidor Socket.IO. */
      sharedSocket = io(`${base}/private`, {
        auth: { token: auth.token },
        transports: ['polling', 'websocket'],
      });
      attachSocketHandlers(sharedSocket, ticketsStore);
    }

    tryConnect();

    stopWatch = watch(
      () => auth.token,
      () => {
        tryConnect();
      },
    );
  });

  onUnmounted(() => {
    if (typeof stopWatch === 'function') {
      stopWatch();
      stopWatch = null;
    }
  });
}
