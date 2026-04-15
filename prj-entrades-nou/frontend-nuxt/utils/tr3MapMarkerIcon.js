/**
 * Icona de marcador TR3 (pastilla groga + punt) per Google Maps Marker.
 * Retorna data URL SVG (sense .map() / ternaris als consumidors).
 */

function escapeXmlText (raw) {
  if (typeof raw !== 'string') {
    return '';
  }
  let out = '';
  for (let i = 0; i < raw.length; i++) {
    const c = raw.charAt(i);
    if (c === '&') {
      out += '&amp;';
      continue;
    }
    if (c === '<') {
      out += '&lt;';
      continue;
    }
    if (c === '>') {
      out += '&gt;';
      continue;
    }
    if (c === '"') {
      out += '&quot;';
      continue;
    }
    out += c;
  }
  return out;
}

/**
 * Escurça el text de la pastilla perquè càpiga al SVG.
 */
export function tr3MapMarkerLabel (name) {
  if (!name || typeof name !== 'string') {
    return '·';
  }
  const t = name.trim();
  if (t === '') {
    return '·';
  }
  if (t.length <= 12) {
    return t;
  }
  let short = '';
  for (let i = 0; i < 10; i++) {
    short += t.charAt(i);
  }
  return `${short}…`;
}

/**
 * Genera data:image/svg+xml per usar com a Marker#icon.url
 */
export function buildTr3EventMarkerDataUrl (eventName) {
  const label = tr3MapMarkerLabel(eventName);
  const safe = escapeXmlText(label);
  const w = 112;
  const h = 52;
  const cx = w / 2;
  const dotY = h - 10;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><rect x="4" y="2" rx="14" width="${w - 8}" height="24" fill="#f7e628"/><text x="${cx}" y="18" text-anchor="middle" font-size="9" font-weight="700" fill="#6e6600" font-family="Inter,system-ui,sans-serif">${safe}</text><circle cx="${cx}" cy="${dotY}" r="8" fill="#f7e628" stroke="#131313" stroke-width="2"/><circle cx="${cx}" cy="${dotY}" r="2.5" fill="#6e6600"/></svg>`;
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}
