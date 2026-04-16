/**
 * Motor Socket.IO sobre el mateix `http.Server` (CORS configurable).
 */

//================================ NAMESPACES / IMPORTS ============

import { Server } from 'socket.io';

//================================ FUNCIONS PÚBLIQUES ============

/**
 * @param {import('http').Server} httpServer
 * @param {{ cors?: { origin?: boolean | string | string[], methods?: string[] } }} [options]
 */
export function createSocketServer (httpServer, options) {
  let corsOpt = { origin: true, methods: ['GET', 'POST'] };
  if (options && options.cors) {
    corsOpt = options.cors;
  }
  const io = new Server(httpServer, {
    cors: corsOpt,
  });
  const privateNs = io.of('/private');
  return { io, privateNs };
}
