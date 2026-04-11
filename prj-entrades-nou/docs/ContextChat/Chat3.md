# Context del chat 3 — Tasques Speckit T025–T029, Git i entrega US2

Aquest document resumeix el **fil d’aquest agent** sobre el monorepo **prj-entrades-nou**, feature **`001-seat-map-entry-validation`**, perquè un **nou agent** pugui continuar amb el mateix criteri.

**Relació amb xats anteriors:** el [Chat1](./Chat1.md) cobreix decisions de disseny (friend_invites, Socket híbrid, workflow branca + Cypress). El [Chat2](./Chat2.md) resumeix la implementació US1 (holds, orders, seats, Docker). El **Chat 3** documenta el **cicle d’execució de tasques T025–T029**, el **flux Git real** al repositori pare **`3-Tr3`**, i els **canvis concrets** a backend, socket-server i frontend Nuxt.

---

## 1. Abast i referències

- Especificació: `specs/001-seat-map-entry-validation/` (**`tasks.md`** com a font d’estat de tasques).
- Codi: `backend-api/` (Laravel), `frontend-nuxt/` (Nuxt 3 + Pinia + JS), `socket-server/` (Node / Socket.IO + QR).
- **Base de dades (estat del repositori, posterior al fil Chat2):** l’esquema relacional es manté en SQL al monorepo — **`database/init.sql`**, **`database/inserts.sql`**; proves amb **`database/testing/schema.sqlite.sql`** i **`Tests\Concerns\RefreshDatabaseFromSql`**. **Adminer** al compose dev (p. ex. port **8080**). Qualsevol canvi de taules ha anar als fitxers SQL (i al `data-model.md`), no a migracions PHP de DDL. Vegeu **`database/README.md`** i **`docs/SpeckitInformació.md`**.
- **Git (important):** el repositori Git està a la carpeta pare **`3-Tr3`**, no dins només `prj-entrades-nou`. El codi de l’app viu com a **`prj-entrades-nou/`** dins aquest repositori. El remot té **`main`** i **`dev`**; el flux Speckit del projecte integra a **`dev`**.

---

## 2. Resum de conversa (ordre cronològic)

1. **Context Speckit:** lectura dels `.md` de `docs/ContextChat` i **`SpeckitInformació.md`**; resum dels **següents passos** (implementació segons `tasks.md`, branques `001-seat-map-tNNN-…`, PR/merge cap a `dev`, Cypress).
2. **T025** (executada per l’agent): tickets JWT en confirmar pagament (`paid`), model **`Ticket`**, **`JwtTicketService`**, resposta **`confirm-payment`** amb `tickets[]`, **`CypressOrderFlowSeeder`**, prova Cypress **`order-tickets.cy.js`** + task al `cypress.config.js`.
3. **Flux Git demanat per l’usuari:** `pull` → branca de tasca → `commit` → `push` → `merge` a **`dev`** → `push` de `dev`. S’ha creat **`dev`** des de `origin/main` quan calia, i s’han pujat les branques de feature.
4. **T026:** **`socket-server/src/qr/generateTicketSvg.js`** (equivalent ESM al `.ts` citat al pla; sense toolchain TS), **`POST /internal/qr-svg`**, **`SocketTicketSvgClient`** a Laravel, proves PHPUnit + **`qr-svg.cy.js`**.
5. **T027:** **`GET /api/tickets/{ticketId}/qr`** (`TicketController::showQr`), paràmetre **UUID** explícit perquè el middleware JWT pugui respondre **401** sense binding implícit; renovació de **`jwt_expires_at`** si ha caducat; **`docker/dev/docker-compose.yml`**: `SOCKET_SERVER_INTERNAL_URL=http://socket-server:3001` i **`depends_on: socket-server`** per l’API al contenidor.
6. **T028:** **`GET /api/tickets`** (historial), **`TicketController::index`**, resposta **`{ tickets: [...] }`** amb `event`, `seat`, etc.; **`TicketsListApiTest`**, **`tickets-list.cy.js`**.
7. **T029:** **`pages/tickets/index.vue`** i **`pages/tickets/[ticketId].vue`**, **`composables/useAuthorizedApi.js`**, **`plugins/auth-session.client.js`** (claus `speckit_auth_token` / `speckit_auth_user`), **`middleware/auth.js`** amb **salt a SSR** (`import.meta.server`) per no redirigir sense token al servidor; detall resol l’entrada des de **`GET /api/tickets`** i demana el SVG per **`fetch`**; **`cypress/e2e/flows/tickets-pages.cy.js`** amb intercepts (cal **`npm run dev`** a `:3000` per executar Cypress).

---

## 3. Seqüència Git repetida (per tasca)

Des de l’arrel **`3-Tr3`**:

```bash
git checkout dev && git pull origin dev
git checkout -b 001-seat-map-tNNN-<slug>
# canvis sota prj-entrades-nou/ (i només el necessari)
git add …
git commit -m "feat(TNNN): …"
git push -u origin 001-seat-map-tNNN-<slug>
git checkout dev
git merge 001-seat-map-tNNN-<slug> --no-ff -m "Merge branch '…' into dev (TNNN)"
git push origin dev
```

**Nota:** la carpeta **`prj-entrades-a24biedommar/`** (si existeix al mateix repositori) no s’ha inclòs als commits descrits en aquest fil; només s’ha versionat **`prj-entrades-nou/`** on corresponia.

---

## 4. Branques i tasques vinculades (aquest chat)

| Tasca | Branca exemple | Àmbit principal |
|--------|----------------|-----------------|
| T025 | `001-seat-map-t025-ticket-jwt` | Laravel: tickets + JWT, Cypress order flow |
| T026 | `001-seat-map-t026-qr-svg` | Socket: `/internal/qr-svg`, Laravel client HTTP |
| T027 | `001-seat-map-t027-qr-endpoint` | Laravel: GET QR SVG; Docker compose API→socket |
| T028 | `001-seat-map-t028-tickets-list` | Laravel: GET llista tickets |
| T029 | `001-seat-map-t029-tickets-pages` | Nuxt: pàgines entrades + sessió + E2E flows |

Estat al **`tasks.md`:** T025–T029 marcades com fetes (**[X]**) al moment de tancar aquest context.

---

## 5. Estat lògic del producte (després de T029)

- **US2 (tickets / QR / llista / UI):** flux **pagament confirmat → tickets a BD → QR per API → llista API → UI Nuxt** alineat amb T028/T027.
- **Següent bloc Speckit (Phase 5 / US3):** **T030** — `POST /api/validation/scan` (rol validador), després T031–T034 (socket `ticket:validated`, UI validador, etc.).

---

## 6. Idioma i convencions

- Respostes de l’assistent a l’usuari: sovint **castellà** (regles d’usuari); documentació del projecte i **`tasks.md`** en **català**. Un agent ha de **respectar l’idioma del fitxer** que editi.

---

## 7. Metadades d’aquest fitxer

| Camp | Valor |
|------|--------|
| Fitxer | `docs/ContextChat/Chat3.md` |
| Propòsit | Handoff de context del chat 3 (T025–T029 + Git) |
| Feature | `001-seat-map-entry-validation` |
| Data referència entorn | 2026-04-11 |

---

*Fi del context Chat3.*
