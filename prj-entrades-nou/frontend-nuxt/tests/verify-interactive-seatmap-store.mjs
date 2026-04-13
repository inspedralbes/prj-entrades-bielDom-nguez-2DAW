/**
 * Verificació sense navegador: Pinia applySocketUpdate + claus string (mateixa lògica que usePrivateSeatmapSocket).
 * Ús (des de frontend-nuxt): node tests/verify-interactive-seatmap-store.mjs
 */
import { createPinia, setActivePinia } from 'pinia';
import { useInteractiveSeatmapStore } from '../stores/interactiveSeatmap.js';

function fail (msg) {
  console.error(msg);
  process.exit(1);
}

setActivePinia(createPinia());
const store = useInteractiveSeatmapStore();

const eid = '7';
const sid = 'section_1-row_3-seat_10';
const holderUserId = '55';

store.bootstrapFromApi(
  {
    seat_layout: {},
    redis_holds: {},
  },
  eid,
);
store.setCurrentUserId('66');

store.applySocketUpdate({
  eventId: eid,
  seatId: sid,
  status: 'held',
  userId: holderUserId,
});

const got = store.heldBySeatId[sid];
if (got !== holderUserId) {
  fail('heldBySeatId[' + sid + '] esperat ' + holderUserId + ', obtingut ' + String(got));
}

store.applySocketUpdate({
  eventId: eid,
  seatId: sid,
  status: 'available',
  userId: null,
});

if (store.heldBySeatId[sid] !== undefined) {
  fail('Després de available el hold hauria d’haver desaparegut.');
}

store.bootstrapFromApi(
  {
    seat_layout: {},
    redis_holds: { [sid]: holderUserId },
  },
  eid,
);

const got2 = store.heldBySeatId[sid];
if (got2 !== holderUserId) {
  fail('bootstrap redis_holds: esperat ' + holderUserId + ', obtingut ' + String(got2));
}

console.log('OK: interactiveSeatmap store (socket held / available / bootstrap).');
