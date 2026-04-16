/**
 * Registre del namespace `/private`: rooms, admin:metrics, alliberament en disconnect.
 */

//================================ FUNCIONS PÚBLIQUES ============

/**
 * @param {{ privateNs: import('socket.io').Namespace, postReleaseUserEvent: (uid: unknown, eventIdStr: string) => void }} deps
 */
export function registerPrivateNamespace ({ privateNs, postReleaseUserEvent }) {
  privateNs.on('connection', (socket) => {
    const uid = socket.data.userId;
    if (uid) {
      socket.join('user:' + String(uid));
    }
    const pq = socket.handshake.query;
    let eventIdJoin = '';
    if (pq && pq.eventId) {
      eventIdJoin = String(pq.eventId);
    }
    if (eventIdJoin !== '') {
      socket.join('event:' + eventIdJoin);
    }
    let logUserId = null;
    if (uid) {
      logUserId = String(uid);
    }
    let logEventIdPriv = null;
    if (eventIdJoin !== '') {
      logEventIdPriv = eventIdJoin;
    }
    console.log('[socket-server][private] connection', {
      socketId: socket.id,
      userId: logUserId,
      eventId: logEventIdPriv,
    });
    socket.emit('server:hello', { channel: 'private', at: new Date().toISOString() });

    socket.on('admin:metrics', (payload) => {
      privateNs.emit('admin:metrics', payload);
    });

    socket.on('disconnect', (reason) => {
      let discUserId = null;
      if (uid) {
        discUserId = String(uid);
      }
      console.log('[socket-server][private] disconnect', {
        socketId: socket.id,
        userId: discUserId,
        reason,
      });
      if (!uid) {
        return;
      }
      if (eventIdJoin === '') {
        return;
      }
      postReleaseUserEvent(uid, eventIdJoin);
    });
  });
}
