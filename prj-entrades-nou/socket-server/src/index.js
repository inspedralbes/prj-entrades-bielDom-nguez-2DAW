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

//================================ HTTP: health + emissió interna + QR SVG (T026)
httpServer.on('request', (req, res) => {
  if (req.url === '/health' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ status: 'ok', service: 'socket-server' }));
    return;
  }
  if (req.url === '/internal/emit' && req.method === 'POST') {
    if (!internalSecretOk(req)) {
      res.writeHead(403);
      res.end();
      return;
    }
    readJsonBody(req)
      .then((body) => {
        const room = String(body.room || '');
        const evt = String(body.event || '');
        const payload = body.payload;
        if (room !== '' && evt !== '') {
          io.to(room).emit(evt, payload);
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
  if (req.url === '/internal/qr-svg' && req.method === 'POST') {
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
  const eventId = q && q.eventId ? String(q.eventId) : '';
  if (eventId !== '') {
    socket.join('event:' + eventId);
  }
  const anonSession = q && q.anonSession ? String(q.anonSession) : '';
  if (anonSession !== '') {
    socket.join('anon:' + anonSession);
  }
  socket.emit('server:hello', {
    channel: 'public',
    eventId: eventId || null,
    at: new Date().toISOString(),
  });

  socket.on('seat:contention', (payload) => {
    io.to('event:' + String(payload.eventId || '')).emit('seat:contention', payload);
  });

  socket.on('countdown:resync', (payload) => {
    io.to('event:' + String(payload.eventId || '')).emit('countdown:resync', payload);
  });
});

//================================ Namespace privat: JWT a handshake.auth.token (Bearer opcional)
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
    socket.data.userId = decoded.sub;
    next();
  } catch (e) {
    next(new Error('Unauthorized'));
  }
});

privateNs.on('connection', (socket) => {
  const uid = socket.data.userId;
  if (uid) {
    socket.join('user:' + String(uid));
  }
  socket.emit('server:hello', { channel: 'private', at: new Date().toISOString() });

  socket.on('admin:metrics', (payload) => {
    privateNs.emit('admin:metrics', payload);
  });
});

const port = Number(process.env.PORT || 3001);
httpServer.listen(port, '0.0.0.0', () => {
  console.log('socket-server escoltant a ' + port);
});
