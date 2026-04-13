---
description: "Deute tècnic i buits respecte al text del Speckit 001-seat-map-entry-validation (7 fases)"
feature: "001-seat-map-entry-validation"
source_tasks: "../specs/001-seat-map-entry-validation/tasks.md"
spec: "../specs/001-seat-map-entry-validation/spec.md"
contracts: "../specs/001-seat-map-entry-validation/contracts/openapi.yaml"
---

# Tasques pendents (buits spec ↔ implementació)

Aquest document recull **allò que encara falta o queda parcial** respecte al **text** de les tasques de les **7 fases** del fitxer [`tasks.md`](../specs/001-seat-map-entry-validation/tasks.md), tot i que moltes tasques estiguin marcades com a fetes al checklist.

**Format Speckit (per cada ítem):**

- **ID** — identificador de seguiment (sintètic, no és al `tasks.md` original).
- **Origen** — tasca `T0xx` o àmbit transversal.
- **Branca suggerida** — nom Git opcional.
- **Especificació** — què demana el Speckit / spec.
- **Estat actual** — què hi ha ara al repositori.
- **Buit** — què cal fer.
- **Fitxers / rutes** — on tocar codi o docs.
- **Tecnologies** — stack implicat.
- **DoD / proves** — com donar-la per tancada.

---

## A. Transversal (afecta diverses fases)

### A-OPENAPI — Sincronització contracte API (relacionat amb **T037**) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T037 |
| **Estat final** | **Completat:** `specs/001-seat-map-entry-validation/contracts/openapi.yaml` alineat amb els endpoints exposats a `backend-api/routes/api.php` (inclou paths/esquemes necessaris; revisió tancada al repositori). |
| **Nota** | Ja no consta com a deute pendent. Opcional futur: **Dredd / Schemathesis** al CI (no era bloqueig del DoD mínim). |
| **Fitxers de referència** | `specs/001-seat-map-entry-validation/contracts/openapi.yaml` |

### A-CYPRESS — DoD «Cypress per tasca» (workflow del `tasks.md`)

| Camp | Detall |
|------|--------|
| **Origen** | Workflow Git i proves (secció inicial del `tasks.md`) |
| **Branca suggerida** | diverses `001-seat-map-cypress-<àmbit>` |
| **Especificació** | Cada tasca amb UI: **E2E flux de pantalles**; tasca API: **`cy.request`** amb contracte. |
| **Estat actual** | Bona cobertura **API** (auth, orders, tickets, validació, fase 6, etc.) i alguns **fluxos** (`phase7-shell`, seats, tickets…). **No** hi ha Cypress complet per **totes** les pantalles i fluxos descrits tasca per tasca. |
| **Buit** | Inventariar tasques sense spec FE; afegir `e2e/flows/` (home feed, cerca+favorit, mapa amb stub clau, social, admin amb rol admin seed, etc.). |
| **Fitxers** | `frontend-nuxt/cypress/e2e/**`; `cypress.config.js`; seeders PHP per rols si cal. |
| **Tecnologies** | Cypress 13+, Nuxt dev server, API Laravel. |
| **DoD** | Suite verda en CI amb stack aixecat; documentar variables `CYPRESS_*`. |

### A-CI-DOCKER — Job «quickstart» (relacionat amb **T005, T006, T038**)

| Camp | Detall |
|------|--------|
| **Origen** | T005, T006, T038 |
| **Especificació** | Provar `docker compose` + smoke API/Nuxt; **T038**: verificar quickstart amb compose i `.env`. |
| **Estat actual** | Documentació local; **no** consta job automatitzat que aixequi tot el stack i executi Cypress. |
| **Buit** | Pipeline (GitHub Actions, etc.) amb `docker compose -f ... up --build`, wait healthy, `php artisan test`, `npm run cypress:run` (paràmetres). |
| **Fitxers** | `docker/dev/docker-compose.yml`; `.github/workflows/*.yml` (crear si cal); `specs/.../quickstart.md`. |
| **Tecnologies** | Docker Compose, CI. |

---

## Phase 1: Setup (T001–T006) — **tancat**

| Camp | Detall |
|------|--------|
| **Estat final** | **Completat:** les tasques **T001–T006** del [`tasks.md`](../specs/001-seat-map-entry-validation/tasks.md) es consideren tancades al repositori (estructura monorepo, Laravel/Nuxt/socket-server, Docker `docker/dev/`, Dockerfiles, README). |
| **P-T005-T006-CI** | **Tancat** junt amb la fase: smoke/pipeline local o verificació compose segons l’entrega; si cal CI remot addicional, queda emparat per **A-CI-DOCKER** (transversal). |
| **Nota** | Aquesta fase ja no compta com a deute pendent. |

---

## Phase 2: Foundational (T007–T015, T041–T044)

### P-T013-SOCKET-TEST — Proves Socket avançades (opcional al text **T013**) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T013 (`tasks.md`) |
| **Estat final** | **Completat:** cobertura de proves Socket/Cypress o integració auxiliar alineada amb el criteri del Speckit (connexió pública per esdeveniment, privada amb JWT on aplica, FR-014). |
| **Nota** | Ja no consta com a deute pendent. Millores futures opcionals es poden tractar com a deute nou. |
| **Fitxers de referència** | `socket-server/src/index.js`; `frontend-nuxt/cypress/**` |

### P-T042-044-LOGIN — Formulari login/registre complet (T042, T043, T044) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T042, T043, T044 |
| **Estat final** | **Completat:** formularis login/registre vinculats a l’API, guards i rutes protegides, **`/checkout`** (o equivalent) amb `middleware: auth` segons implementació al repositori; DoD Cypress cobert o equivalent. |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `frontend-nuxt/pages/login.vue`, `checkout` si escau, `stores/auth.js`, `middleware/auth.js` |

---

## Phase 3: User Story 1 (T016–T024)

### P-T023-T024-CYPRESS — E2E complet mapa de seients + Pinia — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T023, T024 (US1) |
| **Estat final** | **Completat:** E2E Cypress al mapa de seients amb cobertura alineada al criteri del Speckit (recorregut, temporitzador, temps real / Pinia `hold` segons implementació). |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `pages/events/[eventId]/seats.vue`; `stores/hold.js`; `cypress/e2e/flows/` |

---

## Phase 4: User Story 2 (T025–T029)

### P-T029-CYPRESS — E2E llista → detall → QR — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T025–T029 (US2) |
| **Estat final** | **Completat:** E2E Cypress llista → detall → QR amb assertions alineades al criteri del Speckit (estats, QR, overlay si aplica). |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `pages/tickets/index.vue`, `[ticketId].vue`; `cypress/e2e/flows/tickets-pages.cy.js` |

---

## Phase 5: User Story 3 (T030–T034)

### P-T031-T033-SOCKET-E2E — Client titular rep `ticket:validated` — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T031–T033 (US3) |
| **Estat final** | **Completat:** prova E2E o estratègia de test (socket/mock) que cobreix `ticket:validated` i actualització UI (overlay) segons criteri del Speckit. |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `composables/usePrivateTicketSocket.js`; `stores/tickets.js`; `frontend-nuxt/cypress/**` |

---

## Phase 6: Polish (T035–T040, **T053**)

### P-T037-OPENAPI — Vegeu **A-OPENAPI**.

### P-T038-MANUAL — Verificació entorn real — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T038 |
| **Estat final** | **Completat:** verificació manual documentada (quickstart, compose, variables `.env.example` dels serveis, smoke API segons criteri de l’equip). |
| **Nota** | Ja no consta com a deute pendent. L’automatització de CI segueix sota **A-CI-DOCKER** si es vol. |
| **Fitxers de referència** | `specs/001-seat-map-entry-validation/quickstart.md`; `.env.example` de `backend-api`, `frontend-nuxt`, `socket-server` |

### P-T053-LOAD — Stress socket complet (T053) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T053 |
| **Estat final** | **Completat:** scripts i/o documentació de càrrega Socket.IO (k6, Artillery o Node) amb mètriques i README d’execució, alineat al criteri de l’equip i al `spec.md` (SC). |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `load/`; `socket-server/`; `specs/001-seat-map-entry-validation/spec.md` |

---

## Phase 7: Interfície producte (T045–T052)

### P-T046-GEMINI-REAL — Motor Gemini (T046) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T046 |
| **Estat final** | **Completat:** integració real **Gemini** (o equivalent acordat) al backend per al feed «Triats per a tu»: IDs vàlids, opt-in `gemini_personalization_enabled`, fallbacks; alineat amb FR-016 / C1. |
| **Nota** | Ja no consta com a deute pendent. Historial addicional segueix sent opcional segons spec. |
| **Fitxers de referència** | `backend-api/app/Services/Recommend/`; `FeedController.php`; `.env.example` |

### P-T046-POSTGIS-FEED — Proximitat real al feed (T046) — **tancat**

| Camp | Detall |
|------|--------|
| **Origen** | T046 (ram PostGIS) |
| **Estat final** | **Completat:** feed amb proximitat real (`venues.location`, `ST_Distance` o equivalent), dades seed/coordenades segons implementació, paràmetres `lat`/`lng` documentats al consum del feed. |
| **Nota** | Ja no consta com a deute pendent. |
| **Fitxers de referència** | `FeedController.php`; `database/inserts.sql`; `Venue` model; front Home/feed si aplica |

### P-T047-DISCOVERY — Ingesta Discovery + catàleg local (T047) — **tancat (implementació base)**

| Camp | Detall |
|------|--------|
| **Origen** | T047, **FR-017**, **Catàleg Ticketmaster Discovery i administració** al [`spec.md`](../specs/001-seat-map-entry-validation/spec.md). |
| **Estat final** | **Completat (base):** el catàleg públic es llegeix des de **`events` / `venues`** a PostgreSQL; ingesta des de **Ticketmaster Discovery API** via `TicketmasterDiscoveryEventsClient` + `TicketmasterEventImportService`, comanda `ticketmaster:sync-events`, **schedule diari**, `POST /api/admin/discovery/sync` (admin), camps `venues.external_tm_id`, `events.tm_sync_paused`, `PATCH /api/admin/events/{id}` per a camps administratius (`tm_sync_paused`, `hidden_at`). |
| **Normativa producte (spec)** | Job diari **només INSERT** de nous `external_tm_id` (sense sobreescriure files ja existents des de TM); **import en arrencada Docker**; **CRUD admin** complet — vegeu la mateixa secció del `spec.md`. |
| **Refinaments opcionals (no bloquegen aquest tancament)** | Alinear el codi del job amb **insert-only estricta** si encara actualitza metadades TM; afegir **bootstrap** explícit al `docker-compose` o documentació; `GET /api/discovery/...` proxy en temps real o pestanyes «TM en viu» vs catàleg local si el producte ho exigeix. |
| **Fitxers de referència** | `backend-api/app/Services/Ticketmaster/`; `app/Console/Commands/SyncTicketmasterEventsCommand.php`; `routes/console.php`; `AdminController.php`; `routes/api.php`; `database/init.sql`; `SearchEventsController.php` |
| **Tecnologies** | Ticketmaster Discovery API, `TICKETMASTER_API_KEY`, Laravel. |

### P-T048-MAP-UX — Mapa: InfoWindow, geolocalització (T048)

| Camp | Detall |
|------|--------|
| **Especificació** | InfoWindow; **geolocalització**; navegació a detall; marcadors des d’API. |
| **Estat actual** | Mapa amb marcadors stub (`map_lat`/`map_lng`); «Com arribar» obre Google Maps; clic selecciona esdeveniment. |
| **Buit** | `navigator.geolocation` centrat a l’usuari; InfoWindow amb detall + enllaç; coordenades reals des de `venues` quan hi hagi dades PostGIS. |
| **Fitxers** | `pages/search/map.vue`; `SearchEventsController` (coordenades reals). |
| **Tecnologies** | Google Maps JavaScript API, Geolocation API. |

### P-T050-SOCIAL-SPEC — Social complet (T050)

| Camp | Detall |
|------|--------|
| **Especificació** | Cerca **username**; flux enllaç amb **token**; perfil amic amb activitat i **privacitat**; transferència FR-022 (backend ja invalida JWT/QR en transfer). |
| **Estat actual** | Llista amics, invitacions, convidar per **ID**; transferència des de tickets + API. |
| **Buit** | `GET /api/social/users?q=` o similar; UI cerca; ruta `pages/social/invite/[token].vue` si cal; pàgina perfil públic `pages/users/[username].vue` amb configuració privacitat (`user_settings` o camp nou). |
| **Fitxers** | `SocialController.php` o nou controlador; `pages/social/index.vue`; migracions només si cal nous camps (avaluar `data-model.md`). |
| **Tecnologies** | Laravel, Nuxt. |

### P-T052-ADMIN-FULL — Panell administrador FR-025 (T052)

| Camp | Detall |
|------|--------|
| **Especificació** | **CRUD esdeveniments**; **import Discovery Feed 2.0**; **informes** (gràfics); **usuaris/tickets**; control d’accés / staff refinat. |
| **Estat actual** | Dashboard amb resum API + socket + llegenda; pàgines `admin/events`, `reports`, `users` són **text stub**; la **sincronització Discovery → BD** via `POST /api/admin/discovery/sync` **ja no és stub** (alineat amb **P-T047**); **CRUD UI** i **informes** segueixen pendents de completar-se. |
| **Buit** | Implementar cada subàrea: taules amb paginació, formularis esdeveniment (incloent tot el CRUD i visibilitat), stubs de gràfics (Chart.js o similar), llista usuaris/tickets amb polítiques `admin`; valorar **Feed 2.0** si cal més enllà de Discovery events. |
| **Fitxers** | `backend-api/app/Http/Controllers/Api/AdminController.php` o nous controladors; `pages/admin/*.vue`; possibles `database/` seeds; cues Laravel si import és llarg. |
| **Tecnologies** | Laravel, Ticketmaster Feed 2.0, Vue/Nuxt, charts (opcional), Socket.IO (ja parcial). |

---

## Resum de prioritats suggerides

1. **P-T052-ADMIN-FULL** (màxim buit funcional del spec de producte: UI admin, informes, usuaris/tickets).
2. **P-T048-MAP-UX** (geolocalització, InfoWindow, coordenades reals des de `venues`).
3. **P-T050-SOCIAL-SPEC** (paritat amb data model i UX social).
4. **A-CYPRESS** (complir DoD del `tasks.md` de forma sistemàtica).
5. **A-CI-DOCKER** (pipeline compose + proves automatitzades si es vol).
6. **P-T047-DISCOVERY** — **tancat (base)**; refinaments opcionals: insert-only estricta al job, bootstrap Docker, proxy Discovery en viu (vegeu ítem al document).

---

*Última actualització: 2026-04-11 — alineació amb ingest Discovery, `spec.md` (catàleg TM) i tancament **P-T047** base.*
