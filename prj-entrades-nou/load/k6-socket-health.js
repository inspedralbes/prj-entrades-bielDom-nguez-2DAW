/**
 * T053 — càrrega HTTP bàsica contra el socket-server (GET /health).
 * Executar: k6 run prj-entrades-nou/load/k6-socket-health.js
 * Variable: SOCKET_URL (per defecte http://localhost:3001)
 */
import http from 'k6/http';
import { check, sleep } from 'k6';

const base = __ENV.SOCKET_URL || 'http://localhost:3001';

export const options = {
  stages: [
    { duration: '10s', target: 20 },
    { duration: '20s', target: 50 },
    { duration: '10s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<500'],
  },
};

export default function () {
  const res = http.get(`${base.replace(/\/$/, '')}/health`);
  check(res, {
    'status 200': (r) => r.status === 200,
  });
  sleep(0.05);
}
