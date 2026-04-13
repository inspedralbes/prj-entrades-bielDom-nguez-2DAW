/**
 * Socket.IO híbrid (FR-014): lectura pública per eventId; namespace /private amb JWT al handshake.
 */
import 'dotenv/config';
import { createServer } from 'http';
import jwt from 'jsonwebtoken';
import { Server } from 'socket.io';
import { generateTicketSvg } from './qr/generateTicketSvg.js';

const jwtSecret = process.env.JWT_SECRET || process.env.APP_KEY || '';

function internalSecretOk (req) {
  const expected = process.env.SOCKET_INTERNAL_SECRET || '';
  const got = req.headers['x-internal-secret'] || '';
  if (expected !== '' && got !== expected) {
    return false;
  }
  return true;
}

function readJsonBody (req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (c) => {
      chunks.push(c);
    });
    req.on('end', () => {
      try {
        const raw = Buffer.concat(chunks).toString('utf8');
        resolve(JSON.parse(raw));
      } catch (e) {
        reject(e);
      }
    });
    req.on('error', reject);
  });
}

const httpServer = createServer();

//================================ Socket.IO (cal crear abans del handler HTTP per usar io a /internal/emit)
const io = new Server(httpServer, {
  cors: {
    origin: true,
    methods: ['GET', 'POST'],
  },
});

/**
 * JWT opcional al namespace per defecte: si el client envia auth.token, es verifica i es guarda jwtSub.
 * Necessari per reemetre `client:seat_hold_intent` / rollback sense confiar en el userId del cos.
 */
io.use((socket, next) => {
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
});

// Namespace privat (cal abans de /internal/emit per reemetre a user:{id})
const privateNs = io.of('/private');

privateNs.use((socket, next) => {
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
    /* Diagnòstic dev: mismatch JWT_SECRET API vs socket, token caducat (jwt expired), etc. */
    console.warn('[socket-server][private] JWT verify failed:', msg);
    next(new Error('Unauthorized'));
  }
});

function postInternalReleaseUserEvent (userId, eventIdStr) {
  const eid = parseInt(String(eventIdStr), 10);
  const uid = parseInt(String(userId), 10);
  if (Number.isNaN(eid) || eid < 1 || Number.isNaN(uid) || uid < 1) {
    return;
  }
  const base = process.env.LARAVEL_INTERNAL_API_URL || 'http://backend-api:8000';
  const secret = process.env.SOCKET_INTERNAL_SECRET || '';
  const url = String(base).replace(/\/$/, '') + '/api/internal/seat-holds/release-user-event';
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (secret !== '') {
    headers['X-Internal-Secret'] = secret;
  }
  fetch(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ user_id: uid, event_id: eid }),
  }).catch(() => {});
}

privateNs.on('connection', (socket) => {
  const uid = socket.data.userId;
  if (uid) {
    socket.join('user:' + String(uid));
  }
  const pq = socket.handshake.query;
  const eventIdJoin = pq && pq.eventId ? String(pq.eventId) : '';
  if (eventIdJoin !== '') {
    socket.join('event:' + eventIdJoin);
  }
  console.log('[socket-server][private] connection', {
    socketId: socket.id,
    userId: uid ? String(uid) : null,
    eventId: eventIdJoin !== '' ? eventIdJoin : null,
  });
  socket.emit('server:hello', { channel: 'private', at: new Date().toISOString() });

  socket.on('admin:metrics', (payload) => {
    privateNs.emit('admin:metrics', payload);
  });

  socket.on('disconnect', (reason) => {
    console.log('[socket-server][private] disconnect', {
      socketId: socket.id,
      userId: uid ? String(uid) : null,
      reason,
    });
    if (!uid) {
      return;
    }
    if (eventIdJoin === '') {
      return;
    }
    postInternalReleaseUserEvent(uid, eventIdJoin);
  });
});

//================================ HTTP: health + emissió interna + QR SVG (T026)
function requestPathOnly (req) {
  const u = req.url || '';
  const q = u.indexOf('?');
  if (q >= 0) {
    return u.slice(0, q);
  }
  return u;
}

httpServer.on('request', (req, res) => {
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
          // Namespace /private: sala pròpia (JWT); el mapa públic viu al namespace per defecte.
          privateNs.in(room).emit(evt, payload);
          /* Emissió explícita als sockets del namespace per defecte (evita casos rars amb broadcast + multiplex). */
          const defSockets = await io.in(room).fetchSockets();
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
        const width = body.width !== undefined ? Number(body.width) : undefined;
        const margin = body.margin !== undefined ? Number(body.margin) : undefined;
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
});

//================================ Canal públic: room event:{eventId} sense JWT (query ?eventId=)
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
  const anonSession = q && q.anonSession ? String(q.anonSession) : '';
  if (anonSession !== '') {
    socket.join('anon:' + anonSession);
  }
  console.log('[socket-server][public] connection', {
    socketId: socket.id,
    eventId: eventId !== '' ? eventId : null,
    room: eventId !== '' ? 'event:' + eventId : null,
  });
  socket.emit('server:hello', {
    channel: 'public',
    eventId: eventId || null,
    at: new Date().toISOString(),
  });

  socket.on('disconnect', (reason) => {
    console.log('[socket-server][public] disconnect', {
      socketId: socket.id,
      eventId: eventId !== '' ? eventId : null,
      reason,
    });
    const jwtUid = socket.data.jwtSub;
    if (jwtUid && eventId !== '') {
      postInternalReleaseUserEvent(jwtUid, eventId);
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
    let sockEid = '';
    if (q && q.eventId) {
      sockEid = String(q.eventId);
    }
    if (sockEid === '' && ha && ha.eventId) {
      sockEid = String(ha.eventId);
    }
    if (!payload || typeof payload !== 'object') {
      return;
    }
    const pe = payload;
    const eventId = String(pe.eventId || '');
    const seatId = String(pe.seatId || '');
    if (eventId === '' || seatId === '') {
      return;
    }
    if (sockEid !== eventId) {
      return;
    }
    io.to('event:' + eventId).emit('SeatStatusUpdated', {
      eventId,
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
    let sockEid = '';
    if (q && q.eventId) {
      sockEid = String(q.eventId);
    }
    if (sockEid === '' && ha && ha.eventId) {
      sockEid = String(ha.eventId);
    }
    if (!payload || typeof payload !== 'object') {
      return;
    }
    const pe = payload;
    const eventId = String(pe.eventId || '');
    const seatId = String(pe.seatId || '');
    if (eventId === '' || seatId === '') {
      return;
    }
    if (sockEid !== eventId) {
      return;
    }
    io.to('event:' + eventId).emit('SeatStatusUpdated', {
      eventId,
      seatId,
      status: 'available',
      userId: null,
      provisional: true,
    });
  });

  /** Panell admin (T052): el client es subscriu per rebre `admin:metrics` emès al room `admin:dashboard`. */
  socket.on('join:admin-dashboard', () => {
    socket.join('admin:dashboard');
  });
});

const port = Number(process.env.PORT || 3001);
httpServer.listen(port, '0.0.0.0', () => {
  console.log('socket-server escoltant a ' + port);
});
