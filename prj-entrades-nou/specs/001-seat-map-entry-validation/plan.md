# Pla d’implementació: Mapa de seients, bloquejos, entrades segures i validació

**Feature Speckit**: `001-seat-map-entry-validation` | **Branca base Git**: `dev` (vegeu [tasks.md](./tasks.md) — Git Flow) | **Data**: 2026-04-13 | **Spec**: [spec.md](./spec.md)  
**Entrada**: Especificació de funcionalitat del mateix directori.

## Summary

Aquesta funcionalitat cobreix: (1) **mapa de seients** amb imatge i zones via **Ticketmaster Top Picks** (`snapshotImageUrl` + metadades), amb **fallback** a consultes **PostgreSQL/PostGIS** (proximitat o ordre manual) si Top Picks no respon; (2) **hold atòmic** de fins a **6** seients amb TTL **per esdeveniment** (rang 3–5 min, **línia base de producte 4 min**) a **Redis**, amb **pròrroga única de +2 min** només quan l’usuari **inicia login o registre des del checkout**; compte enrere al **Nuxt** i **resincronització** si el desfasament client–servidor supera **2 segons** (Socket.IO); selecció de seients **permetuda sense sessió**; **pagament i comanda** només amb **usuari autenticat**; (3) **concurrència estricta** en la mateixa fila de seient: **transaccions amb bloqueig de fila** a PostgreSQL, segon comprador notificat per **Socket.IO** amb missatge fix; confirmació final denegada amb **«Seient ja no disponible»** si el SoT detecta indisponibilitat; (4) estat de comanda **Pending Payment** mentre dura la passarel·la externa, hold Redis actiu fins TTL; (5) **JWT d’API** emès per **Laravel** (registre, login, validació de peticions); **Pinia** al Nuxt per token i perfil; **middleware** Nuxt a checkout, Tickets i perfil; **JWT de ticket** (TTL **15 min** o vinculat a sessió) i **QR SVG** amb **UUID** vinculat a la fila `tickets`, generat amb **node-qrcode v1.5**; (6) **validació** una petició HTTP per credencial (escaneigs ràpids en grup), transició **venuda → utilitzada** a PostgreSQL, **Socket.IO** cap a l’**Assistent**; UI [22, Historial]; (7) rols **Administrador** (panell, inventari TM + edició, **dashboard temps real** Socket.IO: connectats, vendes) i **Validador** (escaneig porta), més funcions **socials** del comprador (enviar entrades a amics, activitat) segons abast al model de dades.

Enfocament tècnic: tota persistència i regles de negoci al **Laravel** + **PostgreSQL/PostGIS**; Redis per holds; **Socket.IO** amb **JWT al handshake** (mateixa clau/validació que l’API) per identificar usuaris i alimentar mètriques admin; notificacions després d’esdeveniments ja persistits o per errors de contenció / resync de temps (no substitueix SoT).

**Rols (3)**: **Usuari** (Comprador/Assistent, app consumidor + JWT per compra i dades privades), **Validador** (escaneig mòbil, validació JWT ticket → PostgreSQL irreversible), **Administrador** (Sidebar, import TM, **plànols JSONB**, dashboard temps real). Detall: [spec.md](./spec.md) Session 2026-04-14 i **Arquitectura de seguretat i estat**.

**Pinia**: stores **`auth`** (JWT sessió) i **`hold`** (selecció de seients pre-confirmació), sense reemplaçar el SoT Laravel.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 11), TypeScript/JavaScript (Node.js 20 LTS, Nuxt 3 / Vue 3.4+)  
**Primary Dependencies**: Paquet **JWT d’API** a Laravel (p. ex. `tymon/jwt-auth` o equivalent acordat) com a **únic emissor** de tokens de sessió; Pinia (Nuxt) per `auth` global; Socket.IO 4.7 amb verificació JWT al `handshake`; **Google Maps JavaScript API** (vista mapa cerca, overlay); Google AI SDK (Gemini 1.5 Flash), node-qrcode 1.5, Ticketmaster **Discovery API** (llista/cerca sincronitzada), **Discovery Feed 2.0** (import admin), Top Picks client (HTTP); `spatie/laravel-permission` (o equivalent) per rols comprador / validador / administrador  
**Storage**: PostgreSQL 16 + PostGIS 3.4 (domini, validacions, entrades); esquema versionat en **`database/init.sql`** + **`database/inserts.sql`** (sense migracions Laravel per al DDL); Redis 7.2 (holds, opcionalment rate limiting); **Adminer 4.8.1** al compose dev (inspecció de taules, p. ex. port 8080)  
**Testing**: PHPUnit / Pest (Laravel), Vitest (Nuxt), **Cypress** (E2E Nuxt + `cy.request` contra l’API Laravel; vegeu la secció **Inicialització de Cypress (monorepo)** més avall), proves de contracte sobre OpenAPI (`contracts/`)  
**Target Platform**: Servidor Linux/containers (docker-compose); clients web (Nuxt SSR/SPA) i navegador validador  
**Project Type**: Monorepo web: API REST + app Nuxt + servei Socket.IO  
**Performance Goals**: SC del spec (validació successiva de fins a 6 credencials &lt; ~60 s pont a pont en proves; p95 validació API coherent amb ús a porta)  
**Constraints**: Validació **sense offline** per a estat «usat»; SoT al servidor; secrets només backend/worker QR  
**Scale/Scope**: MVP esdeveniments limitats, fins a 6 seients per hold, integració Top Picks segons clau `TICKETMASTER_API_KEY`

### Inicialització de Cypress (monorepo)

Alineat amb [tasks.md](./tasks.md) (branca per tasca + DoD amb proves). La base del projecte de proves és **`frontend-nuxt/`** (la UI és Nuxt; les crides API es fan amb `cy.request` cap a la URL de Laravel, normalment en paral·lel al dev server).

1. **Requisits**: Node **20** (mateixa línia que la constitució); `backend-api` i `frontend-nuxt` accessibles (p. ex. `docker compose -f docker/dev/docker-compose.yml up` segons [quickstart.md](./quickstart.md)).

2. **Instal·lar Cypress** dins del paquet Nuxt (triar el gestor que useu al monorepo):

   ```bash
   cd frontend-nuxt
   npm install -D cypress
   ```

   (Amb **pnpm**: `pnpm add -D cypress`; amb **yarn**: `yarn add -D cypress`.)

3. **Descarregar binaris del navegador** (primera vegada o en CI):

   ```bash
   cd frontend-nuxt
   npx cypress install
   ```

4. **Generar l’estructura inicial** (crea `cypress/` i fitxers per defecte si encara no existeixen):

   ```bash
   cd frontend-nuxt
   npx cypress open
   ```

   Al assistent, triar **E2E Testing**, configurar **`cypress.config.js`** (projecte sense TypeScript al codi de l’app; vegeu `.cursor/rules/agents-stack.mdc`) amb almenys:
   - **`baseUrl`**: URL on escolta Nuxt en dev (p. ex. `http://localhost:3000`; el port ha de coincidir amb el compose / `nuxt dev`).
   - Opcional: variables d’entorn **`CYPRESS_API_URL`** (p. ex. `http://localhost:8000` o el host del contenidor Laravel) per prefixar `cy.request()` sense duplicar URLs als tests — definir-les a `frontend-nuxt/.env` amb prefix `CYPRESS_` (Cypress les injecta automàticament) o a `env` dins de `cypress.config`.

5. **Estructura de carpetes recomanada** (coherent amb `tasks.md`):

   ```text
   frontend-nuxt/
   ├── cypress/
   │   ├── e2e/
   │   │   ├── api/          # Proves cy.request (auth, holds, tickets, …)
   │   │   └── flows/        # Fluxos de pantalla (login, mapa de seients, checkout, …)
   │   ├── fixtures/
   │   └── support/
   │       ├── e2e.js
   │       └── commands.js   # Comandes personalitzades (p. ex. login amb token)
   └── cypress.config.js
   ```

6. **Scripts npm** (afegir a `frontend-nuxt/package.json`):

   ```json
   "scripts": {
     "cypress:open": "cypress open",
     "cypress:run": "cypress run"
   }
   ```

7. **Execució sense UI (CI / terminal)**:

   ```bash
   cd frontend-nuxt
   npm run cypress:run
   ```

**Nota**: Proves que depenguin de **Socket.IO** poden requerir plugin (`cypress-plugin-socket-io`) o tests d’integració al `socket-server`; el stress de càrrega segueix sent **k6 / Artillery** ([tasks.md](./tasks.md) **T053**), no Cypress.

### Esdeveniments Socket.IO (contracte lògic)

Els noms són orientatius; cal documentar el payload mínim al `socket-server` i al client Nuxt.

| Esdeveniment (orientatiu) | Quan | Payload mínim (idea) |
|---------------------------|------|----------------------|
| `seat:contention` o similar | Segon usuari perd la cursa per un seient (post bloqueig de fila) | `seatId`, `message`: «Aquest seient acaba de ser seleccionat per un altre usuari» |
| `hold:sync-time` o `countdown:resync` | Desfasament &gt; 2 s entre client i servidor | `expiresAt` (ISO) o `remainingSeconds` |
| `ticket:validated` | Després de `POST` validació OK (ticket utilitzat) | `ticketId`, `status`: `used` (room per `userId` de l’Assistent) |
| `order:payment-timeout` (opcional) | Hold expirat sense pagament confirmat | `holdId` / `orderId` per refrescar UI |
| `admin:metrics` (orientatiu) | Tick periodicitat o esdeveniment | Comptadors orientatius: sockets autenticats, vendes recent (no substitueix SoT comptable) |

**Socket.IO híbrid** ([spec.md](./spec.md) **FR-014**, Session 2026-04-15):  
- **Lectura pública**: subscripció a un **canal per `eventId`** (o equivalent) **sense JWT** per rebre broadcasts d’estat de seients (colors, disponibilitat) després que el backend hagi validat el canvi.  
- **Escriptura**: només via **API Laravel** (JWT per accions d’usuari autenticat; hold anònim segons contracte).  
- **Rooms privades**: connexions amb **JWT** al `handshake` per a `ticket:validated`, `user:{id}`, **Administrador** (mètriques), etc.

**Gemini**: motor de **recomanació** al backend: sortida **JSON** amb IDs d’esdeveniments per al feed; **mai** compra ni reserva; disponibilitat sempre **PostgreSQL** via Laravel (`GEMINI_API_KEY` al backend).

## Constitution Check

*GATE: Abans de la fase 0 i després del disseny de la fase 1.*

Verificar contra `.specify/memory/constitution.md` (projecte Entrades / ticketing):

- **Font de veritat**: Tot escriptura d’estat de negoci i persistència autoritària passa pel backend Laravel i PostgreSQL/PostGIS; el client Nuxt i Socket.IO no defineixen veritat pròpia per sobre de l’API.
- **Stack**: Les tecnologies i versions del pla coincideixen amb la constitució (Laravel 11 / PHP 8.3, Nuxt 3 + Vue 3.4+ / Pinia, Node 20 + Socket.IO 4.7, PostgreSQL 16 + PostGIS 3.4, Redis 7.2, Gemini 1.5 Flash via Google AI SDK), o l’excepció està documentada i justificada a *Complexity Tracking*.
- **Temps real**: Els fluxos Socket.IO són coherents amb estat ja validat o explícitament coordinats amb l’API; no substitueixen confirmacions crítiques on calgui.
- **IA**: L’ús de Gemini és assistencial, amb validació i sense exposar secrets ni dades sensibles sense política explícita.

**Estat post-disseny (fase 1)**: Sense violacions; tres paquets (`backend-api`, `frontend-nuxt`, `socket-server`) estan justificats per separació de responsabilitats (SoT API vs UI vs QR/temps real).

### Revisió constitucional (T040 — fase 6)

Després de **T035–T039** i artefactes associats:

- **SoT Laravel + PostgreSQL**: holds, comandes, tickets, transferències i amistats es persisteixen i validen només a l’API; el client no redefineix estat crític.
- **Redis**: TTL de holds i claus segueixen el disseny existent; sense canvi de rol respecte la constitució.
- **Socket.IO**: subordinat a l’API (notificacions `admin:metrics`, validació, etc.); els fluxos nous no fan del socket font de veritat per a negoci.
- **Excepcions ja documentades**: vegeu *Complexity Tracking* (p. ex. Laravel 13 vs «11» al text de constitució, `firebase/php-jwt`); no s’afegeixen noves excepcions per aquesta fase.

## Project Structure

### Documentació (aquesta funcionalitat Speckit)

```text
specs/001-seat-map-entry-validation/
├── plan.md           # Aquest fitxer
├── research.md       # Fase 0
├── data-model.md     # Fase 1
├── quickstart.md     # Fase 1
├── contracts/        # Fase 1 (OpenAPI)
└── tasks.md          # /speckit.tasks (no generat aquí)
```

### Arrel del repositori d’aplicació (monorepo proposat)

L’estructura inicial acordada per al projecte **`DAWTR3XXX-AppEsdeveniments`** (substituir `XXX` pel codi del grup):

```text
DAWTR3XXX-AppEsdeveniments/
├── backend-api/                 # API Laravel 11
├── frontend-nuxt/               # Nuxt 3 + Pinia
├── socket-server/               # Node.js 20 + Socket.IO 4.7 (+ node-qrcode per SVG)
├── database/
│   ├── init.sql                 # Esquema PostGIS i taules
│   └── inserts.sql              # Dades de prova (mock)
├── docs/                        # Documentació SDD
│   ├── Constitution.md          # Pots enllaçar o reflectir .specify/memory/constitution.md
│   ├── Specify.md
│   ├── Plan.md
│   └── TasksMvp.md              # Generat per OpenSpec / tasques
├── docker/                      # Orquestració separada dev / prod + Dockerfiles
│   ├── dev/
│   │   ├── docker-compose.yml   # (o compose.yaml) Stack per programar: bind mounts, hot reload, ports locals
│   │   └── README.md            # Com arrencar l’entorn de desenvolupament
│   ├── prod/
│   │   ├── docker-compose.yml   # Stack producció: imatges construïdes, sense codi muntat en volums
│   │   └── README.md            # Variables, secrets i desplegament
│   └── dockerfiles/             # Un Dockerfile per servei d’aplicació (versions constitució)
│       ├── backend-api/
│       │   └── Dockerfile       # PHP 8.3 + extensions pgsql/redis; multi-stage opcional (dev vs prod)
│       ├── frontend-nuxt/
│       │   └── Dockerfile       # Node 20; target dev (nuxt dev) vs prod (nuxt build + node)
│       ├── socket-server/
│       │   └── Dockerfile       # Node 20 LTS + dependències Socket.IO / node-qrcode
│       └── README.md            # Context de build (`docker build -f ...`), tags i ús des de dev/prod compose
└── .gitignore                   # node_modules, vendor, .env
```

**Structure Decision**: El codi de producte viu en **`backend-api`**, **`frontend-nuxt`** i **`socket-server`**. Els scripts SQL van a **`database/`**. La documentació de curs (`docs/`) complementa els artefactes Speckit sota **`specs/`**.

### Interfície Nuxt (mapa de pantalles)

- **Layout consumidor (rol Usuari)**: **`layouts/default.vue`** (o equivalent) amb **Header** (desktop) i **Footer fix** (mòbil) amb **6 seccions** normatives: **Home**, **Buscador + Mapa**, **Tickets**, **Guardats**, **Social**, **Perfil** ([spec.md](./spec.md) **FR-015**).
- **Layout administrador**: **`layouts/admin.vue`** amb **Sidebar** lateral; rutes sota prefix p. ex. `/admin/*` amb middleware rol `admin` + API protegida.
- **Descobriment**: endpoints Laravel que encapsulin **Discovery API** (cerca, filtres) i serveixin coordenades **PostGIS** als marcadors del mapa; **Gemini** només com a **recomanació** (JSON d’IDs); catàleg i compra = **Laravel + PostgreSQL**.
- **Staff / Validador (vista E producte)**: mateixa app Nuxt, ruta dedicada **mòbil-first** (`/admin/access` o `/validator/scan` segons convenció); **getUserMedia** + biblioteca lectora QR en **web** (p. ex. ZXing-js, html5-qrcode); *Flutter / mobile_scanner* només com a alternativa futura fora del stack constitucional Nuxt.

- **`docker/dev/`**: és l’entorn que useu **per programar** (volums cap al codi font, `command` de desenvolupament, ports exposats al host). El compose referencia les imatges construïdes des de **`docker/dockerfiles/*/Dockerfile`** (o `build.context` cap als directoris d’app).
- **`docker/prod/`**: compose per **producció** (mateixos Dockerfiles amb `target` prod o fitxers separats si cal), sense muntar el repositori com a volum d’edició; Postgres **16** + Redis **7.2** via imatges oficials al compose (no cal Dockerfile propi si no es personalitzen).
- **`docker/dockerfiles/`**: conté **tots els Dockerfiles necessaris** per als tres serveis d’aplicació. Postgres/PostGIS i Redis es defineixen al compose amb `image: postgres:16` (variant PostGIS si s’escau) i `image: redis:7.2`; només cal Dockerfile addicional si en el futur cal una imatge DB personalitzada.

Això substitueix qualsevol `docker-compose.yml` únic a l’arrel: la convenció és **`docker/dev`** vs **`docker/prod`** explícits.

### Fitxers `.env` per directori (plantilles)

Cada servei té el seu **`.env`** (no versionat; `.env.example` sí):

| Directori | Variables clau |
|-----------|----------------|
| **backend-api/.env** | `DB_CONNECTION=pgsql`, `DB_*`, `REDIS_*`, `TICKETMASTER_API_KEY`, `GEMINI_API_KEY`, `JWT_SECRET` (o claus del paquet JWT), secrets signatura QR ticket, `SOCKET_SERVER_INTERNAL_URL` (si cal cridar Node) |
| **frontend-nuxt/.env** | `NUXT_PUBLIC_API_URL`, `NUXT_PUBLIC_SOCKET_URL`, `NUXT_PUBLIC_GOOGLE_MAPS_KEY` |
| **socket-server/.env** | `REDIS_URL`, `JWT_SECRET` (mateixa clau o clau pública segons esquema) per validar **JWT d’API** al handshake i JWT de payload QR si el worker genera SVG aquí |

**Nota**: La font de veritat de negoci roman al **Laravel**; el `socket-server` valida els **JWT d’API** amb les mateixes claus que l’API (handshake Socket.IO) i, per QR, o bé rep SVG del backend o valida el token de ticket segons disseny.

## Complexity Tracking

> Sense violacions de constitució que requerisquin justificació addicional.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Laravel 13 (skeleton `composer create-project` 2026) vs constitució «Laravel 11» | Plantilla oficial actual instal·la `laravel/framework:^13`; el codi del monorepo ja està sobre aquesta base. | Forçar `^11.0` implica reinstal·lar dependències i provar compatibilitat; es pot fer en una tasca d’alineació explícita. |
| JWT d’API via `firebase/php-jwt` (HS256) en lloc de `php-open-source-saver/jwt-auth` | L’entorn PHP local (Wamp) pot mancar `ext-sodium`, requerit per dependències recents del paquet JWT clàssic; HS256 amb secret compartit cobreix auth API + validació al Node. | Reintentar paquet JWT «tot en un» quan `ext-sodium` estigui activat o en contenidor Docker amb extensions completes. |

## Phase 0 & 1 outputs

- **research.md**: Decisions d’integració Top Picks, claus Redis per holds, repartiment JWT/QR, esdeveniments Socket.IO.
- **data-model.md**: Entitats i transicions (hold → **pending_payment** → paid → ticket **venuda** → **utilitzada**), UUID al ticket, bloqueig de fila per seient.
- **contracts/openapi.yaml**: Esborrany d’endpoints REST principals.
- **quickstart.md**: Arrencada amb Docker i variables d’entorn.

Vegeu fitxers al mateix directori que aquest pla.
