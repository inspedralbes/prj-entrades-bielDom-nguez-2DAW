/**
 * Llegeix el cos JSON d’una petició HTTP (chunks sense .map).
 */

//================================ FUNCIONS PÚBLIQUES ============

export function readJsonBody (req) {
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
