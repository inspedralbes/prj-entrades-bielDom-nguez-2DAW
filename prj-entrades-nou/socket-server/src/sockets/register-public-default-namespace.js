/**
 * Namespace per defecte: sales públiques, intents de seient, join admin dashboard.
 */

//================================ NAMESPACES / IMPORTS ============

import { getHandshakeEventId } from '../socket/handshake-event-id.js';

//================================ FUNCIONS PÚBLIQUES ============

/**
 * @param {{ io: import('socket.io').Server, postReleaseUserEvent: (uid: unknown, eventIdStr: string) => void }} deps
 */
export function registerPublicDefaultNamespace ({ io, postReleaseUserEvent }) {
  io.on('connection', (socket) => {
    const q = socket.handshake.query;
    const ha = socket.handshake.auth;
    let eventId = '';
    if (q && q.eventId) {
      eventId = String(q.eventId);
    }
    if (eventId === '' && ha && ha.eventId) {
      eventId = String(ha.eventId);
    }
    if (eventId !== '') {
      socket.join('event:' + eventId);
    }
    let anonSession = '';
    if (q && q.anonSession) {
      anonSession = String(q.anonSession);
    }
    if (anonSession !== '') {
      socket.join('anon:' + anonSession);
    }
    let logEventIdPub = null;
    if (eventId !== '') {
      logEventIdPub = eventId;
    }
    let logRoomPub = null;
    if (eventId !== '') {
      logRoomPub = 'event:' + eventId;
    }
    console.log('[socket-server][public] connection', {
      socketId: socket.id,
      eventId: logEventIdPub,
      room: logRoomPub,
    });
    let helloEventId = null;
    if (eventId !== '') {
      helloEventId = eventId;
    }
    socket.emit('server:hello', {
      channel: 'public',
      eventId: helloEventId,
      at: new Date().toISOString(),
    });

    socket.on('disconnect', (reason) => {
      let discEv = null;
      if (eventId !== '') {
        discEv = eventId;
      }
      console.log('[socket-server][public] disconnect', {
        socketId: socket.id,
        eventId: discEv,
        reason,
      });
      const jwtUid = socket.data.jwtSub;
      if (jwtUid && eventId !== '') {
        postReleaseUserEvent(jwtUid, eventId);
      }
    });

    socket.on('seat:contention', (payload) => {
      io.to('event:' + String(payload.eventId || '')).emit('seat:contention', payload);
    });

    socket.on('countdown:resync', (payload) => {
      io.to('event:' + String(payload.eventId || '')).emit('countdown:resync', payload);
    });

    /**
     * Reserva visual immediata (paral·lel a Redis via API): el mateix usuari envia intent amb JWT;
     * el servidor reemet SeatStatusUpdated als altres abans que Laravel acabi el POST.
     */
    socket.on('client:seat_hold_intent', (payload) => {
      const uid = socket.data.jwtSub;
      if (!uid) {
        return;
      }
      const sockEid = getHandshakeEventId(socket);
      if (!payload || typeof payload !== 'object') {
        return;
      }
      const pe = payload;
      const payloadEventId = String(pe.eventId || '');
      const seatId = String(pe.seatId || '');
      if (payloadEventId === '' || seatId === '') {
        return;
      }
      if (sockEid !== payloadEventId) {
        return;
      }
      io.to('event:' + payloadEventId).emit('SeatStatusUpdated', {
        eventId: payloadEventId,
        seatId,
        status: 'held',
        userId: uid,
        provisional: true,
      });
    });

    socket.on('client:seat_hold_rollback', (payload) => {
      const uid = socket.data.jwtSub;
      if (!uid) {
        return;
      }
      const sockEid = getHandshakeEventId(socket);
      if (!payload || typeof payload !== 'object') {
        return;
      }
      const pe = payload;
      const payloadEventId = String(pe.eventId || '');
      const seatId = String(pe.seatId || '');
      if (payloadEventId === '' || seatId === '') {
        return;
      }
      if (sockEid !== payloadEventId) {
        return;
      }
      io.to('event:' + payloadEventId).emit('SeatStatusUpdated', {
        eventId: payloadEventId,
        seatId,
        status: 'available',
        userId: null,
        provisional: true,
      });
    });

    /**
     * Panell admin: join:admin-dashboard → room admin:dashboard per admin:metrics.
     */
    socket.on('join:admin-dashboard', () => {
      socket.join('admin:dashboard');
    });
  });
}
