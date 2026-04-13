# Research: Mapa de seients, bloquejos, entrades segures i validació

## 1. Ticketmaster Top Picks (imatge i zones)

**Decision**: El **backend-api** (Laravel) encapsula les crides a l’API Discovery / **Top Picks** de Ticketmaster amb `TICKETMASTER_API_KEY`; exposa al Nuxt un recurs estable (p. ex. `GET /api/events/{id}/seatmap`) que retorna `snapshotImageUrl` i metadades de zones ja adaptades al model intern.

**Rationale**: La clau i els límits de ràtio queden només al servidor; es pot cachejar la resposta (Redis o DB) per esdeveniment amb TTL curt si cal.

**Alternatives considered**: Cridar Top Picks des del Nuxt (rebutjada: exposa claus o proxy insegur).

## 2. Hold atòmic N≤6 a Redis

**Decision**: Clau Redis per hold de sessió de compra, p. ex. `hold:{eventId}:{holdId}` amb valor JSON (set de seat ids, `userId` o `sessionId`, `expiresAt`, `login_grace_applied`). Operació **SET** amb NX o script Lua per **atomic check-and-set** de fins a 6 seients: si algun seient ja està en un altre hold vàlid, falla tot el grup. TTL **inicial** = **240 s** (4 min) línia base; **excepció**: una sola **`PEXPIRE` +120 s** quan es detecta login/registre des del checkout (**total màxim efectiu 360 s** en aquesta política). Flag per evitar repetició.

**Rationale**: Redis 7.2 encaixa amb la constitució; latència baixa per contenció de venda.

**Alternatives considered**: Només Postgres advisory locks (més pesat per rotació ràpida de holds).

## 3. JWT d’API (sessió) + Socket.IO híbrid + JWT de ticket + QR SVG (node-qrcode 1.5)

**Decision**: **Laravel** és l’**emissor** de **JWT d’API** (registre, login, `me`, middleware de rutes protegides). **Socket.IO híbrid** ([spec.md](./spec.md) **FR-014**): **canal públic** per `eventId` per a **broadcast** d’estat de seients (lectura sense JWT); **handshake amb JWT** per a **rooms privades** (`user:{id}`, admin, mètriques). Per als bitllets, el **Laravel** emet JWT de credencial (claims: `ticket_id`, `event_id`, `seat_id`, `jti`, `exp`). La generació **SVG** amb **node-qrcode v1.5** al **socket-server** (o invocació des del backend); el client rep només el SVG o URL curta.

**Rationale**: Assumpció del spec: node-qrcode al worker Node; JWT sempre verificables pel backend en validació.

**Alternatives considered**: QR generat en PHP (possible però duplica lògica; el spec fixa node-qrcode v1.5).

## 4. Validació i temps real

**Decision**: Endpoint **POST** autenticat amb rol **validador** (Laravel): llegeix JWT del QR, comprova `jti` no reutilitzat, marca ticket `used_at` + `validator_id` a Postgres; després **emissió Socket.IO** (room per `userId`) amb payload mínim `{ ticketId, status: 'used' }` perquè el Nuxt actualitzi Pinia i mostri la «X».

**Rationale**: Estat «usat» només després de persistència; Socket.IO és notificació, no font de veritat.

**Alternatives considered**: Validació només via WebSocket (rebutjada: viola SoT).

## 5. Gemini 1.5 Flash (recomanació)

**Decision**: **Gemini** al backend per al feed «Triats per a tu»: retorna **JSON** amb **IDs d’esdeveniments**; **mai** executa compres. Validació de sortides abans de mostrar-les; minimització de dades (constitució V). La compra és **sempre** Laravel + PostgreSQL.

**Rationale**: Assistencial; no substitueix SoT ni el mapa de seients.

## 6. PostGIS

**Decision**: `init.sql` activa PostGIS; taules de **venue/seat** poden tenir `geometry` o referències TM mapejades; consultes geo per proximitat són opcionals al MVP si el mapa és principalment imatge + zones TM.

**Rationale**: Constitució exigeix PostGIS 3.4; l’esquema concret és al `data-model.md`.
