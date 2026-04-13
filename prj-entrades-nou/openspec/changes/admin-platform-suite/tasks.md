# Tasques — `admin-platform-suite`

Ordre suggerit: contracte i dades → backend → socket → frontend → proves → documentació.

**Especificació de requisits**: `openspec/specs/admin-platform-suite/spec.md`

### Regles d’execució (obligatòries)

- **Flux Git i qualitat**: cada bloc (p. ex. per pàgina A–E) es desenvolupa en una **branca nova des de `dev`**, amb **tot** el codi del bloc, **proves al 100%** (navegador per frontend admin + tests backend; socket si escau), després **commit, push i merge a `dev`**. Vegeu **`WORKFLOW.md`** en aquesta carpeta (mapatge seccions ↔ pàgines, nomenclatura de branques, checklist).
- **Cap tasca a mitges**: no es marca **`[x]`** una sub-tasca fins que la funcionalitat associada estigui implementada i verificada; no es fusiona el bloc amb funcionalitat parcial o tests fallant.
- **Següent bloc**: després del merge, nova branca des de `dev` actualitzat; es repeteix el cicle fins a completar totes les caselles.

---

## 1. Contracte i model de dades

- [ ] 1.1 Revisar taules `events`, `orders`, `tickets`, `users`, rols Spatie; documentar camps per **ingressos dia**, **pending_payment**, **hidden_at**, **aforament**.
- [ ] 1.2 Ampliar `specs/001-seat-map-entry-validation/contracts/openapi.yaml` (o delta acordat) amb paths `GET/PATCH/POST/DELETE` necessaris sota `/api/admin/...` (dashboard, discovery search, import, monitor, usuaris, informes).
- [ ] 1.3 Decidir persistència d’**alertes sync** (últim run vs taula `admin_sync_runs`); si cal migració Laravel, afegir migració i model.

---

## 2. Backend — Dashboard global (A)

- [ ] 2.1 Implementar **ingressos del dia** (TZ `Europe/Madrid` o la definida al `.env`) sobre comandes/tiquets pagats.
- [ ] 2.2 Implementar recompte de comandes en estat **`pending_payment`** (o nom real del enum `Order`).
- [ ] 2.3 Exposar **alertes** de sync TM (errors de l’últim job o llista curta) al payload del dashboard.
- [ ] 2.4 Estendre `AdminController::summary` o crear `AdminDashboardController` + servei dedicat (`app/Services/Admin/`) amb respostes no stub.
- [ ] 2.5 Tests feature: admin rep 403 sense rol; amb `admin` rep camps esperats.

---

## 3. Backend — Presència / usuaris en viu (alimentació socket)

- [ ] 3.1 Definir estratègia de **presència** (heartbeat + Redis o alternativa) i documentar-la al `design.md` en actualitzar *Open Questions*.
- [ ] 3.2 Implementar increment/decrement segur (connexió/desconnexió, TTL) al **socket-server** i/o Laravel.
- [ ] 3.3 Publicar canvis de recompte cap als clients admin (nom de esdeveniment acordat amb el frontend).
- [ ] 3.4 Integració: el dashboard admin subscriu el canal i mostra el número (vegeu secció 6).

---

## 4. Backend — CRUD esdeveniments i Discovery (B)

- [ ] 4.1 Endpoint **cerca Discovery** (proxy al client Ticketmaster existent) amb paràmetres de cerca i paginació segons API TM.
- [ ] 4.2 Endpoint **importar per ID TM** (o payload seleccionat) reutilitzant `TicketmasterEventImportService` / lògica d’`insert` existent.
- [ ] 4.3 Completar **POST/PATCH** esdeveniment manual (nom, dates, venue, imatge, preu) validant camps i coherència amb venues.
- [ ] 4.4 Confirmar **DELETE** o **PATCH hidden_at** com a única via d’“eliminar” per al catàleg públic; proves de que els llistats públics exclouen ocults.
- [ ] 4.5 Tests feature per import, edició de preu i ocultació.

---

## 5. Backend — Monitor esdeveniment (C)

- [ ] 5.1 **GET** mètriques per `eventId`: venuts, restants, recaptació total, (opcional) llista de holds des de Redis amb TTL si l’API pot llegir les mateixes claus que el socket.
- [ ] 5.2 Reutilitzar `GET /events/{id}/seatmap` o endpoint admin que retorni el mateix payload que el mapa usuari per **carrega inicial**.
- [ ] 5.3 Assegurar que els esdeveniments Socket per canvis de seient ja emesos pel sistema són els que consumeix la vista admin (mateix `eventId` / room).

---

## 6. Socket-server — Temps real

- [ ] 6.1 Revisar namespaces existents; afegir o reutilitzar subscripció per **dashboard** (métrica presència) sense trencar clients actuals.
- [ ] 6.2 Garantir que el **mapa admin** rep `seat:*` / esdeveniments equivalents als de la vista usuari (documentar noms al `design.md`).
- [ ] 6.3 Opcional: canal **admin-only** amb JWT `role:admin` al handshake.

---

## 7. Backend — Usuaris i tiquets (D)

- [ ] 7.1 `GET /api/admin/users` (paginat, cerca) i `POST` creació amb assignació de rols (`assignRole`).
- [ ] 7.2 `DELETE /api/admin/users/{id}` o política d’**hard delete** / bloqueig segons FK; documentar decisió D8 del `design.md`.
- [ ] 7.3 `GET /api/admin/users/{id}/orders` (i nested tickets) amb estats **validat**, **transferit** si les taules ho reflecteixen.
- [ ] 7.4 Tests feature de autorització i integritat referencial.

---

## 8. Backend — Informes (E)

- [ ] 8.1 Endpoint sèrie temporal (vendes agregades per franja horària o dia) amb filtres `eventId`, rang de dates.
- [ ] 8.2 Endpoint resum **ocupació** (venuts vs aforament) per esdeveniment.
- [ ] 8.3 Validació de rangs i límit de query per evitar scans massius.

---

## 9. Frontend Nuxt — Pinia i socket

- [ ] 9.1 Crear o estendre stores (`useAdminDashboardStore`, `useAdminSeatmapStore`, …) seguint `Agents/frontend/AgentPinia.md` (sense `.map`/`filter` en lògica de domini si cal substituir per bucles).
- [ ] 9.2 Plugin/composable socket client per **dashboard** i per **detall esdeveniment** admin; actualització Pinia en cada esdeveniment.
- [ ] 9.3 Garantir que el **layout admin** no mostra entrada a fluxos de **validador** (només `admin`).

---

## 10. Frontend Nuxt — Pàgines

- [ ] 10.1 **`pages/admin/index.vue`**: dashboard (usuaris viu, ingressos dia, pending_payment, alertes TM).
- [ ] 10.2 **`pages/admin/events.vue`**: taula + cercador Discovery + import + formulari crear/editar + ocultar.
- [ ] 10.3 **Nova ruta** `pages/admin/events/[eventId]/monitor.vue` (o similar): mapa reutilitzat, comptadors, holds, recaptació.
- [ ] 10.4 **`pages/admin/users.vue`**: llista, crear usuari, detall amb historial de comandes/tiquets.
- [ ] 10.5 **`pages/admin/reports.vue`**: gràfic de línies + gràfic circular amb dades dels nous endpoints.
- [ ] 10.6 Accessibilitat i estats de càrrega/error coherents amb la resta de l’app.

---

## 11. Verificació i qualitat

- [ ] 11.1 `php artisan test` (o subset) pels nous tests API admin.
- [ ] 11.2 `npm run lint` al `frontend-nuxt` i al `socket-server` si hi ha canvis.
- [ ] 11.3 Prova manual: dos navegadors (usuari + admin) mateix `eventId` — colors de seients coherents.
- [ ] 11.4 Opcional: prova E2E Cypress per una ruta admin amb usuari `admin` de `database/inserts.sql`.

---

## 12. Tancament OpenSpec

- [ ] 12.1 Executar `openspec verify-change` (o checklist interna) quan l’implementació estigui llesta.
- [ ] 12.2 Arxivar el canvi segons `.cursor/skills/openspec-archive-change/SKILL.md` quan el PR estigui fusionat (si aplica al flux de l’equip).
