import { unref, watch, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { io } from 'socket.io-client';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';
import { resolvePublicSocketUrl } from '~/utils/apiBase';

/**
 * URL del socket: runtimeConfig (amb resolució producció), o fallback localhost:3001 / mateix origen.
 */
function resolveSocketBase (config) {
  const configured = (config.public.socketUrl || '').trim();
  if (configured !== '') {
    return resolvePublicSocketUrl(configured).replace(/\/$/, '');
  }
  if (import.meta.client && typeof window !== 'undefined') {
    const h = window.location.hostname;
    const p = window.location.protocol;
    if (h === 'localhost' || h === '127.0.0.1') {
      return `${p}//${h}:3001`;
    }
    return window.location.origin;
  }
  return '';
}

const commonOpts = {
  /* Polling primer: menys soroll de WS si el primer intent falla; mateix servidor Socket.IO. */
  transports: ['polling', 'websocket'],
  reconnection: true,
  reconnectionAttempts: 12,
  reconnectionDelay: 500,
  timeout: 15000,
};

/**
 * Temps real del mapa:
 * - Namespace per defecte + query eventId + JWT opcional a auth.token: intents de reserva immediats.
 * - /private + JWT: alliberament de holds en tancar pestanya.
 */
export function usePrivateSeatmapSocket (eventIdRef) {
  let publicSocket = null;
  let privateSocket = null;

  const auth = useAuthStore();
  const { token } = storeToRefs(auth);
  const { getJson } = useAuthorizedApi();

  function disconnectPublic () {
    if (publicSocket) {
      publicSocket.removeAllListeners();
      publicSocket.disconnect();
      publicSocket = null;
    }
  }

  function disconnectPrivate () {
    if (privateSocket) {
      privateSocket.removeAllListeners();
      privateSocket.disconnect();
      privateSocket = null;
    }
  }

  function connectPublicOnly () {
    if (!import.meta.client) {
      return;
    }

    disconnectPublic();

    const config = useRuntimeConfig();
    const seatmapStore = useInteractiveSeatmapStore();
    const base = resolveSocketBase(config);
    const raw = unref(eventIdRef);
    const eid = Array.isArray(raw) ? raw[0] : raw;

    if (!base || !eid) {
      console.warn('[seatmap-socket][public] no es connecta: falta base o eventId', { base, eid });
      return;
    }

    seatmapStore.syncRouteEventId(String(eid));

    console.log('[seatmap-socket][public] intentant connexió', { base, eventId: String(eid) });

    const authPayload = { eventId: String(eid) };
    const tokPublic = token.value;
    if (tokPublic) {
      authPayload.token = tokPublic;
    }

    publicSocket = io(base, {
      ...commonOpts,
      query: { eventId: String(eid) },
      /* eventId + JWT: el socket-server verifica intents client:seat_hold_intent sense confiar en el cos */
      auth: authPayload,
    });

    publicSocket.on('connect', () => {
      console.log('[seatmap-socket][public] connectat', {
        socketId: publicSocket.id,
        eventId: String(eid),
        transport: publicSocket.io && publicSocket.io.engine ? publicSocket.io.engine.transport.name : null,
      });
      /* SoT Redis+PG: qui entra després d’una reserva o hi ha carrera amb la primera GET, torna a llegir el seatmap */
      auth.init();
      const idStr = String(eid);
      getJson(`/api/events/${idStr}/seatmap`, { noCache: true })
        .then((sm) => {
          seatmapStore.bootstrapFromApi(sm, idStr);
          const uid = auth.user && auth.user.id !== undefined ? auth.user.id : null;
          seatmapStore.setCurrentUserId(uid);
        })
        .catch((err) => {
          console.warn('[seatmap-socket][public] sync seatmap després de connect ha fallat', err);
        });
    });

    publicSocket.on('disconnect', (reason) => {
      console.log('[seatmap-socket][public] desconnectat', { reason, eventId: String(eid) });
    });

    publicSocket.on('server:hello', (msg) => {
      console.log('[seatmap-socket][public] server:hello', msg);
    });

    publicSocket.on('SeatStatusUpdated', (payload) => {
      console.log('[seatmap-socket][public] SeatStatusUpdated rebut', payload);
      seatmapStore.applySocketUpdate(payload);
    });

    publicSocket.on('connect_error', (err) => {
      let msg = 'unknown';
      if (err && err.message) {
        msg = err.message;
      }
      console.warn('[seatmap-socket][public] connect_error', { msg, base, eventId: String(eid) });
      /* El mapa segueix vàlid amb dades de l’API; només faltarà temps real fins reconnect. */
    });
  }

  /**
   * Emissió immediata al room (abans que Redis respongui via API): la resta d’usuaris veuen el hold.
   */
  function emitSeatHoldIntent (seatId) {
    if (!publicSocket || !publicSocket.connected) {
      return;
    }
    const raw = unref(eventIdRef);
    const eid = Array.isArray(raw) ? raw[0] : raw;
    if (!eid || !seatId) {
      return;
    }
    publicSocket.emit('client:seat_hold_intent', {
      eventId: String(eid),
      seatId: String(seatId),
    });
  }

  /**
   * Si l’API de hold falla, revertir visual als altres (paral·lel a revertOptimisticReserve local).
   */
  function emitSeatHoldRollback (seatId) {
    if (!publicSocket || !publicSocket.connected) {
      return;
    }
    const raw = unref(eventIdRef);
    const eid = Array.isArray(raw) ? raw[0] : raw;
    if (!eid || !seatId) {
      return;
    }
    publicSocket.emit('client:seat_hold_rollback', {
      eventId: String(eid),
      seatId: String(seatId),
    });
  }

  function connectPrivateOnly () {
    if (!import.meta.client) {
      return;
    }

    disconnectPrivate();

    const config = useRuntimeConfig();
    const base = resolveSocketBase(config);
    const raw = unref(eventIdRef);
    const eid = Array.isArray(raw) ? raw[0] : raw;
    const tok = token.value;

    if (!base || !eid || !tok) {
      if (!tok) {
        console.log('[seatmap-socket][private] sense token: no es connecta /private (normal si no hi ha sessió)');
      }
      return;
    }

    console.log('[seatmap-socket][private] intentant connexió', { base, eventId: String(eid) });

    privateSocket = io(`${base}/private`, {
      ...commonOpts,
      /* Connexió separada: si /private falla (JWT), no ha d’afectar el motor del socket públic (multiplex). */
      multiplex: false,
      auth: { token: tok },
      query: { eventId: String(eid) },
    });

    privateSocket.on('connect', () => {
      console.log('[seatmap-socket][private] connectat', {
        socketId: privateSocket.id,
        eventId: String(eid),
        transport: privateSocket.io && privateSocket.io.engine ? privateSocket.io.engine.transport.name : null,
      });
    });

    privateSocket.on('disconnect', (reason) => {
      console.log('[seatmap-socket][private] desconnectat', { reason, eventId: String(eid) });
    });

    privateSocket.on('server:hello', (msg) => {
      console.log('[seatmap-socket][private] server:hello', msg);
    });

    privateSocket.on('connect_error', (err) => {
      let msg = 'unknown';
      if (err && err.message) {
        msg = err.message;
      }
      console.warn('[seatmap-socket][private] connect_error', { msg, base, eventId: String(eid) });
      /* JWT desalineat amb socket-server: sense /private no s’alliberen holds en tancar pestanya. */
    });
  }

  watch(
    () => unref(eventIdRef),
    () => {
      connectPublicOnly();
      connectPrivateOnly();
    },
    { immediate: true },
  );

  watch(
    () => token.value,
    () => {
      connectPublicOnly();
      connectPrivateOnly();
    },
    { immediate: true },
  );

  onUnmounted(() => {
    disconnectPublic();
    disconnectPrivate();
  });

  return {
    emitSeatHoldIntent,
    emitSeatHoldRollback,
  };
}
