/**
 * Prova E2E: POST /internal/emit (held + sold) i recepció al client Socket.IO (namespace per defecte, room event:{id}).
 * Requisit: socket-server en marxa. SOCKET_INTERNAL_SECRET del .env s’aplica automàticament (dotenv).
 *
 * Ús: node scripts/verify-internal-emit.mjs [http://127.0.0.1:3001]
 */
import 'dotenv/config';
import { io } from 'socket.io-client';

const base = (process.argv[2] || 'http://127.0.0.1:3001').replace(/\/$/, '');
const eventId = '2';
const room = `event:${eventId}`;
const seatHeld = 'section_1-row_1-seat_21';
const seatSold = 'section_1-row_1-seat_22';

const socket = io(base, {
  query: { eventId },
  transports: ['polling', 'websocket'],
  timeout: 8000,
});

const seen = { held: false, sold: false };

function fail (msg) {
  console.error(msg);
  try {
    socket.disconnect();
  } catch {
    /* ignore */
  }
  process.exit(1);
}

const timer = setTimeout(() => {
  fail('Timeout: falta held=' + seen.held + ' sold=' + seen.sold);
}, 12000);

socket.on('SeatStatusUpdated', (payload) => {
  if (!payload) {
    return;
  }
  if (String(payload.eventId) !== eventId) {
    return;
  }
  if (payload.status === 'held' && payload.seatId === seatHeld) {
    seen.held = true;
  }
  if (payload.status === 'sold' && payload.seatId === seatSold) {
    seen.sold = true;
  }
  if (seen.held && seen.sold) {
    clearTimeout(timer);
    console.log('OK: SeatStatusUpdated held + sold rebuts al client (room ' + room + ').');
    socket.disconnect();
    process.exit(0);
  }
});

socket.on('connect_error', (err) => {
  clearTimeout(timer);
  fail('connect_error: ' + (err && err.message ? err.message : String(err)));
});

async function postEmit (payload) {
  const url = `${base}/internal/emit`;
  const body = JSON.stringify({
    room,
    event: 'SeatStatusUpdated',
    payload,
  });
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  const sec = process.env.SOCKET_INTERNAL_SECRET || '';
  if (sec !== '') {
    headers['X-Internal-Secret'] = sec;
  }
  const res = await fetch(url, {
    method: 'POST',
    headers,
    body,
  });
  if (res.status !== 204) {
    const t = await res.text();
    fail('POST ' + url + ' ha retornat ' + res.status + ': ' + t);
  }
}

socket.on('connect', async () => {
  try {
    await postEmit({
      eventId: String(eventId),
      seatId: seatHeld,
      status: 'held',
      userId: '999',
    });
    await postEmit({
      eventId: String(eventId),
      seatId: seatSold,
      status: 'sold',
      userId: null,
    });
  } catch (e) {
    clearTimeout(timer);
    fail('fetch error: ' + (e && e.message ? e.message : String(e)));
  }
});
