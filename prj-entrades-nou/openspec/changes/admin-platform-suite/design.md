## Context

Monorepo **prj-entrades-nou**: **Laravel 11** (API JWT + Spatie `admin`), **Nuxt 3** (JS, Pinia), **Socket.IO** + **Redis** per holds i temps real, **PostgreSQL** (+ PostGIS segons constitució). Ja existeixen rutes **`/api/admin/*`** (`AdminController`: `summary`, `discoverySync`, CRUD esdeveniments parcial amb `hidden_at`, `price`, `tm_sync_paused`) i pàgines **stub** sota `frontend-nuxt/pages/admin/`.

La suite **admin-platform-suite** ha de completar el producte descrit a `openspec/specs/admin-platform-suite/spec.md`.

### Revisió model de dades (tasques 1.1)

- **`orders`**: `state` inclou `pending_payment`, `paid`, `failed`. Els **ingressos del dia** es calculen com a suma de `total_amount` per a comandes `paid` amb `updated_at` dins del **dia natural** a la zona `config('admin.business_timezone')` (per defecte `Europe/Madrid`, variable `ADMIN_BUSINESS_TIMEZONE`).
- **`events.hidden_at`**: ocultació lògica respecte al catàleg públic.
- **Aforament per esdeveniment**: recompte de `seats` per `event_id` (o semàntica del mapa) per a informes i monitor; pendent d’ús explícit als endpoints de monitor (bloc C).

### Flux de desenvolupament i Git

L’equip i els agents han de seguir el document **`WORKFLOW.md`** (mateix directori que aquest `design.md`): una **branca des de `dev` per bloc entregable**, verificació **manual (navegador) + automàtica (tests backend)** al 100%, **merge a `dev`**, marcatge **`[x]`** al `tasks.md` del bloc, i després **següent branca**. No es deixen tasques ni funcionalitats del `tasks.md` a mitges dins d’un merge declarat complet.

## Goals / Non-Goals

**Goals**

- Una **única consola admin** amb les cinc àrees (A–E) amb UX coherent amb el tema existent (layout `admin`).
- **Paritat de mapa**: l’admin veu els mateixos estats de seient que la vista pública (després de validació Laravel), actualitzats per **Socket.IO**; **Pinia** reflecteix immediatament els payloads.
- **Mètriques de negoci** calculades al **backend** (no confiar només en el client per a diners i recomptes oficials).
- **Import Discovery**: flux usable des del panell (cerca + import) reutilitzant `TicketmasterEventImportService` o clients HTTP existents.

**Non-Goals**

- **App mòbil** ni PWA específica d’admin.
- **Rol validador** dins del panell admin (escaneig a porta): fora d’abast; no calen pantalles ni menús de validador aquí.
- **Desglossament financer per categories** a la vista de recaptació per esdeveniment (només total agregat).
- **Multi-tenant** o diversos administradors amb permisos finos (només `admin` tret que el producte ampliï explícitament).

## Decisions

### D1: Font de veritat i ordre d’actualització

**Decisió**: PostgreSQL (via Laravel) és l’autoritat per a estats persistits (venut, comanda); Redis per **holds** i dades volàtils; el socket **retransmet** canvis coneguts després de validació o des de la mateixa lògica que ja usa el mapa usuari.

**Rationale**: Coherent amb `Agents/` i el spec 001 (FR-014).

### D2: Comptador global d’usuaris “connectats”

**Decisió**: **Presència** via `POST /api/presence/ping` (JWT) cada ~30 s des del client (`plugins/presence-ping.client.js`): Laravel escriu `user_id` al ZSET Redis `presence:online_ts` amb score = timestamp; es netegen membres més antics que la finestra (TTL lògic). El recompte `online_users` del dashboard ve del **mateix ZSET** (`zcard` després de neteja). El socket emet `admin:metrics` després del ping (i des del resum) per refrescar el panell en temps real.

**Rationale**: No cal `INCR` global de connexions Socket: el ping REST funciona també sense WebSocket i tolera fallades de Redis (try/catch al controlador).

### D3: Ingressos del dia

**Decisió**: Usar **TZ** del negoci (proposta: `Europe/Madrid`) i interval `[inici del dia natural, ara]` sobre tiquets/comandes **pagades** (o equivalent `STATE_PAID` al codi).

### D4: Alertes sync Ticketmaster

**Decisió**: Persistir o exposar l’**últim resultat** de `discoverySync` / jobs programats: errors agrupats, timestamp, opcionalment llista curta d’errors recents. El dashboard llegeix **GET** dedicat o camp dins `summary` ampliat.

### D5: Soft hide d’esdeveniments

**Decisió**: Reutilitzar **`hidden_at`** (ja previst al `AdminController::updateEvent`). El catàleg públic filtra `hidden_at IS NULL`.

### D6: Reutilització del mapa al Nuxt

**Decisió**: Extreure o reutilitzar el **mateix component / composable** de seat map que la ruta d’usuari (`/events/.../seats` o equivalent), amb **mode** `admin` (només lectura + labels) si cal evitar accions d’usuari.

### D7: Gràfics d’informes

**Decisió**: Triar una llibreria ja present al `package.json` del frontend; si no n’hi ha cap, afegir **una** (p. ex. Chart.js) amb bundle mínim — concretar a `tasks.md` en la fase d’implementació.

### D8: Eliminació d’usuaris

**Decisió**: **Hard delete** via `DELETE /api/admin/users/{id}` si les FK ho permeten; no es pot eliminar l’últim `admin` ni l’usuari autenticat que fa la petició (vegeu `AdminUsersController::destroy`).

## Risks / Trade-offs

| Risc | Mitigació |
|------|-----------|
| Sobrecàrrega del socket amb molts admins | Namespace o subscripció per `eventId`; throttling d’updates |
| Desincronització mapa vs BD | Endpoint de **resync** periòdic o esdeveniment `seatmap:sync` ja existent |
| Volum de logs Discovery | Limitar mida de `errors[]` emmagatzemada per al dashboard |
| Seguretat endpoints admin | Mantenir `jwt.auth` + `role:admin`; no exposar internals sense `internal.socket` on correspongui |

## Migration Plan

1. **Contracte API**: ampliar OpenAPI i implementar **GET** dashboard ampliat + endpoints d’informes / usuaris.
2. **Backend**: serveis de domini prims (`AdminDashboardService`, etc.) sota `app/Services/`.
3. **Socket**: presència + assegurar que el mapa admin rep els mateixos esdeveniments que el client usuari per `eventId`.
4. **Frontend**: pàgines admin en ordre **Dashboard → Esdeveniments → Monitor → Usuaris → Informes** (o paral·lel per equip).
5. **Proves**: Feature tests API admin; smoke manual socket; opcional E2E Cypress per rutes admin.

## Open Questions

1. **Definició exacta** de “usuari actiu” (només socket vs també REST amb activitat recent).
2. **Camp `pending_payment`**: nom exacte a la taula `orders` (o equivalent) i si cal nou índex.
3. **Aforament total** per esdeveniment: font única (taula esdeveniment vs recompte de seients al mapa).
4. **Històric de sync**: només en memòria / última execució vs taula `sync_runs`.
5. **Validador**: confirmació de producte si es **oculten** enllaços a `/validator` des del layout general (fora d’aquest change si no es demana explícitament).
