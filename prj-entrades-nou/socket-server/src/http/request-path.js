/**
 * Path sense query string (per routing manual sobre http.Server).
 */

//================================ FUNCIONS PÚBLIQUES ============

export function requestPathOnly (req) {
  const u = req.url || '';
  const q = u.indexOf('?');
  if (q >= 0) {
    return u.slice(0, q);
  }
  return u;
}
