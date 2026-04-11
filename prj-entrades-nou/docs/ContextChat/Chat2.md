# Context del chat 2 — Implementació Speckit (US1) i Docker Nuxt

Aquest document resumeix el **fil d’aquest agent** (converses amb l’assistent) sobre el monorepo **prj-entrades-nou**, feature **`001-seat-map-entry-validation`**, perquè un **nou agent** pugui continuar amb el mateix criteri.

**Relació amb Chat1:** el [Chat1](./Chat1.md) cobreix decisió de disseny inicial, OpenAPI, constitució, etc. El **Chat 2** se centra en el que es va **implementar i depurar** després (backend holds/orders, frontend mapa de seients, Docker).

---

## 1. Abast i referències

- Especificació: `specs/001-seat-map-entry-validation/` (`spec.md`, `plan.md`, `data-model.md`, **`tasks.md`**).
- Codi principal: `backend-api/` (Laravel), `frontend-nuxt/` (Nuxt 3 + Pinia + JS), `socket-server/` (Socket.IO).

---

## 2. Decisions tècniques recordades en aquest fil

### 2.1 JWT (API)

- No s’ha usat `php-open-source-saver/jwt-auth` (requisit **ext-sodium**); s’usa **`firebase/php-jwt`** (HS256) amb `JwtTokenService`, middleware `AuthenticateJwt`, `config/jwt.php`: **`JWT_SECRET` preferit**, fallback a **`APP_KEY`** si no hi ha `JWT_SECRET` explícit (dev).

### 2.2 Holds (Redis / cache)

- Clau cache: `hold:byid:{uuid}` (compatible amb `CACHE_STORE=array` als tests).
- `SeatHoldService`: transacció DB + `lockForUpdate()`, estat `held`, `login_grace` +120 s (màx. 360 s des de `created_at`), `InternalSocketNotifier` HTTP cap a `SOCKET_SERVER_INTERNAL_URL/internal/emit`.

### 2.3 Socket-server

- `POST /internal/emit` (JSON: `room`, `event`, `payload`), header opcional `X-Internal-Secret` si `SOCKET_INTERNAL_SECRET` està definit.
- Connexió pública: query `eventId` i `anonSession` → sales `event:{id}` i `anon:{session}`.
- Important: `io` s’ha de crear **abans** del handler HTTP que fa servir `io.to(room).emit` (es va refactoritzar per evitar TDZ).

---

## 3. Backend — què hi ha implementat (resum)

| Àrea | Fitxers / notes |
|------|------------------|
| Holds | `HoldController`, `SeatHoldService`, migració camps hold a `seats`, rutes `POST/DELETE/GET` holds |
| Orders | `POST /api/orders` (JWT) `pending_payment`, `PendingPaymentOrderService`, columna `hold_uuid` a `orders` |
| Confirmació T021 | `POST /api/orders/{order}/confirm-payment` → `paid` o error **«Seient ja no disponible»** + `Order::STATE_FAILED` + `forceReleaseHold` |
| Seatmap + seients (T023) | `PostgresSeatmapFallbackService::seatsForEvent()`, resposta JSON amb **`seats`**: `{ id, zoneId, key, status }` |
| Tests PHPUnit | `HoldApiTest`, `OrderApiTest`, `SeatmapApiTest` ampliat; `phpunit.xml` inclou `APP_KEY` per evitar `MissingAppKeyException` a `ExampleTest` |

---

## 4. Frontend — què hi ha implementat (resum)

| Àrea | Notes |
|------|--------|
| Pàgina T023 | `pages/events/[eventId]/seats.vue` — seatmap, selecció fins a 6, reserva, compte enrere, polling `GET /api/holds/{id}/time`, Socket `seat:contention` i `countdown:resync` |
| Store T024 | `stores/hold.js` — sessió anònima (`sessionStorage`), selecció, hold, resync |
| Composable | `composables/useEventSeatSocket.js` — `socket.io-client` |
| Dependència | `socket.io-client` al `package.json` |
| Cypress | `cypress/e2e/flows/seats-page.cy.js` (intercept API) |
| Enllaç | `pages/index.vue` enllaç d’exemple cap al mapa |

---

## 5. Estat de `tasks.md` (fase US1 en el moment del chat)

Marcades com fetes (entre d’altres): **T016–T024** (holds, login-grace, socket contenció, delete hold, orders pending, confirmació indisponibilitat, temps hold, pàgina seients + store).

**Pendent** (no fet en aquest fil): **T025+** (tickets JWT/QR, validació, UI producte, etc.) — veure `tasks.md` actual.

---

## 6. Docker — problemes i solucions tractats

### 6.1 Build `frontend-nuxt` fallava (Cypress)

- **Símptoma:** `npm install` al Dockerfile descarregava el binari de Cypress (`download.cypress.io`) i fallava (DNS/xarxa al build).
- **Solució:** `docker/dockerfiles/frontend-nuxt/Dockerfile`: `npm ci --omit=dev --ignore-scripts`, després `COPY` del codi i `npm run postinstall` (`nuxt prepare`). Documentat a `docker/dev/README.md`.

### 6.2 `docker compose up` — errors Nuxt (`#app-manifest`, paths `/components/...`, PostCSS)

- **Causes típiques:** barreja de **`.nuxt` / `.output` generats a Windows** amb el contenidor Linux; o **cache** inconsistent; referències fantasma si el volum munta codi antic.
- **Mesures útils:** esborrar `frontend-nuxt/.nuxt` i `frontend-nuxt/.output` al host i tornar a aixecar; opcionalment desactivar DevTools dins Docker (`devtools` / variable d’entorn); en futures millores es pot muntar volum separat només per `.nuxt` (no implementat en aquest fil si l’usuari ja ho va resoldre).

### 6.3 Instruccions d’ús Docker (resum)

Des de l’arrel del monorepo:

```bash
docker compose -f docker/dev/docker-compose.yml build
docker compose -f docker/dev/docker-compose.yml up
```

Migracions (exemple):

```bash
docker compose -f docker/dev/docker-compose.yml exec backend-api php artisan migrate
```

Ports habituals: Nuxt **3000**, API **8000**, Socket **3001**, Postgres **5432**, Redis **6379**.

**JWT alineat amb socket (opcional):** el compose posa `JWT_SECRET` al `socket-server`; convé el mateix secret al `backend-api/.env` per proves amb namespace privat.

---

## 7. Proves

- Backend: `cd backend-api && php artisan test`.
- Frontend build: `cd frontend-nuxt && npm run build`.
- L’usuari ha confirmat poder **aixecar l’app correctament** amb Docker després dels ajustos.

---

## 8. Continuació lògica del treball

1. **T025+** (tickets després de `paid`, QR, llistats, UI entrades).
2. Flux Nuxt complet: login → comanda → confirmació pagament (encara pot ser fino respecte al que ja fa l’API).
3. Actualitzar **OpenAPI** / **Cypress** API segons DoD del Speckit.

---

*Document generat per resumir el context del **Chat 2** amb l’agent; revisar `tasks.md` per l’estat real de les caselles.*
