/**
 * T053 — Socket.IO load test
 * Executar: node load/socket-load-test.js
 */
import { io } from 'socket.io-client';

const SOCKET_URL = process.env.SOCKET_URL || 'http://localhost:3001';
const JWT_SECRET = process.env.JWT_SECRET || 'tr3_dev_jwt_hs256_minimum_key_length_32_bytes_ok';
import jwt from 'jsonwebtoken';

function generateJWT(userId) {
  return jwt.sign({ sub: userId }, JWT_SECRET, { expiresIn: '1h' });
}

async function testPublicChannel(connections = 50) {
  console.log(`\n=== Testing public channel: ${connections} connections ===`);
  const sockets = [];
  const start = Date.now();

  for (let i = 0; i < connections; i++) {
    const socket = io(SOCKET_URL, {
      query: { eventId: `load-test-${i}` },
      transports: ['websocket'],
    });
    sockets.push(
      new Promise((resolve, reject) => {
        socket.on('connect', () => {
          socket.emit('server:hello', {});
          resolve(socket);
        });
        socket.on('connect_error', reject);
      })
    );
  }

  await Promise.all(sockets);
  const elapsed = Date.now() - start;
  console.log(`Connected ${connections} in ${elapsed}ms`);

  for (const s of sockets) {
    s.disconnect();
  }
  return { connections, elapsed };
}

async function testPrivateChannel(connections = 25) {
  console.log(`\n=== Testing private channel with JWT: ${connections} connections ===`);
  const sockets = [];
  const start = Date.now();

  for (let i = 0; i < connections; i++) {
    const token = generateJWT(`load-user-${i}`);
    const socket = io(`${SOCKET_URL}/private`, {
      auth: { token },
      transports: ['websocket'],
    });
    sockets.push(
      new Promise((resolve, reject) => {
        socket.on('connect', () => {
          resolve(socket);
        });
        socket.on('connect_error', reject);
      })
    );
  }

  await Promise.all(sockets);
  const elapsed = Date.now() - start;
  console.log(`Authenticated ${connections} in ${elapsed}ms`);

  for (const s of sockets) {
    s.disconnect();
  }
  return { connections, elapsed };
}

async function main() {
  console.log('Socket.IO Load Test (T053)');
  console.log(`Target: ${SOCKET_URL}`);

  const results = [];

  try {
    const publicResult = await testPublicChannel(50);
    results.push(publicResult);
  } catch (e) {
    console.error('Public test failed:', e.message);
  }

  try {
    const privateResult = await testPrivateChannel(25);
    results.push(privateResult);
  } catch (e) {
    console.error('Private test failed:', e.message);
  }

  console.log('\n=== Results ===');
  results.forEach(r => {
    console.log(`${r.connections} connections in ${r.elapsed}ms`);
  });
}

main();