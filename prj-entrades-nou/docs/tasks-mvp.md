# Full de ruta tècnic MVP — `docs/tasks-mvp.md`

**Rol**: veritat operativa per a la fase d’implementació (Spec-Driven Development).  
**Fonts normatives**: `.specify/memory/constitution.md`, `specs/001-seat-map-entry-validation/spec.md`, `plan.md`, `tasks.md`, `data-model.md`, `research.md`.

**Abans de la llista de tasques — resolució prescriptiva d’inconsistències de l’anàlisi**

### I1 — Sockets anònims al mapa (Guest Tokens efímers)

**Problema**: cal lectura pública d’estat de seients sense JWT d’usuari (**FR-014**), però sense un vincle segur el servidor no pot dirigir `seat:contention` al client correcte ni aplicar límit de connexions per `eventId`.

**Solució obligatòria**:

1. **Laravel** exposa `POST /api/guest/socket-token` (públic, rate-limited) amb cos `{ "event_id": "<uuid>", "hold_id": "<uuid opcional>" }`.
2. Resposta: `{ "guest_token": "<JWT>", "expires_at": "<ISO8601>" }`.
3. El JWT és **efímer** (TTL **10–15 min**, renovable): claims mínims: `typ: "guest_socket"`, `event_id`, `hold_id` (nullable), `jti` (únic), `exp`, `iat`; signat amb la mateixa família de secrets que la resta de JWT (`JWT_SECRET` / clau dedicada `GUEST_SOCKET_JWT_SECRET` si es vol separar rotació).
4. **Nuxt** (mapa de seients, `seats.vue`): després de crear o recuperar hold, crida aquest endpoint i passa `guest_token` al client Socket.IO: `io(url, { auth: { guestToken } })`.
5. **socket-server** (`src/middleware/socketAuth.ts` o dins `io.use`):  
   - Si hi ha `auth.guestToken`, el verifica amb la clau compartida; permet `socket.join(\`event:${event_id}\`)` i, si escau, `socket.join(\`hold:${hold_id}\`)` per missatges dirigits.  
   - Si no hi ha token (només lectura mínima), es pot permetre `join` públic amb **quota més baixa** (configurable) o requerir token en entorns staging/prod (documentar la decisió a `socket-server/README.md`).
6. Les **escriptures** de negoci (crear/alliberar hold, comanda) continuen sent **només** REST Laravel; el guest token **no** autoritza escriptura al mapa via Socket.

### I2 — Middleware de protecció Social / Guardats (i alineació FR-010)

**Problema**: el middleware ha de cobrir explícitament **Social** i **Guardats** a més de checkout, tickets i perfil.

**Solució obligatòria**:

1. **Nuxt**: middleware `frontend-nuxt/middleware/auth.ts` (o `auth.global.ts` amb llista blanca per a rutes públiques) que comprovi `stores/auth.ts` (JWT present i no caducat; opcionalment `GET /api/auth/me` en primer accés).
2. Rutes **protegides** (meta `middleware: ['auth']` o prefix equivalent):  
   - `/tickets`, `/tickets/**`  
   - `/saved`, `/saved/**` (Guardats)  
   - `/social`, `/social/**`  
   - `/checkout`, `/checkout/**`  
   - `/profile`, `/profile/**` (Perfil)  
3. Rutes **públiques** (sense auth): Home, Buscador (llista/mapa), detall esdeveniment **fins** al mapa de seients amb hold anònim, login/registre.
4. **Backend**: tots els endpoints `GET/POST/PATCH/DELETE` sota `/api/social/*`, `/api/saved-events/*` (o convenció triada), `/api/tickets/*`, etc. amb `auth:api` + policies segons correspongui.

---

## Principi d’entrega incremental

1. **Infra + auth + I1 + I2 + esquema de dades**.  
2. **Seatmap (Top Picks + fallback PostGIS) + holds + Redis + Socket públic/guest + contenció** — **proves de concurrència abans** de tenir el mòdul de pagaments complet.  
3. **Comanda `pending_payment` amb passarel·la stub** (webhook dev) per tancar el flux sense integració de pagament real.  
4. **JWT ticket + QR SVG** (node-qrcode).  
5. **Validació validador + `ticket:validated` + marca «X» a Pinia**.  
6. **Discovery API + import admin (Discovery Feed)** i polish.

---

## Tasques (format normatiu)

Totes les branques neixen de `dev`: `feat/<nom-curt>`.

---

### [MVP-01] — Monorepo i Docker Compose (Redis 7, Postgres/PostGIS)

**Branca Git**: `feat/docker-compose-stack`

**Objectiu**: Entorn reproduïble amb els serveis de la constitució (Postgres 16 + PostGIS, Redis 7.2, serveis d’app).

**Context tècnic 100%**: Xarxa interna Docker; volums per dades; variables per `JWT_SECRET`, `DB_*`, `REDIS_*`; ports exposats per desenvolupament local.

**Tecnologia**: Docker Compose v2, imatges oficials `postgis/postgis:16-3.4` (o equivalent), `redis:7.2`.

**Fitxers a crear/modificar**:

- `docker/dev/docker-compose.yml`
- `docker/dev/README.md`
- `docker/dockerfiles/backend-api/Dockerfile`
- `docker/dockerfiles/frontend-nuxt/Dockerfile`
- `docker/dockerfiles/socket-server/Dockerfile`

**Criteris d’acceptació (testing)**: `docker compose -f docker/dev/docker-compose.yml up` arrenca sense errors; `redis-cli PING` i `psql` connecten des del host o contenidor; README documenta ports i env mínims.

**Workflow**: `git checkout dev && git pull && git checkout -b feat/docker-compose-stack` → commits atòmics → PR cap a `dev` → merge després de revisió.

---

### [MVP-02] — Backend Laravel 11: esquelet, `.env.example`, connexió DB/Redis

**Branca Git**: `feat/backend-api-skeleton`

**Objectiu**: API preparada per migracions, Redis i configuració JWT posterior.

**Context tècnic 100%**: `config/database.php` (pgsql + redis); cap lògica de negoci encara.

**Tecnologia**: Laravel 11, PHP 8.3.

**Fitxers a crear/modificar**:

- `backend-api/` (projecte Composer)
- `backend-api/.env.example`
- `backend-api/config/database.php`

**Criteris d’acceptació**: `php artisan migrate:status` funciona (sense taules encara o només `migrations`); connexió Redis des de tinker.

**Workflow**: Branch des de `dev` → PR → merge a `dev`.

---

### [MVP-03] — Frontend Nuxt 3 + Pinia: esquelet i variables públiques

**Branca Git**: `feat/frontend-nuxt-skeleton`

**Objectiu**: App amb `NUXT_PUBLIC_API_URL`, `NUXT_PUBLIC_SOCKET_URL`, `NUXT_PUBLIC_GOOGLE_MAPS_KEY`.

**Context tècnic 100%**: Base per layouts i pàgines; sense lògica de negoci.

**Tecnologia**: Nuxt 3, Pinia, TypeScript.

**Fitxers a crear/modificar**:

- `frontend-nuxt/nuxt.config.ts`
- `frontend-nuxt/.env.example`
- `frontend-nuxt/package.json`

**Criteris d’acceptació**: `npm run dev` arrenca; variables llegides al client on calgui.

**Workflow**: PR cap a `dev`.

---

### [MVP-04] — Socket server Node 20 + Socket.IO 4.7

**Branca Git**: `feat/socket-server-skeleton`

**Objectiu**: Procés Node que escolta Socket.IO i llig Redis (pub/sub o subscripció preparada).

**Context tècnic 100%**: Esdeveniments documentats al `plan.md` (`seat:contention`, `countdown:resync`, `ticket:validated`).

**Tecnologia**: Node 20 LTS, `socket.io@4.7`, client `ioredis` o `redis` v4.

**Fitxers a crear/modificar**:

- `socket-server/package.json`
- `socket-server/src/index.ts`
- `socket-server/.env.example`

**Criteris d’acceptació**: Client de prova (script o Postman Socket) es connecta; log de connexió.

**Workflow**: PR → `dev`.

---

### [MVP-05] — Migracions domini: esdeveniments, seients, holds, comandes, tickets, social

**Branca Git**: `feat/domain-migrations`

**Objectiu**: Esquema alineat amb `data-model.md`: `events.hold_ttl_seconds`, `events.seat_layout` JSONB, `seats`, `orders` (estat `pending_payment`), `tickets` (`public_uuid`, estats), `friend_invites`, `saved_events`, etc.

**Context tècnic 100%**: PostGIS per `venues.location`; claus úniques per anti-frau; índexs per `event_id` + `seat_id`.

**Tecnologia**: Laravel migrations, PostgreSQL 16.

**Fitxers a crear/modificar**:

- `backend-api/database/migrations/*_create_events_table.php`
- `backend-api/database/migrations/*_create_seats_orders_tickets.php`
- `database/init.sql` (opcional, si el pla manté SQL paral·lel)

**Criteris d’acceptació**: `php artisan migrate:fresh` exitós; seed mínim amb 1 esdeveniment i seients de prova.

**Workflow**: PR → `dev`.

---

### [MVP-06] — JWT d’API (Laravel): AuthController, middleware, `/api/auth/me`

**Branca Git**: `feat/jwt-api-auth`

**Objectiu**: Únic emissor de JWT de sessió (**FR-009**); registre, login, me.

**Context tècnic 100%**: Password hash bcrypt; rols Spatie (`user`, `validator`, `admin`).

**Tecnologia**: `tymon/jwt-auth` (o paquet acordat), `spatie/laravel-permission`.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/AuthController.php`
- `backend-api/app/Models/User.php`
- `backend-api/routes/api.php`
- `backend-api/config/jwt.php`
- `backend-api/database/seeders/RoleSeeder.php`

**Criteris d’acceptació**: Postman/curl: login retorna `access_token`; `GET /api/auth/me` amb Bearer retorna usuari; sense token, 401.

**Workflow**: PR → `dev`.

---

### [MVP-07] — **I1** Guest Socket Token: emissió Laravel + validació socket-server

**Branca Git**: `feat/guest-socket-tokens`

**Objectiu**: Tokens efímers per unir connexions anònimes al mapa sense JWT d’usuari, mantenint **FR-014**.

**Context tècnic 100%**: Vege secció I1 dalt; TTL 10–15 min; claims `typ: guest_socket`; rate limit per IP a Laravel.

**Tecnologia**: `firebase/php-jwt` o mateix stack JWT; middleware `io.use` al socket-server.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/GuestSocketTokenController.php`
- `backend-api/app/Services/GuestSocketTokenService.php`
- `backend-api/routes/api.php` → `POST /api/guest/socket-token`
- `socket-server/src/auth/verifyGuestToken.ts`
- `socket-server/src/index.ts` (integració `io.use`)

**Criteris d’acceptació**: Emetre token amb `event_id`; socket amb `auth.guestToken` entra a room `event:{id}`; token caducat rebutja connexió o join; escriptura de hold només via REST.

**Workflow**: PR → `dev`.

---

### [MVP-08] — **I2** Middleware Nuxt `auth` + rutes Social / Guardats / Tickets / Checkout / Perfil

**Branca Git**: `feat/nuxt-auth-middleware`

**Objectiu**: Compliment **FR-010** i resolució I2: cap consulta privada sense JWT.

**Context tècnic 100%**: Redirecció a `/login?redirect=…` si no hi ha token; `stores/auth.ts` com a font de presència del token.

**Tecnologia**: Nuxt route middleware, Pinia.

**Fitxers a crear/modificar**:

- `frontend-nuxt/middleware/auth.ts`
- `frontend-nuxt/stores/auth.ts`
- `frontend-nuxt/pages/social/**` (layout amb meta middleware)
- `frontend-nuxt/pages/saved/**` o `guardats/**` (unificar nom amb menú **FR-015**)
- `frontend-nuxt/pages/tickets/**`, `frontend-nuxt/pages/checkout/**`, `frontend-nuxt/pages/profile/**`

**Criteris d’acceptació**: Accés directe a `/social` sense login redirigeix; amb login, carrega; mateix per `/saved` i `/tickets`; mapa de seients **sense** aquest middleware.

**Workflow**: PR → `dev`.

---

### [MVP-09] — Ticketmaster: client Top Picks + `SeatmapController`

**Branca Git**: `feat/ticketmaster-top-picks`

**Objectiu**: **FR-001**: `snapshotImageUrl` i zones; errors → servei fallback.

**Context tècnic 100%**: Clau `TICKETMASTER_API_KEY`; timeouts curts; no bloquejar resposta si TM falla.

**Tecnologia**: HTTP client Laravel (`Http::timeout()`), `TicketmasterSeatmapClient`.

**Fitxers a crear/modificar**:

- `backend-api/app/Services/Ticketmaster/TicketmasterSeatmapClient.php`
- `backend-api/app/Services/Seatmap/PostgresSeatmapFallbackService.php`
- `backend-api/app/Http/Controllers/Api/SeatmapController.php`
- `backend-api/routes/api.php` → `GET /api/events/{eventId}/seatmap`

**Criteris d’acceptació**: Amb TM mock o clau real, JSON amb `snapshotImageUrl`; simulant fallada TM, resposta des de Postgres sense zones fictícies.

**Workflow**: PR → `dev`.

---

### [MVP-10] — Ticketmaster: Discovery API (proxy Laravel) + cerca esdeveniments

**Branca Git**: `feat/ticketmaster-discovery-proxy`

**Objectiu**: **FR-017**: cerca amb filtres des del backend (no exposar clau al Nuxt).

**Context tècnic 100%**: Paginació; mapatge a model intern `events` quan calgui; fallback PostGIS si cal per coordenades.

**Tecnologia**: Discovery API v2 (documentació TM), cache opcional Redis.

**Fitxers a crear/modificar**:

- `backend-api/app/Services/Ticketmaster/DiscoveryApiClient.php`
- `backend-api/app/Http/Controllers/Api/EventSearchController.php`
- `backend-api/routes/api.php` → `GET /api/events/search`

**Criteris d’acceptació**: Crida des de Nuxt a `/api/events/search?q=` retorna llista; tests manual o contracte OpenAPI.

**Workflow**: PR → `dev`.

---

### [MVP-11] — Holds: `POST /api/events/{id}/holds` amb transacció `FOR UPDATE`

**Branca Git**: `feat/holds-atomic-redis`

**Objectiu**: **FR-002**, **FR-003**: fins a 6 seients, tot o res; TTL Redis = `hold_ttl_seconds` (default 240).

**Context tècnic 100%**: `SELECT … FOR UPDATE` a `seats`; clau Redis `hold:{eventId}:{holdUuid}`; identificador anònim (cookie / UUID client) fins a login.

**Tecnologia**: Redis `SET` + TTL, Laravel DB transaction.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/HoldController.php`
- `backend-api/app/Services/Hold/HoldService.php`
- `backend-api/routes/api.php`

**Criteris d’acceptació**: Dos workers provant el mateix `seat_id`: un OK, l’altre error coherent; Redis reflecteix hold.

**Workflow**: PR → `dev`.

---

### [MVP-12] — Contenció Socket: `seat:contention` + integració backend → socket-server

**Branca Git**: `feat/socket-seat-contention`

**Objectiu**: Segon comprador rep missatge exacte del spec.

**Context tècnic 100%**: Després de rollback de transacció o error de bloqueig, publicar a Redis o HTTP intern cap a Node que faci `io.to(socketId|room).emit('seat:contention', { seatId, message })`.

**Tecnologia**: Redis pub/sub o `POST http://socket-server:3000/internal/broadcast` (secret intern).

**Fitxers a crear/modificar**:

- `backend-api/app/Services/Socket/SocketBroadcastClient.php`
- `socket-server/src/handlers/seatContention.ts`
- `socket-server/src/index.ts`

**Criteris d’acceptació**: Prova manual amb dos navegadors: el segon rep el text **«Aquest seient acaba de ser seleccionat per un altre usuari»** (o clau i18n equivalent al payload).

**Workflow**: PR → `dev`.

---

### [MVP-13] — `login-grace`: `POST /api/holds/{id}/login-grace` (+120 s una vegada)

**Branca Git**: `feat/hold-login-grace`

**Objectiu**: **FR-003**: `PEXPIRE` + flag `login_grace_applied` al hash Redis.

**Context tècnic 100%**: Només si hold vinculat a sessió anònima que ha obert flux login des del checkout; màxim efectiu 360 s.

**Tecnologia**: Redis `HSET`, `PEXPIRE`.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/HoldController.php` (mètode `loginGrace`)
- `frontend-nuxt/pages/checkout/*.vue` (crida en obrir modal login)

**Criteris d’acceptació**: Primer `login-grace` allarga TTL; segon intent retorna 409 o ignora segons especificació escrita al controlador.

**Workflow**: PR → `dev`.

---

### [MVP-14] — Resync compte enrere: `GET /api/holds/{id}/time` + esdeveniment `countdown:resync`

**Branca Git**: `feat/hold-countdown-resync`

**Objectiu**: **SC-002**: drift > 2 s → correcció via Socket (o resposta HTTP).

**Context tècnic 100%**: `expires_at` servidor; comparació al client `stores/hold.ts`.

**Tecnologia**: Socket.IO event `countdown:resync` amb `{ expiresAt }`.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/HoldController.php` (`time`)
- `frontend-nuxt/stores/hold.ts`
- `frontend-nuxt/pages/events/[eventId]/seats.vue`

**Criteris d’acceptació**: Simular rellotge client desfasat; després de resync, diferència ≤ 2 s respecte servidor.

**Workflow**: PR → `dev`.

---

### [MVP-15] — UI mapa de seients: `seats.vue` + Guest Token + colors temps real

**Branca Git**: `feat/seat-map-ui-socket`

**Objectiu**: Demostrable **abans** del pagament real: mapa + hold + sockets.

**Context tècnic 100%**: Subscripció a `event:{eventId}`; actualització visual seients; compte enrere.

**Tecnologia**: Vue 3, Pinia, socket.io-client.

**Fitxers a crear/modificar**:

- `frontend-nuxt/pages/events/[eventId]/seats.vue`
- `frontend-nuxt/composables/useSeatSocket.ts`

**Criteris d’acceptació**: Cypress o manual: seleccionar seients, veure temporitzador, veure canvi de color quan un altre usuari reserva (segons entorn de prova).

**Workflow**: PR → `dev`.

---

### [MVP-16] — Comanda `pending_payment` + passarel·la stub (sense TPV real)

**Branca Git**: `feat/order-pending-payment-stub`

**Objectiu**: Permetre provar flux complet de hold → comanda **sense** bloquejar el MVP en passarel·la real; **end-first** de concurrència ja validada a MVP-11–15.

**Context tècnic 100%**: `POST /api/orders` requereix JWT; vincula `user_id` i `hold_id`; estat `pending_payment`; webhook dev confirma pagament.

**Tecnologia**: Laravel Service `PaymentStubService`, job o endpoint intern.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/OrderController.php`
- `backend-api/app/Services/Payment/PaymentStubService.php`
- `backend-api/routes/api.php`

**Criteris d’acceptació**: Crear comanda des del checkout amb stub; estat passa a `paid` amb crida simulada; hold alliberat o convertit segons disseny.

**Workflow**: PR → `dev`.

---

### [MVP-17] — JWT de ticket + UUID + generació QR SVG (node-qrcode 1.5)

**Branca Git**: `feat/ticket-jwt-qr-svg`

**Objectiu**: **FR-005**, **FR-006**: credencial per seient; JWT amb TTL **15 min** (o vinculació sessió documentada al servei).

**Context tècnic 100%**: Payload amb `ticket_id`, `jti`, `public_uuid`; QR no generable al client amb secret.

**Tecnologia**: `JwtTicketService`, `node-qrcode@1.5` a `socket-server` o worker invocat des de Laravel.

**Fitxers a crear/modificar**:

- `backend-api/app/Services/Ticket/JwtTicketService.php`
- `socket-server/src/qr/generateTicketSvg.ts`
- `backend-api/app/Http/Controllers/Api/TicketController.php` → `GET /api/tickets/{id}/qr`

**Criteris d’acceptació**: Resposta `image/svg+xml`; escaneig manual del payload mostra claims (sense secret); token caducat → 401 al tornar a demanar QR.

**Workflow**: PR → `dev`.

---

### [MVP-18] — Validació: `POST /api/validation/scan` (política `validator`)

**Branca Git**: `feat/ticket-validation-api`

**Objectiu**: **FR-007**: només rol validador; transició `venuda` → `utilitzada` idempotent.

**Context tècnic 100%**: Una petició per credencial; sense xarxa no hi ha canvi d’estat.

**Tecnologia**: Laravel Policy, middleware rol, transacció DB.

**Fitxers a crear/modificar**:

- `backend-api/app/Http/Controllers/Api/ValidationController.php`
- `backend-api/app/Policies/TicketPolicy.php` (o gate)
- `backend-api/routes/api.php`

**Criteris d’acceptació**: Primer escaneig OK; segon 409/conflict; usuari sense rol validador 403.

**Workflow**: PR → `dev`.

---

### [MVP-19] — Socket `ticket:validated` + Pinia + UI «X» instantània

**Branca Git**: `feat/socket-ticket-validated-ui`

**Objectiu**: **FR-008**, **SC-004**: Assistent veu marca **X** en &lt; 5 s.

**Context tècnic 100%**: Després de persistir, broadcast a room `user:{assistantUserId}`; client actualitza `stores/tickets.ts`.

**Tecnologia**: Socket.IO rooms autenticades amb JWT d’usuari al handshake (no guest token).

**Fitxers a crear/modificar**:

- `socket-server/src/handlers/ticketValidated.ts`
- `frontend-nuxt/stores/tickets.ts`
- `frontend-nuxt/pages/tickets/[ticketId].vue` (overlay «X»)

**Criteris d’acceptació**: Flux E2E manual o Cypress: validar → veure «X» sense recarregar pàgina (o amb un sol refetch mínim).

**Workflow**: PR → `dev`.

---

### [MVP-20] — Vista validador: `pages/validator/scan.vue` + càmera / ZXing

**Branca Git**: `feat/validator-scan-ui`

**Objectiu**: **FR-012a**: flux mòbil-first.

**Context tècnic 100%**: Llegir JWT del QR, enviar al backend; errors de xarxa explícits.

**Tecnologia**: `@zxing/browser` o `html5-qrcode`, rol `validator` al `stores/auth`.

**Fitxers a crear/modificar**:

- `frontend-nuxt/pages/validator/scan.vue`
- `frontend-nuxt/middleware/validator.ts`

**Criteris d’acceptació**: Escaneig simulat (entrada de text del JWT) acceptable per dev; en producció, càmera.

**Workflow**: PR → `dev`.

---

### [MVP-21] — Google AI SDK: Gemini (config **1.5 Pro** al `.env`) per recomanacions Home

**Branca Git**: `feat/gemini-recommendations-api`

**Objectiu**: **FR-016**, **C1**: només JSON d’IDs; mai compra.

**Context tècnic 100%**: `GEMINI_API_KEY` al **backend**; model configurable `GEMINI_MODEL=gemini-1.5-pro` (alineable amb **Gemini 1.5 Pro** per SDK; la constitució menciona *Flash* per assistència — triar model per entorn i documentar a `backend-api/.env.example`).

**Tecnologia**: `@google/generative-ai` o Google AI SDK oficial per Node/PHP segons implementació triada.

**Fitxers a crear/modificar**:

- `backend-api/app/Services/Recommendation/GeminiRecommendationService.php`
- `backend-api/config/services.php`
- `docker/dev/docker-compose.yml` (pass-through env)

**Criteris d’acceptació**: Endpoint intern o `GET /api/home/recommendations` retorna array d’IDs vàlids existents a Postgres; sense IDs inventats.

**Workflow**: PR → `dev`.

---

### [MVP-22] — Import massiu admin: Discovery Feed 2.0

**Branca Git**: `feat/admin-discovery-feed-import`

**Objectiu**: **FR-013**, **FR-025**: job d’importació cap a `events`.

**Context tècnic 100%**: Idempotència per `external_id` TM; errors loguejats.

**Tecnologia**: Laravel Artisan command + queue opcional.

**Fitxers a crear/modificar**:

- `backend-api/app/Console/Commands/ImportTicketmasterDiscoveryFeed.php`
- `backend-api/app/Services/Ticketmaster/DiscoveryFeedParser.php`

**Criteris d’acceptació**: Executar command amb fixture JSON petit; files insertades/actualitzades.

**Workflow**: PR → `dev`.

---

### [MVP-23] — Contracte OpenAPI i proves Cypress (smoke)

**Branca Git**: `feat/openapi-cypress-smoke`

**Objectiu**: Mantenir `contracts/openapi.yaml` al dia; smoke login + hold + ticket list.

**Context tècnic 100%**: Cobrir camins crítics MVP, no tot el producte.

**Tecnologia**: Cypress o Playwright (repositori ja menciona Playwright al plan).

**Fitxers a crear/modificar**:

- `specs/001-seat-map-entry-validation/contracts/openapi.yaml`
- `frontend-nuxt/cypress/e2e/smoke.cy.ts` (o `e2e/` Playwright)

**Criteris d’acceptació**: Pipeline local `npm run test:e2e` passa (o documentat si requereix Docker).

**Workflow**: PR → `dev`.

---

## Ordre d’execució recomanat (dependències)

`MVP-01` → `MVP-02`–`MVP-04` (paral·lel parcial) → `MVP-05` → `MVP-06` → **`MVP-07` (I1) + `MVP-08` (I2)** → `MVP-09`–`MVP-15` (**concurrència i mapa abans de pagament real**) → `MVP-16` (stub) → `MVP-17`–`MVP-20` → `MVP-21`–`MVP-23`.

---

## Commits i merge (norma de treball)

1. Cada tasca = una branca `feat/...` des de `dev` actualitzat.  
2. Commits petits en català o anglès consistent: `feat(holds): add atomic POST /events/{id}/holds`.  
3. PR amb descripció que enllaça `[MVP-xx]` i criteris d’acceptació coberts.  
4. Merge a `dev` només amb revisió i CI (si n’hi ha).  
5. `main`/`master` només des de `dev` estable (política de l’equip).

---

*Document generat com a full de ruta MVP prescriptiu; qualsevol canvi de domini ha de passar primer per `spec.md` i aquest fitxer.*
