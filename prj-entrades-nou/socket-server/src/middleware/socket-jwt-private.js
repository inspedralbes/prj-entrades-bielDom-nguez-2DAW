/**
 * JWT obligatori al namespace `/private`; omple `socket.data.userId` (sub del token).
 */

//================================ NAMESPACES / IMPORTS ============

import jwt from 'jsonwebtoken';

//================================ FUNCIONS PÚBLIQUES ============

export function createPrivateJwtMiddleware ({ jwtSecret }) {
  return function privateJwtMiddleware (socket, next) {
    if (jwtSecret === '') {
      return next(new Error('JWT_SECRET no configurat'));
    }
    const auth = socket.handshake.auth;
    let raw = '';
    if (auth && auth.token) {
      raw = String(auth.token);
    }
    let token = raw;
    if (token.indexOf('Bearer ') === 0) {
      token = token.slice(7);
    }
    if (token === '') {
      return next(new Error('Unauthorized'));
    }
    try {
      const decoded = jwt.verify(token, jwtSecret);
      let subVal = null;
      if (decoded.sub !== undefined && decoded.sub !== null) {
        subVal = decoded.sub;
      }
      socket.data.userId = subVal;
      next();
    } catch (e) {
      let msg = 'verify failed';
      if (e && e.message) {
        msg = e.message;
      }
      console.warn('[socket-server][private] JWT verify failed:', msg);
      next(new Error('Unauthorized'));
    }
  };
}
