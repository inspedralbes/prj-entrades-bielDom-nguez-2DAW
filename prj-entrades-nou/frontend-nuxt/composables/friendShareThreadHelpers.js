//================================ FUNCIONS PÚBLIQUES
// Formatació i classes per missatges del fil social (sense mètodes d’array com a bucle principal).

export function friendChatRowClass (m) {
  const p = m.payload;
  if (!p || typeof p !== 'object') {
    return 'friend-chat__row--in';
  }
  if (p.direction === 'sent') {
    return 'friend-chat__row--out';
  }
  return 'friend-chat__row--in';
}

export function friendChatBubbleClass (m) {
  const p = m.payload;
  if (!p || typeof p !== 'object') {
    return 'friend-chat__bubble--in';
  }
  if (p.direction === 'sent') {
    return 'friend-chat__bubble--out';
  }
  return 'friend-chat__bubble--in';
}

export function friendChatKindLabel (m) {
  if (m.type === 'ticket_shared') {
    return 'Entrada';
  }
  if (m.type === 'event_shared') {
    return 'Esdeveniment';
  }
  return 'Compartit';
}

export function friendChatSpeakerLabel (m, peerUsername) {
  const p = m.payload;
  if (p && p.direction === 'sent') {
    return 'Tu';
  }
  let u = peerUsername;
  if (p && typeof p.actor_username === 'string' && p.actor_username.trim() !== '') {
    u = p.actor_username.trim();
  }
  if (u === '') {
    return '@amic';
  }
  return '@' + u;
}

export function friendChatEventTitle (m) {
  const p = m.payload;
  if (!p || typeof p.event_name !== 'string') {
    return 'Esdeveniment';
  }
  if (p.event_name.trim() === '') {
    return 'Esdeveniment';
  }
  return p.event_name;
}

export function friendChatEventVenueLine (m) {
  const p = m.payload;
  if (!p) {
    return '';
  }
  const parts = [];
  if (typeof p.venue_name === 'string' && p.venue_name.trim() !== '') {
    parts.push(p.venue_name.trim());
  }
  if (typeof p.venue_city === 'string' && p.venue_city.trim() !== '') {
    parts.push(p.venue_city.trim());
  }
  if (parts.length === 0) {
    return '';
  }
  let out = parts[0];
  let i = 1;
  for (; i < parts.length; i += 1) {
    out = out + ' · ' + parts[i];
  }
  return out;
}

export function friendChatEventDetailHref (m) {
  const p = m.payload;
  let id = '';
  if (p && p.event_id !== undefined && p.event_id !== null) {
    id = String(p.event_id);
  }
  return '/events/' + encodeURIComponent(id) + '?from=social';
}

export function friendChatTicketTitle (m) {
  const p = m.payload;
  if (!p) {
    return 'Entrada';
  }
  if (typeof p.description === 'string' && p.description.trim() !== '') {
    return p.description;
  }
  if (typeof p.event_name === 'string' && p.event_name.trim() !== '') {
    return p.event_name;
  }
  return 'Entrada';
}

export function friendChatTicketSub (m) {
  const p = m.payload;
  if (!p) {
    return '';
  }
  if (typeof p.venue_name === 'string' && p.venue_name.trim() !== '') {
    return p.venue_name;
  }
  return '';
}

export function friendChatTicketDetailHref (m) {
  const p = m.payload;
  let id = '';
  if (p && p.ticket_id !== undefined && p.ticket_id !== null) {
    id = String(p.ticket_id);
  }
  return '/tickets/' + encodeURIComponent(id);
}

export function friendChatFormatWhen (iso) {
  if (!iso || typeof iso !== 'string') {
    return '';
  }
  let d = null;
  try {
    d = new Date(iso);
  } catch {
    return '';
  }
  if (Number.isNaN(d.getTime())) {
    return '';
  }
  const now = new Date();
  function pad (n) {
    if (n < 10) {
      return '0' + String(n);
    }
    return String(n);
  }
  const day = pad(d.getDate());
  const mo = pad(d.getMonth() + 1);
  const h = pad(d.getHours());
  const mi = pad(d.getMinutes());
  let out = day + '/' + mo;
  if (d.getFullYear() !== now.getFullYear()) {
    out = out + '/' + String(d.getFullYear());
  }
  out = out + ' · ' + h + ':' + mi;
  return out;
}
