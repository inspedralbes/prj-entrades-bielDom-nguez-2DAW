---
description: "Task list for seat map, holds, secure tickets, validation (001-seat-map-entry-validation)"
---

# Tasks: Mapa de seients, bloquejos, entrades segures i validació

**Input**: Design documents from `/specs/001-seat-map-entry-validation/`  
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [data-model.md](./data-model.md), [contracts/openapi.yaml](./contracts/openapi.yaml), [research.md](./research.md), [quickstart.md](./quickstart.md)

**Tests**: **Obligatori per tasca** (vegeu **Workflow Git i proves** més avall): una **branca dedicada**, **Cypress** per al **backend** (`cy.request` contra l’API Laravel, alineat amb [contracts/openapi.yaml](./contracts/openapi.yaml) i checklists Speckit) i, si la tasca toca **UI**, proves de **flux de pantalles** (navegació, dades visibles, estats d’error) al **frontend** (mateixa suite Cypress al monorepo, p. ex. sota `frontend-nuxt/cypress/e2e/` o carpeta acordada al projecte).

**Organization**: Tasques agrupades per user story (US1 P1, US2 P2, US3 P3) amb camins del monorepo **`backend-api/`**, **`frontend-nuxt/`**, **`socket-server/`**, **`database/`**, **`docker/`**.

## Branca base `dev` i Git Flow

- **Branca base d’integració del repositori**: **`dev`**. Tot el desenvolupament parteix d’aquí (rol equivalent a *develop* en **Git Flow**).
- **Origen de les branques**: qualsevol branca de treball —feature del Speckit (`001-seat-map-entry-validation` o nom acordat), **branques per tasca** (`001-seat-map-tNNN-<slug>`, etc.)— es **crea des de `dev`** actualitzat (`git checkout dev && git pull`, després `git checkout -b <nom>`).
- **Integració**: els canvis entren a **`dev`** només via **pull request** (revisió, CI, DoD amb Cypress quan apliqui), **mai** push directe a `dev` en flux professional.
- **Producció (opcional Git Flow)**: una branca **`main`** (o `master`) pot allotjar **releases** estables; `release/*` i `hotfix/*` segons política de l’equip; **`dev`** és el flux continu d’integració fins a release.
- El nom **Speckit** del feature (`001-seat-map-entry-validation`) identifica l’especificació; la branca Git pot coincidir o usar prefix `feature/` — l’important és **sempre** bifurcar des de **`dev`**.

## Workflow Git i proves (per cada tasca)

1. **Branca**: amb **`dev`** actualitzat, crear només la branca de la tasca **`001-seat-map-tNNN-<slug-curt>`** (ex.: `001-seat-map-t016-post-holds`). Opcionalment usar l’script Speckit de branques ([speckit-git-feature](../../.cursor/skills/speckit-git-feature/SKILL.md)) adaptant el nom i el punt de partida **`dev`**; obrir **PR cap a `dev`** quan la tasca passi DoD (no merge directe sense revisió si el flux ho exigeix).
2. **Cypress — backend**: escriure o actualitzar specs que validin **HTTP** (status, cos JSON, errors 4xx/5xx rellevants) contra l’API en entorn de prova (`NUXT_PUBLIC_API_URL` / `APP_URL` segons configuració).
3. **Cypress — frontend (flux de pantalles)**: per tasques amb canvis a `frontend-nuxt/`, proves E2E que recorrin les **rutes afectades**, comprovin **informació** (textos, llistats, estats de botons) i **navegació** coherent amb [spec.md](./spec.md); si la tasca és només infraestructura sense UI encara, deixar **test stub** (`it.skip` amb motiu) o **smoke** mínim documentat al PR fins que existeixin pantalles.
4. **DoD**: PR de la tasca inclou implementació + **Cypress verd** (o justificació escrita si la tasca és només docs/estructura buida, amb test de regressió afegit a la tasca següent que desbloquegi UI/API).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paral·lelitzable (fitxers diferents, sense dependències bloquejades)
- **[USn]**: User story del [spec.md](./spec.md)

## Path Conventions (aquesta feature)

- API: `backend-api/`
- Frontend: `frontend-nuxt/`
- Temps real / QR Node: `socket-server/`
- SQL inicial: `database/init.sql` + `database/inserts.sql` (sense migracions Laravel per al DDL)
- Docker: `docker/dev/`, `docker/prod/`, `docker/dockerfiles/`
- **Cypress**: una carpeta al monorepo (p. ex. `frontend-nuxt/cypress/` o `e2e/` a l’arrel) amb subcarpetes `e2e/api/` i `e2e/flows/` — concretar al primer PR que afegeixi Cypress

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Estructura de repositori i projectes buits segons [plan.md](./plan.md)

- [X] T001 Crear carpetes del monorepo a l’arrel del repo: `backend-api/`, `frontend-nuxt/`, `socket-server/`, `database/`, `docker/dev/`, `docker/prod/`, `docker/dockerfiles/backend-api/`, `docker/dockerfiles/frontend-nuxt/`, `docker/dockerfiles/socket-server/`, `docs/` — **Branca:** `001-seat-map-t001-monorepo-dirs` · **Cypress:** preparar esquelet `cypress.config` + prova buida o script que verifiqui existència de paths (sense flux UI)
- [X] T002 [P] Inicialitzar projecte Laravel 11 (PHP 8.3) a `backend-api/` (`composer create-project laravel/laravel` o equivalent) i afegir `backend-api/.env.example` amb `DB_*`, `REDIS_*`, `TICKETMASTER_API_KEY`, secrets JWT — **Branca:** `001-seat-map-t002-laravel-init` · **Cypress:** `cy.request` GET `/` o ruta `up` de salut si existeix; error esperat acceptable documentat
- [X] T003 [P] Inicialitzar Nuxt 3 + Pinia a `frontend-nuxt/` (`npx nuxi init` o equivalent) i `frontend-nuxt/.env.example` amb `NUXT_PUBLIC_API_URL`, `NUXT_PUBLIC_SOCKET_URL`, `NUXT_PUBLIC_GOOGLE_MAPS_KEY` — **Branca:** `001-seat-map-t003-nuxt-init` · **Cypress:** visita a la home Nuxt (smoke) quan el dev server estigui disponible al pipeline
- [X] T004 [P] Inicialitzar `socket-server/package.json` (Node 20) amb dependències `socket.io@4.7`, `node-qrcode@1.5`, client Redis; `socket-server/.env.example` amb `REDIS_URL` i claus JWT compartides (documentades, no hardcode) — **Branca:** `001-seat-map-t004-socket-pkg` · **Cypress:** test d’integració lleuger (HTTP health del socket-server si n’hi ha) o `cy.task` ping Redis en entorn de prova
- [X] T005 Afegir `docker/dev/docker-compose.yml` que aixequi Postgres 16 (PostGIS), Redis 7.2 i serveis `backend-api`, `frontend-nuxt`, `socket-server` amb build des de `docker/dockerfiles/*/Dockerfile` segons [plan.md](./plan.md) — **Branca:** `001-seat-map-t005-docker-compose` · **Cypress:** job separat o `before` que aixequi stack i després smoke API + (opcional) visita Nuxt
- [X] T006 [P] Esquelet `docker/dockerfiles/backend-api/Dockerfile`, `frontend-nuxt/Dockerfile`, `socket-server/Dockerfile` i `docker/dev/README.md` amb ordres `docker compose build/up` — **Branca:** `001-seat-map-t006-dockerfiles` · **Cypress:** repetir smoke de T005 després de build

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema de dades, Redis, auth i esquelets abans de cap història d’usuari

**⚠️ CRITICAL**: Cap treball de US1–US3 fins completar aquesta fase

- [X] T007 Mantenir l’esquema a **`database/init.sql`** + **`database/inserts.sql`** (extensions PostGIS, taula **`users`** amb **`username`** únic, taules Spatie, `venues` (PostGIS), `events` (`hold_ttl_seconds`, `seat_layout` JSONB, …), `saved_events` / `user_settings`, **`friend_invites`**, **`ticket_transfers`**, `zones`, `seats`, `orders`, `order_lines`, `tickets`, …) i paritat de tests a **`database/testing/schema.sqlite.sql`** — **Branca:** `001-seat-map-t007-migrations` · **Cypress:** `cy.request` amb token si cal + assert taules exposades via API quan T012/T042 existeixin
- [X] T008 [P] Configurar connexió Redis a `backend-api/config/database.php` i servei injectable `App\Services\RedisService` o equivalent per claus `hold:{eventId}:{holdUuid}` — **Branca:** `001-seat-map-t008-redis-service` · **Cypress:** test que creï/llegeixi clau de prova via endpoint de dev o `cy.task` Redis
- [X] T009 [P] Implementar rols **Usuari** (per defecte), **Validador** i **Administrador** via `spatie/laravel-permission` o polítiques Laravel a `backend-api/app/Models/User.php` + `database/seeders/RoleSeeder.php` (noms interns `user`/`customer`, `validator`, `admin`) — **Branca:** `001-seat-map-t009-roles` · **Cypress:** després de login seed, `cy.request` `/api/auth/me` amb rols esperats
- [X] T010 Crear `backend-api/app/Services/Ticketmaster/TicketmasterSeatmapClient.php` (crida HTTP Top Picks amb `TICKETMASTER_API_KEY`) retornant `snapshotImageUrl` + zones; maneig d’errors cap a fallback — **Branca:** `001-seat-map-t010-tm-seatmap-client` · **Cypress:** mock/stub TM o assert fallback `GET /api/events/{id}/seatmap` (si T012 llest)
- [X] T011 Crear `backend-api/app/Services/Seatmap/PostgresSeatmapFallbackService.php` que llegeixi zones/seients des de PostgreSQL (PostGIS `sort_order` / proximitat segons spec) quan Top Picks falli — **Branca:** `001-seat-map-t011-pg-seatmap-fallback` · **Cypress:** `GET /api/events/{id}/seatmap` amb dades seed i TM fallant (flag env)
- [X] T012 Implementar `backend-api/app/Http/Controllers/Api/SeatmapController.php` + ruta `GET /api/events/{eventId}/seatmap` fusionant Top Picks o fallback (contracte [contracts/openapi.yaml](./contracts/openapi.yaml)) — **Branca:** `001-seat-map-t012-seatmap-api` · **Cypress:** **API** — schema `SeatmapResponse`; **FE** — quan hi hagi pàgina de mapa, carregar snapshot i zones visibles
- [X] T013 Esquelet `socket-server/src/index.ts` (o `.js`): **Socket.IO híbrid** ([spec.md](./spec.md) **FR-014**): **Lectura pública** — qualsevol client pot subscriure’s a **rooms/namespace per `eventId`** i rebre **broadcast** d’estat de seients (disponible / reservat / venut) **sense JWT**; **Escriptura protegida** — els **hold** i la **compra** es fan només per **API REST Laravel** amb JWT (no per missatges Socket arbitraris); **`io.use()`** valida **JWT** al handshake abans d’entrar a **rooms privades** (`user:{id}`, transacció/notificacions, **Validador**, **Administrador**); Redis pub/sub si cal; handlers `seat:contention`, `countdown:resync`, `ticket:validated`, `admin:metrics` — **Branca:** `001-seat-map-t013-socket-hybrid` · **Cypress:** test Socket amb `cypress-plugin-socket-io` o app auxiliar; mínim: connexió pública rep event de test + connexió privada rebutjada sense JWT
- [X] T014 [P] Configurar client HTTP des de `frontend-nuxt/` cap a API (`composables/useApi.ts` o `plugins/api.client.ts`) amb `NUXT_PUBLIC_API_URL` — **Branca:** `001-seat-map-t014-api-client` · **Cypress:** flux que cridi API des del navegador i mostri dades (smoke)
- [X] T015 Documentar intercanvi de secrets JWT entre `backend-api` i `socket-server` a `docs/` o `socket-server/README.md` (mateixa clau signatura per payload QR) — **Branca:** `001-seat-map-t015-jwt-docs` · **Cypress:** cap canvi funcional; verificar en CI que els tests T013/T025 segueixen passant

- [X] T041 [P] Instal·lar i configurar **JWT d’API** a `backend-api/` (paquet acordat al pla); publicar configuració; `JWT_SECRET` a `.env.example` — **Branca:** `001-seat-map-t041-jwt-package` · **Cypress:** preparar comandes `login` compartides per specs següents
- [X] T042 Implementar `backend-api/app/Http/Controllers/Api/AuthController.php` (o controladors separats) amb **Register**, **Login**, **Me** i, si escau, **refresh** / **logout**; registrar rutes a `routes/api.php` amb middleware adequat — **Branca:** `001-seat-map-t042-auth-controller` · **Cypress:** **API** — contracte OpenAPI Auth; **FE** — formulari login/registre si existeix pàgina
- [X] T043 [P] Crear `frontend-nuxt/stores/auth.ts` (Pinia) per **token JWT** i **perfil mínim**; **`stores/hold.ts`** per seients seleccionats i estat pre-confirmació ([spec.md](./spec.md) **FR-010**); persistència segura del token (p. ex. memòria + `useCookie` segons política); composable `useAuth` per cridar `/api/auth/me` després del login — **Branca:** `001-seat-map-t043-pinia-auth-hold` · **Cypress:** flux login + persistència token + crida `/me`
- [X] T044 [P] Afegir **guards de navegació** Nuxt 3 (`middleware/auth.ts`, `guest.ts` o equivalent): abans d’entrar a les rutes **`/tickets`**, **`/social`**, **`/checkout`** (i també **Guardats** i **Perfil**), verificar amb **Pinia** (`stores/auth`) que existeixi **JWT vàlid**; si no n’hi ha o és invàlid, **redirigir a `/login`** (o ruta equivalent); alinear prefixos amb `pages/` reals del projecte — **Branca:** `001-seat-map-t044-route-guards` · **Cypress:** **FE** — visitar rutes protegides sense token → login; amb token → accés

**Checkpoint**: Base de dades migrable (incloent `users`), API amb auth JWT, Redis connectat, Socket.IO amb handshake JWT, rols comprador/validador/admin definits, client amb store auth i middleware de rutes

---

## Phase 3: User Story 1 — Selecció de seients, mapa i hold (Priority: P1)

**Goal**: Mapa (Top Picks o fallback), hold atòmic fins a 6 seients, TTL 4 min per defecte, Pending Payment, contenció amb bloqueig de fila + missatge Socket.IO, denegació «Seient ja no disponible», resync compte enrere si drift >2s

**Independent Test**: Crear esdeveniment de prova, obtenir seatmap, crear hold **sense** JWT (sessió anònim), veure expiració; cridar **login-grace** i comprovar +120 s una vegada; simular segon comprador i rebre error/missatge definit al spec

- [X] T016 [US1] Implementar `POST /api/events/{eventId}/holds` a `backend-api/app/Http/Controllers/Api/HoldController.php`: validar fins 6 `seat_ids`, transacció amb `SELECT … FOR UPDATE` sobre files `seats`, escriure hold a Redis amb TTL = `hold_ttl_seconds` de l’esdeveniment (default 240); permetre creació **sense** Bearer (identificador de sessió anònim / cookie); opcionalment associar `user_id` si hi ha JWT — **Branca:** `001-seat-map-t016-post-holds` · **Cypress:** **API** — 201/409; **FE** — selecció fins a 6 seients i feedback visual
- [X] T017 [US1] Implementar `POST /api/holds/{holdId}/login-grace` que, en detectar login/registre des del checkout, executi **`PEXPIRE`** (o equivalent) a Redis per **+120 s** sobre la clau del hold **una vegada** (TTL inicial **240 s** → fins a **360 s**; flag `login_grace_applied`); retornar `expires_at` i/o emetre `countdown:resync` via Socket.IO — **Branca:** `001-seat-map-t017-login-grace` · **Cypress:** **API** — TTL estès una sola vegada; **FE** — compte enrere actualitzat
- [X] T018 [US1] En cas de conflicte de concurrencia, emetre esdeveniment cap a `socket-server` (HTTP intern o Redis pub/sub) amb payload `message`: «Aquest seient acaba de ser seleccionat per un altre usuari» i retransmetre al client del segon usuari via Socket.IO — **Branca:** `001-seat-map-t018-seat-contention` · **Cypress:** dos contextos/clients o `cy.request` paral·lel + assert missatge Socket al client 2
- [X] T019 [P] [US1] Implementar `DELETE /api/holds/{holdId}` a `backend-api/` per alliberar hold i claus Redis — **Branca:** `001-seat-map-t019-delete-hold` · **Cypress:** **API** — alliberament i estat seients
- [X] T020 [US1] Implementar flux `POST /api/orders` (o rutes dedicades) per crear comanda en estat `pending_payment` mentre el hold Redis segueix actiu; **requerir usuari autenticat** (JWT); vincular hold anònim a `user_id` en autenticar-se; integració stub amb passarel·la de pagament (webhook simulat o mode dev) a `backend-api/app/Services/Payment/` — **Branca:** `001-seat-map-t020-orders-pending` · **Cypress:** **API** — `pending_payment`; **FE** — flux checkout fins stub de pagament
- [X] T021 [US1] En confirmació final de compra, si el seient ja no està disponible (SoT PostgreSQL), retornar error **«Seient ja no disponible»** i alliberar holds residuals segons [spec.md](./spec.md) — **Branca:** `001-seat-map-t021-seat-unavailable` · **Cypress:** **API** — missatge i codi esperats; **FE** — missatge a usuari
- [X] T022 [US1] Endpoint `GET /api/holds/{holdId}/time` o capçalera `X-Server-Time` + `expires_at` perquè el client comparï; si drift >2s respecte al compte enrere local, enviar esdeveniment Socket `countdown:resync` des de `socket-server/` — **Branca:** `001-seat-map-t022-countdown-resync` · **Cypress:** assert `expires_at` + event resync simulat
- [X] T023 [P] [US1] Pàgina Nuxt `frontend-nuxt/pages/events/[eventId]/seats.vue` (o ruta equivalent): mostrar `snapshotImageUrl`, zones, selecció múltiple (màx. 6), compte enrere i subscripció Socket per contenció i resync — **Branca:** `001-seat-map-t023-seats-page` · **Cypress:** **FE** — recorregut complet mapa + temporitzador + colors temps real (mock Socket si cal)
- [X] T024 [US1] Completar `frontend-nuxt/stores/hold.ts` (esquelet a **T043**) amb temps restant, sincronització servidor i selecció fins a 6 seients — **Branca:** `001-seat-map-t024-hold-store` · **Cypress:** **FE** — estat Pinia coherent amb API

**Checkpoint**: MVP de venda amb reserva temporal i pagament pendent verificable sense entrades QR encara (o amb stub)

---

## Phase 4: User Story 2 — Credencials JWT i QR SVG per seient (Priority: P2)

**Goal**: Després de pagament confirmat, una credencial per seient amb JWT (TTL 15 min o sessió), UUID vinculat, SVG via node-qrcode 1.5

**Independent Test**: Completar compra de prova i obtenir 1..N QR distints; verificar que el payload JWT és vàlid només des del backend

- [X] T025 [US2] Al confirmar pagament (`paid`), crear files `tickets` amb `status=venuda`, `public_uuid`, generar JWT signat a `backend-api/app/Services/Ticket/JwtTicketService.php` amb `exp` coherent (15 min o sessió) — **Branca:** `001-seat-map-t025-ticket-jwt` · **Cypress:** **API** — ticket creat amb camps esperats
- [X] T026 [P] [US2] Implementar generació SVG a `socket-server/src/qr/generateTicketSvg.ts` cridada des del backend (HTTP intern) o cua; usar `node-qrcode` v1.5 amb payload que inclogui `public_uuid` / referència segura — **Branca:** `001-seat-map-t026-qr-svg` · **Cypress:** **API** — resposta `image/svg+xml` o URL vàlida
- [X] T027 [US2] Implementar `GET /api/tickets/{ticketId}/qr` a `backend-api/` retornant `image/svg+xml` o URL signada segons disseny — **Branca:** `001-seat-map-t027-qr-endpoint` · **Cypress:** assert content-type i presència SVG
- [X] T028 [P] [US2] Implementar `GET /api/tickets` (historial) a `backend-api/app/Http/Controllers/Api/TicketController.php` per l’Assistent — **Branca:** `001-seat-map-t028-tickets-list` · **Cypress:** **API** — llista amb seed; **FE** — taula/targetes
- [X] T029 [US2] Vistes Nuxt `frontend-nuxt/pages/tickets/index.vue` i `frontend-nuxt/pages/tickets/[ticketId].vue` mostrant QR SVG per seient i estat venuda — **Branca:** `001-seat-map-t029-tickets-pages` · **Cypress:** **FE** — llista → detall → QR visible; **API** — coherència amb T028/T027

**Checkpoint**: Comprador/Assistent veu entrades i QR per cada seient

---

## Phase 5: User Story 3 — Validació a porta i UI «usat» (Priority: P3)

**Goal**: Només Validador escaneja; POST validació online; transició venuda→utilitzada; Socket a l’Assistent; sense canvi d’estat si sense xarxa

**Independent Test**: Escanejar QR vàlid com a Validador i veure ticket utilitzada a PostgreSQL; segon escaneig rebutjat; app Assistent mostra marca visual

- [X] T030 [US3] Implementar `POST /api/validation/scan` a `backend-api/app/Http/Controllers/Api/ValidationController.php` amb policy només `validator`, validar JWT, idempotència `jti`/`ticket_id`, actualitzar `tickets` a **utilitzada** en transacció — **Branca:** `001-seat-map-t030-validation-scan` · **Cypress:** **API** — 200 primer cop, 400 segon cop
- [X] T031 [US3] Després de validació OK, emetre `ticket:validated` des de `socket-server/` cap al room de l’usuari Assistent (`userId` del titular del ticket) — **Branca:** `001-seat-map-t031-socket-validated` · **Cypress:** client titular rep event (amb JWT room)
- [X] T032 [P] [US3] Vista `frontend-nuxt/pages/validator/scan.vue` (o ruta protegida amb rol) per llegir QR i cridar API de validació — **Branca:** `001-seat-map-t032-validator-ui` · **Cypress:** **FE** — flux validador + **API** scan
- [X] T033 [US3] Actualitzar `frontend-nuxt/stores/tickets.ts` (o Pinia) en rebre Socket: mostrar estat usat / «X» sobre el bitllet i historial alineat amb [spec.md](./spec.md) referència [22, Historial] — **Branca:** `001-seat-map-t033-ticket-used-ui` · **Cypress:** **FE** — overlay X després d’event simulat o E2E complet
- [X] T034 [US3] Gestionar resposta d’error de xarxa al validador (sense canvi d’estat «usat») als components de validació — **Branca:** `001-seat-map-t034-validator-offline` · **Cypress:** intercept network failure + assert UI

**Checkpoint**: Flux complet compra → entrada → validació → UI Assistent

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T035 [P] **API admin i esborranys** (si cal abans de T052): rutes `/admin/*` o polítiques Laravel per dashboard/import; esborrany **Socket.IO** per mètriques; **cancel·lar o marcar fet** si **T052** cobreix el mateix abans de tancar el sprint — **Branca:** `001-seat-map-t035-admin-stub` · **Cypress:** **API** rutes admin amb rol admin; smoke dashboard
- [X] T036 [P] **Endpoints socials mínims** (si cal abans de T050): transferències i activitat al backend; **cancel·lar o marcar fet** si **T050** els implementa en la mateixa entrega — **Branca:** `001-seat-map-t036-social-api-stub` · **Cypress:** transferència mínima `cy.request`
- [X] T037 [P] Revisar i ampliar `specs/001-seat-map-entry-validation/contracts/openapi.yaml`: `pending_payment`, auth, holds anònims, `login-grace`, errors «Seient ja no disponible», tickets; **Social**: esquema **`FriendInvite`**, `GET/POST /api/social/friend-invites`, `PATCH .../{inviteId}`, transferència amb invalidació QR; **Administrador** (sync Discovery Feed) segons [spec.md](./spec.md) **FR-022**, **FR-025** — **Branca:** `001-seat-map-t037-openapi-sync` · **Cypress:** contracte — opcionalment **Dredd/Schemathesis** o assert manual de camps clau en CI; Cypress comprova paritat amb tests existents
- [X] T038 Verificar `specs/001-seat-map-entry-validation/quickstart.md` amb `docker compose -f docker/dev/docker-compose.yml up` i variables `.env` (incloent `JWT_SECRET` on escaigui) — **Branca:** `001-seat-map-t038-quickstart-verify` · **Cypress:** suite completa en entorn quickstart (job CI)
- [X] T039 [P] Afegir logging estructurat a `backend-api/` per holds, pagaments i validacions (sense secrets) — **Branca:** `001-seat-map-t039-logging` · **Cypress:** sense assert de logs; regressió funcional completa
- [X] T040 Revisar alineació amb `.specify/memory/constitution.md` (SoT Laravel, Redis holds, Socket subordinat) i anotar excepcions a `plan.md` si n’hi ha — **Branca:** `001-seat-map-t040-constitution-review` · **Cypress:** checklist manual + tests verds
- [X] T053 [P] **Proves de càrrega / stress** del **socket-server** (Node.js): connexions massives als **canals públics** per `eventId` + subconjunt de connexions amb **JWT** a rooms privades; verificar que la **concurrència** no degradi latència fora dels llindars (objectiu: mantenir compliment del **95 %** i temps de resposta coherents amb **SC-002** i **SC-004** del [spec.md](./spec.md)); eines p. ex. **k6**, **Artillery** — **complementari** a Cypress i proves funcionals — **Branca:** `001-seat-map-t053-load-socket` · **Cypress:** no substitueix k6/Artillery; mantenir suite Cypress verda en paral·lel

---

## ~~Phase 7: Interfície producte (mapa de pantalles [spec.md](./spec.md))~~ (completada)

**Purpose**: Implementar navegació, descobriment (Gemini + Discovery + Maps), social, guardats, perfil i panell admin descrits a **Interfície per rol** i **FR-015–FR-025**.

- [x] ~~T045 [P] **Layouts Nuxt**: `layouts/default.vue` amb **Header** (desktop) + **Footer** fix (mòbil); `layouts/admin.vue` amb **Sidebar**; enllaços als grups de rutes consumidor vs `/admin/*` — **Branca:** `001-seat-map-t045-layouts` · **Cypress:** **FE** — navegació 6 seccions + admin shell~~
- [x] ~~T046 [P] **Home (A)**: `pages/index.vue` amb seccions **Destacats** i **Triats per a tu**; endpoints Laravel que combinin **PostGIS** (proximitat) i **Gemini** (recomanació si historial i opt-in usuari) — **Branca:** `001-seat-map-t046-home-feed` · **Cypress:** **FE** — seccions amb dades seed; **API** si exposat~~
- [x] ~~T047 [P] **Buscador llista (B)**: pàgina cerca amb text, filtres classificació/dates/preu; client contra API **Discovery** (proxy Laravel); **botó flotant Mapa**; **cor** → `POST/DELETE` favorits — **Branca:** `001-seat-map-t047-search-list` · **Cypress:** **FE** — filtres + favorit; **API** favorits~~
- [x] ~~T048 [P] **Buscador mapa overlay (C)**: component mapa **Google Maps JS** + marcadors des d’API; geolocalització; InfoWindow; **Com arribar** → obrir Maps extern; navegació a detall esdeveniment — **Branca:** `001-seat-map-t048-search-map` · **Cypress:** **FE** — marcadors (stub Maps si cal en CI)~~
- [x] ~~T049 [P] **Guardats (I)** + **Perfil (J)**: pàgina favorits; formulari perfil i **toggle** `gemini_personalization_enabled` (`user_settings`) — **Branca:** `001-seat-map-t049-saved-profile` · **Cypress:** **FE** — guardats + perfil; **API** settings~~
- [x] ~~T050 [P] **Social (G)(H)**: persistència **`friend_invites`** ([data-model.md](./data-model.md)); llista amics, cerca `username`, flux **enllaç d’amistat** (token + acceptació automàtica si escau); perfil amic amb activitat subjecta a privacitat; **Enviar Entrada**: transferència amb **invalidació JWT/QR** i **nou SVG** al servidor ([spec.md](./spec.md) **FR-022**) — **Branca:** `001-seat-map-t050-social` · **Cypress:** **API** — invites + transfer; **FE** — flux social complet~~
- [x] ~~T051 [P] **Entrades UI (E)(F)**: targetes **agrupades per esdeveniment** a `pages/tickets/index.vue`; modal **Enviar Tickets**; detall ticket amb èmfasi **QR dinàmic** + overlay **X** (amplia T029/T033 si cal) — **Branca:** `001-seat-map-t051-tickets-ui` · **Cypress:** **FE** — agrupació + modal + QR~~
- [x] ~~T052 [P] **Administrador (FR-025)**: **Dashboard (A)** temps real (Socket.IO) + mini-mapa estats seient; **CRUD esdeveniments (B)** amb **import Discovery Feed 2.0**; **Informes (C)** (stub gràfics); **Usuaris/tickets (D)**; **Control d’accés / Staff (E)** vista mòbil + escàner web (refinar T032 sota mateix disseny) — **Branca:** `001-seat-map-t052-admin-panel` · **Cypress:** **FE** admin + **API** import/sync; rol admin~~

---

## Dependencies & Execution Order

- **Phase 1 → 2 → 3 → 4 → 5 → 6 → 7** en sèrie per fases (la **Phase 7** pot començar en paral·lel parcial amb la **2** un cop existisquin layouts, però depèn de **T041–T044** per rutes protegides i de dades **T007** per favorits/social). **T053** (stress socket) després de **T013** i abans de desplegament intensiu.
- **US2** depèn de **comanda pagada** (flux iniciat a US1); **US3** depèn de **tickets venuda** (US2).
- Dins US1: T016–T017 abans de T022 (resync depèn de hold actiu); **T041–T044** (auth) han d’estar llestos abans de T020 (comanda autenticada).

### User Story Dependencies

- **US1 (P1)**: Després de Foundational; cap dependència d’US2/US3.
- **US2 (P2)**: Requereix US1 + flux `paid` (o bypass dev).
- **US3 (P3)**: Requereix US2 (tickets i JWT).

### Parallel Opportunities

- T002, T003, T004 en paral·lel (inicialització de tres projectes).
- T008, T009, T014 en paral·lel després de T007 (Redis, rols, client Nuxt).
- T025 i T027 en paral·lel quan JWT ticket estigui definit (QR SVG vs llista tickets).

---

## Parallel Example: User Story 1

```bash
# Després de T016 (hold backend), en paral·lel si hi ha capacitat:
# Recordatori: cada tasca = branca pròpia + Cypress abans de merge
Task: "frontend-nuxt/pages/events/[eventId]/seats.vue UI selecció"  # T023
Task: "socket-server handler seat:contention"  # T018
```

---

## Implementation Strategy

### MVP First (només US1)

1. Phases 1–2  
2. Phase 3 (US1) fins Checkpoint  
3. Aturar i demostrar hold + seatmap + pending payment stub

### Incremental Delivery

1. US1 → demo venda  
2. US2 → demo entrades QR  
3. US3 → demo porta + marca visual

---

## Notes

- Ajustar noms de fitxers Laravel (`app/Http/Controllers/Api/...`) si el projecte usa estructura API Resources diferent.
- Si la generació de SVG es mou totalment a `backend-api` (sense Node), actualitzar tasques T025 i constitució amb justificació a `plan.md`.
- El **mapa de pantalles** (A–J, Admin A–E) està descrit al [spec.md](./spec.md); les rutes concretes (`pages/...`) poden adaptar-se al projecte sense canviar fluxos.
- **Cypress i backend**: usar `cy.request` per a contracte API; per a **Socket**, valorar plugin o tests d’integració Node separats (T013, T031, T053).
- **Branques**: una branca per tasca evita barrejar PRs; **merge via PR cap a `dev`** (branca base); el nom del feature Speckit és independent del nom de la branca Git sempre que el flux des de **`dev`** es respecti.
