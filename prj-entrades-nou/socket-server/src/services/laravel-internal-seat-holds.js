/**
 * Crides HTTP internes cap a Laravel (alliberament de holds en desconnexió).
 * A. Validació IDs · B. URL i headers · C. POST fire-and-forget
 */

//================================ FUNCIONS PÚBLIQUES ============

export function postReleaseUserEvent (userId, eventIdStr) {
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
