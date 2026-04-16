/**
 * `eventId` del handshake (query o auth), una sola font per validar intents de seient.
 */

//================================ FUNCIONS PÚBLIQUES ============

export function getHandshakeEventId (socket) {
  const q = socket.handshake.query;
  const ha = socket.handshake.auth;
  let eventId = '';
  if (q && q.eventId) {
    eventId = String(q.eventId);
  }
  if (eventId === '' && ha && ha.eventId) {
    eventId = String(ha.eventId);
  }
  return eventId;
}
