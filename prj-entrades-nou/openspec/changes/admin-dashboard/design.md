## Context

El resum HTTP i el socket han de convergir en un **panell** amb KPIs concrets, **dos gràfics** (30 dies) via **GraphQL**, i **auditoria** (`admin_logs`). Els **logs d’administració** es llisten **només via REST** (mai GraphQL): una **llista** compacta al dashboard i una **llista completa** en clic, amb columnes llegibles.

Les regles de negoci SHALL ser **úniques** entre REST (summary, logs), GraphQL (només gràfics) i socket.

**Restriccions**: JS sense TS; sense `.map()` / `.filter()` / ternaris segons regles del monorepo; comentaris en català on calgui.

## Goals / Non-Goals

**Goals:**

- KPIs: ingressos dia, comandes pagades el mateix dia, usuaris en línia, tiquets històrics, últims 5 logs (REST).
- Gràfics: línies + barres (30 dies) **exclusivament GraphQL**.
- Llistat de logs: **REST** `GET /api/admin/logs`, UI en format **llista** (no taula HTML rígida obligatòria, però mateixes dades per fila).

**Non-Goals:**

- Consultes GraphQL per llegir `admin_logs`.
- Gravar lectures sense efecte lateral com a log.

## Decisions

### 1. KPIs — definició operativa

| KPI | Font | Notes |
|-----|------|--------|
| Ingressos del dia | Suma `Order` `paid` (camp temporal acordat) | TZ `admin.business_timezone` |
| Comandes pagades avui | Recompte `Order` `paid` mateix dia natural | |
| Usuaris en línia | Redis presència | Snapshot socket |
| Tiquets venuts històric | Recompte total segons model | Camp al `summary` |
| Últims 5 logs | REST: camp `recent_admin_logs` al summary o `GET /api/admin/logs?per_page=5` | Mateix format d’ítem que el llistat paginat |

### 2. Taula `admin_logs`

- **Nom**: `admin_logs`.
- **Camps**: `id`, `admin_user_id` (FK `users`), `action` (codi o etiqueta curta), `entity_type`, `entity_id` (nullable), `summary` (text llegible: “què s’ha fet”), **`ip_address`** (string nullable, IPv4/IPv6 segons `Request::ip()`), `created_at` (timestamp únic per derivar **data i hora** en UTC o TZ mostrada al client).
- **Escriptura**: `AdminAuditLogService::record(..., ?string $ipAddress)` amb IP presa de la **Request** al moment de l’acció admin.
- **Rendiment**: índex `created_at`, `admin_user_id`.

### 3. API de logs — **només REST** (prohibit GraphQL per a logs)

- **`GET /api/admin/logs`**: `page`, `per_page` (per defecte **10**), només rol `admin`. Resposta `{ data: [...], meta: { ... } }`.
- Cada element de `data` SHALL incloure camps suficients per a la UI: **`admin_name`** (o `admin` amb `name`), **`created_at`** (ISO8601) o bé **`date`** + **`time`** separats si el Resource ho exposa així, **`ip_address`**, **`summary`** o **`action_label`** (text humà del que s’ha fet).
- **GraphQL**: cap query de lectura de `admin_logs`; **només** les dues sèries de gràfics del dashboard.

### 4. GraphQL — només gràfics

- Endpoint **`POST /api/graphql`**.
- Queries: `adminDashboardRevenueByDay(days: 30)`, `adminDashboardOrdersPaidByDay(days: 30)`.

### 5. Snapshot Socket.IO

- KPIs numèrics al snapshot; logs opcionalment només via GET després d’accions (evitar payload massa gran al ping).

### 6. Frontend — UX logs (llista)

- **Vista compacta (5)**: **llista** d’ítems; cada ítem mostra com a mínim: **nom de l’admin**, **dia**, **hora**, **IP**, **descripció de l’acció** (el que s’ha fet).
- **Clic** al bloc: obre vista (modal, drawer o pàgina) amb **llista completa** del mateix format (no cal GraphQL), **10 entrades per pàgina**, navegació per `GET /api/admin/logs`.
- **Prohibit** usar client GraphQL per omplir aquests llistats.

### 7. Llibreria de gràfics

- **Chart.js** (v4): `line` + `bar`.

### 8. Text llegible a la vista (no “JSON” per a l’usuari)

- **Transport**: la REST i GraphQL continuen sent JSON sobre HTTP (això és intern); l’**administrador** ha de veure **text normal**: etiquetes, frases i valors formats, **no** cadenes que semblin JSON ni objectes serialitzats a la pantalla.

- **KPIs i alertes TM**: imports amb símbol/decimals, enters amb separadors llegibles, alertes com a **frases** o vinyetes de text (el missatge ja ve en text al payload; no cal mostrar l’estructura crua).

- **Logs**: cada fila és **text pla llegible** (nom, data/hora formatades, IP, descripció de l’acció en llenguatge natural). El camp `summary` (i el que exposi el Resource com a **descripció**) SHALL ser **prosa** o frase curta, **no** un objecte JSON emmagatzemat com a string per mostrar-lo tal qual.

- **Frontend**: **prohibit** usar `JSON.stringify` (o equivalent) per pintar logs, KPIs o alertes; usar plantilles amb text, `Intl`/formatadors de data i moneda segons convingui al projecte.

## Risks / Trade-offs

- **[Risc] IP darrere proxy** → confiar en `X-Forwarded-For` si el projecte ja ho configura a `TrustProxies`.
- **[Risc] Volum de logs** → índexs; retenció opcional més endavant.

## Migration Plan

1. Migració `admin_logs` (amb `ip_address`).  
2. Servei d’auditoria + REST.  
3. UI llista + gràfics GraphQL.

## Open Questions

- Paquet GraphQL (Lighthouse vs alternativa).
- Camp exacte per data de pagament del dia (`paid_at` vs `updated_at`).
