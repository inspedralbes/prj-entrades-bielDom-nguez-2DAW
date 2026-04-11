import { unref } from 'vue';
import { io } from 'socket.io-client';

/**
 * Socket.IO públic per `eventId` + room `anon:{anonSession}` (T023).
 * @param {import('vue').Ref<string|string[]>|string} eventIdRef
 * @param {{ onContention?: (p: unknown) => void, onResync?: (p: unknown) => void }} callbacks
 */
export function useEventSeatSocket (eventIdRef, callbacks = {}) {
  let socket = null;

  onMounted(() => {
    if (!import.meta.client) {
      return;
    }

    const config = useRuntimeConfig();
    const holdStore = useHoldStore();
    holdStore.ensureAnonymousSession();

    const base = (config.public.socketUrl || '').replace(/\/$/, '');
    if (!base) {
      return;
    }

    const raw = unref(eventIdRef);
    const eid = Array.isArray(raw) ? raw[0] : raw;
    if (!eid) {
      return;
    }

    socket = io(base, {
      transports: ['websocket', 'polling'],
      query: {
        eventId: String(eid),
        anonSession: holdStore.anonymousSessionId || '',
      },
    });

    socket.on('seat:contention', (payload) => {
      callbacks.onContention?.(payload);
    });

    socket.on('countdown:resync', (payload) => {
      callbacks.onResync?.(payload);
    });
  });

  onUnmounted(() => {
    if (socket) {
      socket.disconnect();
      socket = null;
    }
  });
}
