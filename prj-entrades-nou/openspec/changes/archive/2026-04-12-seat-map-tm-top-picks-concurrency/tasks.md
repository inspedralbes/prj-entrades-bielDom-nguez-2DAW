# Tasks — seat-map-tm-top-picks-concurrency

Orden suggerit (dependències entre parèntesis).

## Discovery i contracte

- [x] **T001** — Inventariar endpoints TM Top Picks necessaris i mapping cap a `event_id` intern / `ticketmaster_event_id` (llegir `TicketmasterEventImportService`, models `Event`/`Venue`).
- [x] **T002** — Escriure contracte OpenAPI delta: `GET /api/events/{eventId}/seat-map` (proxy mapa), payloads de zones i disponibilitat; ampliar `POST` de hold/reserva si cal (referència `001-seat-map-entry-validation`).

## Backend — proxy Top Picks

- [x] **T003** — Implementar `TicketmasterTopPicksClient` (HTTP server-to-server, timeout, errors); **no** exposar clau al JSON de resposta. → Ja existeix `TicketmasterSeatmapClient`
- [x] **T004** — `SeatMapController` o ampliació de controlador existent: retornar `snapshotImageUrl` + zones amb geometria i `availability` per zona. → Ja existeix `SeatmapController`
- [x] **T005** — Tests feature: resposta 200 amb fixture TM o mock HTTP; 404 si esdeveniment sense dades TM. → Ja implementat

## Backend — concurrencia i Redis 240 s

- [x] **T006** — Revisar flux actual de hold; assegurar `SELECT … FOR UPDATE` en la transacció de reserva de seient. → Ja implementat amb `lockForUpdate()` (linia 78 SeatHoldService.php)
- [x] **T007** — Configurar TTL **240** s a Redis per claus de hold (constant `config` o env `SEAT_HOLD_TTL_SECONDS=240`). → Ja configurat: `$event->hold_ttl_seconds = 240` al TM sync
- [x] **T008** — Validació **màxim 6** seients per usuari/sessió al mateix esdeveniment (o comanda); retorn 422 amb codi estable. → Ja implementat (linia 45-47 SeatHoldService.php)
- [x] **T009** — Tests: dos requests concurrents simulats → un 200/204 i l'altre conflicte; assert fila PG coherent.

## Redis → Socket

- [x] **T010** — Després de canvi d'estat de seient (hold / release / sold), `PUBLISH` al canal ja usat pel projecte (o documentar canal nou). → Ja implementat via `InternalSocketNotifier.emitToEventRoom()`
- [x] **T011** — `socket-server`: consumir missatge i fer `emit` a `event:{eventId}` (o convenció existent). → Ja implementat
- [x] **T012** — Esdeveniment específic per **conflicte** amb text català acordat (payload + test integració lleuger si hi ha harness). → Ja implementat: `seat:contention` amb missatge "Aquest seient acaba de ser seleccionat per un altre usuari"

## Frontend — Nuxt

- [x] **T013** — Pàgina o refactor `pages/events/[eventId]/seats.vue` (o ruta nova `.../map`): carregar JSON del proxy, renderitzar imatge + SVG. → Nova pàgina seatmap.vue
- [x] **T014** — Implementar zoom per zona (estat Vue) i càrrega de rejilla de seients des d'API interna.
- [x] **T015** — Zona amb `availability === 0`: estil vermell + desactivar clic (sense `.map`/`filter` prohibits; usar bucles `for`).
- [x] **T016** — Subscripció `socket.io-client` a room d'esdeveniment; actualitzar colors de seients i rebre missatge de conflicte (toast o inline).

## Tancament

- [x] **T017** — Actualitzar `openspec/specs/` o sync delta segons workflow del repo després d'implementació verificada.
- [x] **T018** — Cypress o E2E mínim: obrir mapa mock i veure capa SVG (si CI ho permet).