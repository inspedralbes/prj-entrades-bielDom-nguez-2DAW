/**
 * Prova d’integració: dos clients Socket.IO al mateix room event:{id};
 * el primer emet client:seat_hold_intent (JWT vàlid) i el segon rep SeatStatusUpdated.
 *
 * Demostra el mateix camí que el navegador (Nuxt) en temps real, sense Laravel.
 *
 * Ús: JWT_SECRET=... node scripts/verify-seat-intent-broadcast.mjs [http://127.0.0.1:3001]
 * Requisit: socket-server en marxa amb JWT_SECRET no buit (sinó jwtSub és null i l’intent s’ignora).
 */
import 'dotenv/config';
import jwt from 'jsonwebtoken';
import { io } from 'socket.io-client';

const base = (process.argv[2] || 'http://127.0.0.1:3001').replace(/\/$/, '');
const eventId = '2';
const seatId = 'section_1-row_5-seat_12';
const userAId = '42';
const userBId = '99';

const jwtSecret = process.env.JWT_SECRET || process.env.APP_KEY || '';

function fail (msg) {
  console.error(msg);
  process.exit(1);
}

if (jwtSecret === '') {
  fail('JWT_SECRET (o APP_KEY) ha d’estar definit i coincidir amb el socket-server.');
}

const tokenA = jwt.sign({ sub: userAId }, jwtSecret, { algorithm: 'HS256' });
const tokenB = jwt.sign({ sub: userBId }, jwtSecret, { algorithm: 'HS256' });

const common = {
  query: { eventId },
  transports: ['websocket', 'polling'],
  timeout: 12000,
};

function disconnect (sock) {
  if (!sock) {
    return;
  }
  try {
    sock.removeAllListeners();
    sock.disconnect();
  } catch {
    /* ignore */
  }
}

function waitConnect (label, token) {
  return new Promise((resolve, reject) => {
    const s = io(base, {
      ...common,
      auth: { eventId, token },
    });
    const t = setTimeout(() => {
      disconnect(s);
      reject(new Error('timeout connect ' + label));
    }, 15000);
    s.on('connect', () => {
      clearTimeout(t);
      resolve(s);
    });
    s.on('connect_error', (err) => {
      clearTimeout(t);
      let msg = 'connect_error';
      if (err && err.message) {
        msg = err.message;
      }
      disconnect(s);
      reject(new Error(label + ': ' + msg));
    });
  });
}

async function main () {
  /** @type {import('socket.io-client').Socket | null} */
  let clientB = null;
  /** @type {import('socket.io-client').Socket | null} */
  let clientA = null;

  const timer = setTimeout(() => {
    disconnect(clientA);
    disconnect(clientB);
    fail('Timeout: el segon client no ha rebut SeatStatusUpdated (intent → room).');
  }, 20000);

  try {
    clientB = await waitConnect('clientB', tokenB);

    let received = false;
    clientB.on('SeatStatusUpdated', (payload) => {
      if (!payload || typeof payload !== 'object') {
        return;
      }
      if (String(payload.eventId) !== eventId) {
        return;
      }
      if (String(payload.seatId) !== seatId) {
        return;
      }
      if (payload.status !== 'held') {
        return;
      }
      if (String(payload.userId) !== userAId) {
        return;
      }
      received = true;
    });

    clientA = await waitConnect('clientA', tokenA);

    clientA.emit('client:seat_hold_intent', {
      eventId: String(eventId),
      seatId: String(seatId),
    });

    const start = Date.now();
    while (!received && Date.now() - start < 18000) {
      await new Promise((r) => {
        setTimeout(r, 50);
      });
    }

    if (!received) {
      clearTimeout(timer);
      fail('El client B no ha rebut SeatStatusUpdated esperat (held, userId=' + userAId + ').');
    }

    clearTimeout(timer);
    console.log('OK: dos clients al room event:' + eventId + ' — intent reemet SeatStatusUpdated al company.');
    disconnect(clientA);
    disconnect(clientB);
    process.exit(0);
  } catch (e) {
    clearTimeout(timer);
    disconnect(clientA);
    disconnect(clientB);
    let m = String(e);
    if (e && e.message) {
      m = e.message;
    }
    fail(m);
  }
}

main();
