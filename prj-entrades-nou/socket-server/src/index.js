/**
 * Socket.IO híbrid (FR-014): lectura pública per eventId; namespace /private amb JWT al handshake.
 * Gateway temps real (Agent Node / Agent Sockets): HTTP intern + Socket.IO + JWT.
 */

//================================ NAMESPACES / IMPORTS ============

import 'dotenv/config';
import { createServer } from 'http';
import { generateTicketSvg } from './qr/generateTicketSvg.js';
import { createInternalHttpHandler } from './http/internal-routes.js';
import { createDefaultJwtMiddleware } from './middleware/socket-jwt-default.js';
import { createPrivateJwtMiddleware } from './middleware/socket-jwt-private.js';
import { createSocketServer } from './socket/create-socket-server.js';
import { postReleaseUserEvent } from './services/laravel-internal-seat-holds.js';
import { registerPrivateNamespace } from './sockets/register-private-namespace.js';
import { registerPublicDefaultNamespace } from './sockets/register-public-default-namespace.js';

//================================ VARIABLES / CONSTANTS ============

const jwtSecret = process.env.JWT_SECRET || process.env.APP_KEY || '';

// Estat mutable després d’inicialitzar `io` i `privateNs` (POST /internal/emit).
const appState = {
  io: null,
  privateNs: null,
};

//================================ SERVIDOR HTTP ============

const httpServer = createServer();
httpServer.on('request', createInternalHttpHandler(appState, generateTicketSvg));

//================================ Socket.IO ============

const { io, privateNs } = createSocketServer(httpServer);

io.use(createDefaultJwtMiddleware({ jwtSecret }));
privateNs.use(createPrivateJwtMiddleware({ jwtSecret }));

registerPrivateNamespace({ privateNs, postReleaseUserEvent });

appState.io = io;
appState.privateNs = privateNs;

registerPublicDefaultNamespace({ io, postReleaseUserEvent });

//================================ ARRENCADA ============

const port = Number(process.env.PORT || 3001);
httpServer.listen(port, '0.0.0.0', () => {
  console.log('socket-server escoltant a ' + port);
});
