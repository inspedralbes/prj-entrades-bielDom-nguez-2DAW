/**
 * Factory del handler HTTP compartit amb Socket.IO: health, internal emit, QR SVG.
 * A. GET /health · B. POST /internal/emit · C. POST /internal/qr-svg
 */

//================================ NAMESPACES / IMPORTS ============

import { internalSecretOk } from './internal-auth.js';
import { readJsonBody } from './read-json-body.js';
import { requestPathOnly } from './request-path.js';

//================================ FUNCIONS PÚBLIQUES ============

/**
 * @param {{ io: import('socket.io').Server | null, privateNs: import('socket.io').Namespace | null }} appState
 * @param {typeof import('../qr/generateTicketSvg.js').generateTicketSvg} generateTicketSvg
 */
export function createInternalHttpHandler (appState, generateTicketSvg) {
  return function internalHttpHandler (req, res) {
    const pathOnly = requestPathOnly(req);
    if (pathOnly === '/health' && req.method === 'GET') {
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ status: 'ok', service: 'socket-server' }));
      return;
    }
    if (pathOnly === '/internal/emit' && req.method === 'POST') {
      if (!internalSecretOk(req)) {
        res.writeHead(403);
        res.end();
        return;
      }
      const ioRef = appState.io;
      const privateNsRef = appState.privateNs;
      if (!ioRef || !privateNsRef) {
        res.writeHead(503, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ error: 'socket-server no preparat' }));
        return;
      }
      readJsonBody(req)
        .then(async (body) => {
          const room = String(body.room || '').trim();
          const evt = String(body.event || '');
          const payload = body.payload;
          if (room === '' || evt === '') {
            res.writeHead(204);
            res.end();
            return;
          }
          try {
            privateNsRef.in(room).emit(evt, payload);
            const defSockets = await ioRef.in(room).fetchSockets();
            let ids = '';
            let i = 0;
            for (; i < defSockets.length; i += 1) {
              defSockets[i].emit(evt, payload);
              if (i > 0) {
                ids = ids + ',';
              }
              ids = ids + defSockets[i].id;
            }
            console.log('[socket-server][internal/emit]', {
              room,
              event: evt,
              defaultNsRecipients: defSockets.length,
              socketIds: ids,
            });
          } catch (err) {
            let m = 'emit failed';
            if (err && err.message) {
              m = err.message;
            }
            console.warn('[socket-server][internal/emit] error', m);
          }
          res.writeHead(204);
          res.end();
        })
        .catch(() => {
          res.writeHead(400);
          res.end();
        });
      return;
    }
    if (pathOnly === '/internal/qr-svg' && req.method === 'POST') {
      if (!internalSecretOk(req)) {
        res.writeHead(403);
        res.end();
        return;
      }
      readJsonBody(req)
        .then(async (body) => {
          const text = String(body.text || body.payload || '');
          if (text === '') {
            res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ error: 'text or payload requerit' }));
            return;
          }
          let width;
          if (body.width !== undefined) {
            width = Number(body.width);
          }
          let margin;
          if (body.margin !== undefined) {
            margin = Number(body.margin);
          }
          const svg = await generateTicketSvg(text, { width, margin });
          res.writeHead(200, { 'Content-Type': 'image/svg+xml; charset=utf-8' });
          res.end(svg);
        })
        .catch(() => {
          res.writeHead(400);
          res.end();
        });
      return;
    }
    res.writeHead(404);
    res.end();
  };
}
