import { io } from 'socket.io-client';
import { onMounted, onUnmounted, watch } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { resolvePublicSocketUrl } from '~/utils/apiBase.js';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';
import { useSocialToastsStore } from '~/stores/socialToasts';
import { useTicketsStore } from '~/stores/tickets';

/** Una sola connexió compartida (layout); evita desconnexió en canvi de ruta. */
let sharedSocket = null;

/**
 * Si la finestra és HTTPS al 443 i la URL configurada apunta al mateix host al :3001,
 * usem l’origen de la finestra (p. ex. nginx fa proxy de /socket.io al Node).
 * Això evita WSS directe al 3001 quan només el 443 té certificat.
 */
function preferSameOriginWhenHttpsPortMismatch (resolvedBase) {
  if (typeof window === 'undefined' || !window.location) {
    return resolvedBase;
  }
  if (window.location.protocol !== 'https:') {
    return resolvedBase;
  }
  let out = resolvedBase;
  try {
    const u = new URL(resolvedBase);
    const loc = window.location;
    if (u.hostname !== loc.hostname) {
      return out;
    }
    let locPort = loc.port;
    if (locPort === '') {
      locPort = '443';
    }
    if (u.port === '3001' && locPort === '443') {
      out = loc.origin;
    }
  } catch {
    return out;
  }
  return out;
}

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
      const raw = resolvePublicSocketUrl(config.public.socketUrl).replace(/\/$/, '');
      const base = preferSameOriginWhenHttpsPortMismatch(raw);
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
