/**
 * Autenticació de rutes internes (header X-Internal-Secret).
 */

//================================ FUNCIONS PÚBLIQUES ============

export function internalSecretOk (req) {
  const expected = process.env.SOCKET_INTERNAL_SECRET || '';
  const got = req.headers['x-internal-secret'] || '';
  if (expected !== '' && got !== expected) {
    return false;
  }
  return true;
}
