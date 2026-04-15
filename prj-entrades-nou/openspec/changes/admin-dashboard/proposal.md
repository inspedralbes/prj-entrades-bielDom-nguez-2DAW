## Why

La pàgina `pages/admin/index.vue` encara mostra **sortides de depuració** que no aporten UX de panell. Els **KPIs**, **gràfics** (GraphQL) i **logs d’auditoria** (REST, llistes) han de ser clars: traçabilitat amb **nom d’admin, data, hora, IP i acció**.

## What Changes

- **Eliminar** com a UX principal: JSON brut, logs tècnics de socket en `<pre>`.

- **Presentació llegible**: tot el que veu l’admin al dashboard (targetes, alertes, llistat de logs, etiquetes de gràfics) en **text normal** formatat; **cap** bloc que mostri dades com a JSON serialitzat ni objectes “crus” per llegir la informació.

- **Informació al dashboard**: ingressos del dia, comandes pagades el mateix dia, usuaris en línia, tiquets històrics, **5 últims logs** (format **llista**).

- **Clic al bloc de logs**: vista amb **llista completa** (mateix format de fila: nom admin, dia, hora, IP, què s’ha fet), **paginació 10 en 10**, dades només via **`GET /api/admin/logs`** — **sense GraphQL** per als logs.

- **Taula `admin_logs`**: inclou **`ip_address`**; text d’acció llegible al camp `summary` (o equivalent).

- **Gràfics** (únic ús de GraphQL al dashboard per a dades): línies i barres 30 dies a **`POST /api/graphql`**.

- **Backend**: REST per summary i logs; GraphQL només resolvers de sèries; socket per KPIs.

## Capabilities

### New Capabilities

- `admin-dashboard-metrics-ui`: KPIs, 2 gràfics GraphQL, widget logs REST.
- `admin-audit-logs`: Taula `admin_logs`, escriptura amb IP, lectura REST paginada.

### Modified Capabilities

- *(Opcional en arxivar)*: delta a `admin-platform-suite`.

## Impact

- **PostgreSQL**: `admin_logs` amb `ip_address`.
- **backend-api**: `AdminAuditLogService`, `AdminLogResource` amb camps per la UI, rutes REST; GraphQL sense tipus de logs.
- **frontend-nuxt**: llistes (`<ul>`/ítems) per logs; Chart.js + client GraphQL només per gràfics.
