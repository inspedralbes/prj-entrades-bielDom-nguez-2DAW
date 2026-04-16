/**
 * JWT opcional al namespace per defecte: `socket.data.jwtSub` per reemetre intents de seient.
 */

//================================ NAMESPACES / IMPORTS ============

import jwt from 'jsonwebtoken';

//================================ FUNCIONS PÚBLIQUES ============

export function createDefaultJwtMiddleware ({ jwtSecret }) {
  return function defaultJwtMiddleware (socket, next) {
    socket.data.jwtSub = null;
    if (jwtSecret === '') {
      next();
      return;
    }
    const ha = socket.handshake.auth;
    let raw = '';
    if (ha && ha.token) {
      raw = String(ha.token);
    }
    let tok = raw;
    if (tok.indexOf('Bearer ') === 0) {
      tok = tok.slice(7);
    }
    if (tok === '') {
      next();
      return;
    }
    try {
      const decoded = jwt.verify(tok, jwtSecret);
      if (decoded.sub !== undefined && decoded.sub !== null) {
        socket.data.jwtSub = String(decoded.sub);
      }
    } catch {
      /* token invàlid: sense jwtSub no es reemeten intents */
    }
    next();
  };
}
