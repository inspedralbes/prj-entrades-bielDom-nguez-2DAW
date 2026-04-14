## Context

Avui el panell admin té **`pages/admin/reports.vue`** amb títol **Informes**, que consumeix **`GET /api/admin/reports/sales`** (sèrie temporal amb filtres propis) i **`GET /api/admin/reports/occupancy?event_id=`** (donut per **un** esdeveniment). La lògica d’ingressos reutilitza `Order` amb `state = paid` i rang sobre `updated_at` (vegeu `AdminReportsController::salesSeries`). L’ocupació per esdeveniment compta `Seat` com a capacitat i `Ticket` venuts via comandes pagades.

El canvi substitueix aquesta pantalla per **Analítiques** amb **un sol filtre de període global** (7d / 30d / personalitzat), mètriques agregades i ocupació **per categoria** (`Event.category`), sense dependre del gràfic de línies ni del donut per esdeveniment seleccionat.

**Restriccions del monorepo**: Laravel 11, Nuxt 3 en JS sense TypeScript; sense `.map()` / `.filter()` / ternaris als buits coberts per les regles d’`Agents/`; comentaris al codi en català on aportin valor.

## Goals / Non-Goals

**Goals:**

- Una pàgina **Analítiques** que reemplaci **Informes** (mateix punt del menú lateral: enllaç i text actualitzats).
- Filtre global **`date_from` / `date_to`** (incloses) amb presets **7 dies**, **30 dies** i mode **personalitzat** (dos selectors de data o equivalent accessible).
- **Total guanyat** al període (mateixa semàntica de diners que els informes actuals: comandes pagades dins del rang).
- Llista o taula de **rendiment per esdeveniment**: ingressos al període; **venda mitjana per dia** = ingressos totals ÷ **nombre de dies naturals** del període `[from, to]` (inclosos), arrodonit coherentment (evitar divisió per zero: mínim 1 dia).
- **Ocupació per categoria**: per a cada valor de `Event.category` (incloent buit com a “sense categoria” si cal), percentatge **venuts / capacitat total** agregada dels esdeveniments d’aquesta categoria (pes global, no mitjana simple d’esdeveniments). UI amb **barres de progrés** i commutador a **vista llista** (files apilades amb percentatge llegible).
- Implementació backend centralitzada en un **servei** dedicat (patró `Admin*Service`) i respostes JSON estables per al client.

**Non-Goals:**

- Temps real amb Socket.IO per a aquesta pàgina (opcional en futurs increments).
- Gràfics via GraphQL (el dashboard pot usar GraphQL per altres vistes; aquí es prioritza **REST** coherent amb `AdminReportsController`).
- Export CSV/PDF ni informes programats.

## Decisions

### 1. Ruta i fitxer Nuxt

| Opció | Pros | Contras |
|-------|------|---------|
| **A)** Nova ruta `pages/admin/analytics.vue` → `/admin/analytics` | Nom explícit, clar per SEO/bookmarks | Cal actualitzar enllaç i possibles redirects |
| **B)** Mantenir `reports.vue` i `/admin/reports` | Menys canvis d’URL | El path no reflecteix “analítiques” |

**Decisió**: **A)** — crear **`/admin/analytics`** com a ruta canònica. Eliminar o substituir el contingut de `reports.vue`: **reemplaçament total** — es pot **esborrar** `reports.vue` i afegir **`middleware` o regla Nuxt redirect** de `/admin/reports` → `/admin/analytics` (301 o `navigateTo` segons el que el projecte ja faci servir per compatibilitat d’enllaços antics). El layout **`admin.vue`** ha d’apuntar el menú a **Analítiques** → `/admin/analytics`.

### 2. Contracte API REST

Nous endpoints sota el grup `jwt.auth` + `role:admin` (prefix `admin`), per exemple:

| Endpoint | Propòsit |
|----------|-----------|
| `GET /api/admin/analytics/summary` | `date_from`, `date_to` (obligatoris, format data) → `{ total_revenue_eur, period: { from, to } }` |
| `GET /api/admin/analytics/events` | Mateix rang → llista `{ event_id, name, revenue_eur, avg_daily_revenue_eur }` ordenada per ingressos desc (o per nom; concretar a la implementació amb criteri únic) |
| `GET /api/admin/analytics/categories/occupancy` | Mateix rang → `{ categories: [ { category_key, label, capacity, sold, occupancy_percent } ] }` |

Validació: rang màxim coherent amb l’existent (p. ex. **93 dies** com `salesSeries`) per evitar abusos; mateix fus horari que la resta d’admin (**Europe/Madrid** via `config` o `Carbon` explícit al servei).

**Alternativa considerada**: un sol payload combinat — es descarta per mantenir respostes petites i permetre carregar seccions amb errors aïllats.

### 3. Font de veritat i coherència amb vendes

- **Ingressos**: mateixa base que `salesSeries`: `Order::STATE_PAID`, filtre temporal sobre el camp que ja s’usa avui (**`updated_at`** dins del rang `[startOfDay(from), endOfDay(to)]**). Documentar-ho al servei perquè un futur canvi a `paid_at` sigui un sol punt.
- **Esdeveniments inclosos**: només comandes amb `event_id` vàlid; es poden excloure esdeveniments `hidden_at` no nuls si el producte vol analítica “només públics” — **decisió per defecte**: incloure **tots** els esdeveniments amb venda al període (si cal filtre `hidden`, queda com a pregunta oberta).

### 4. Venda mitjana per dia (per esdeveniment)

Per cada esdeveniment: `revenue_eur` = suma de `total_amount` de comandes pagades al període per aquell `event_id`.  
`avg_daily_revenue_eur` = `revenue_eur / days_in_period`, amb `days_in_period` = diferència en dies naturals entre `from` i `to` + 1 (inclosos).

### 5. Ocupació per categoria

- Agrupar esdeveniments per `category` (string; normalitzar `null` a etiqueta fixa p. ex. `—` o `sense_categoria` al payload però mostrar text llegible a la UI).
- Per grup: `capacity` = recompte de `Seat` per tots els `event_id` del grup; `sold` = recompte de `Ticket` venuts (mateixa regla que `occupancy` actual: estat venut + comanda pagada de l’esdeveniment).
- `occupancy_percent` = si `capacity > 0` llavors `round(100 * sold / capacity, 2)` sinó `0` o excloure la categoria del llistat.

### 6. Capa PHP

- Nou **`App\Services\Admin\AdminAnalyticsService`** amb mètodes clars (`buildSummary`, `buildPerEvent`, `buildCategoryOccupancy`) cridats des d’un **`AdminAnalyticsController`** prim o mètodes nous ben delimitats; **no** duplicar consultes massives sense índexs — reutilitzar patrons de `AdminReportsController` (consultes amb `whereBetween`, agrupacions en PHP amb bucles `foreach` segons convenció del projecte).

### 7. Frontend

- **Estat del període**: `ref` / `computed` amb presets que assignin `from`/`to` (7 i 30 dies fins a **avui** com a dia final, o fins ahir segons criteri únic documentat).
- **Càrrega**: `$fetch` o helper existent (`getJson`) en paral·lel o seqüencial; mostrar estats de càrrega i error per secció.
- **Vista categories**: toggle **Barres** / **Llista** — mateix array de dades; barres amb `progress` semàntic o `div` amb amplada %; llista amb files `flex` i percentatge al costat.
- **Accessibilitat**: etiquetes i `aria` als indicadors de percentatge.

### 8. Documentació OpenAPI

Actualitzar o afegir paths al contracte OpenAPI del repositori si la resta d’endpoints admin ja estan documentats (alineació amb `proposal.md`).

## Risks / Trade-offs

- **[Risc] `updated_at` com a data de venda** → Pot no coincidir amb el moment real de pagament; **mitigació**: documentar; tasques futures per `paid_at` si existeix al model `Order`.
- **[Risc] Rendiment amb molts esdeveniments/seients** → **mitigació**: límit de rang de dates; revisar índexs sobre `orders(state, updated_at, event_id)` si les consultes són lentes.
- **[Risc] Categoria inconsistent** (typos) → Les barres mostraran grups separats; **mitigació** opcional: normalització `trim`/`lowercase` al servei (decidir a `tasks` si cal).

## Migration Plan

1. Implementar servei + rutes + proves feature mínimes al backend.
2. Nova pàgina `analytics.vue`, actualitzar `layouts/admin.vue`, redirect des de `/admin/reports` si es manté compatibilitat.
3. Eliminar codi mort de la vista antiga si ja no s’usa; valorar **deprecació** dels endpoints `reports/sales` i `reports/occupancy` només si cap altre client els crida (cercar al repo abans d’esborrar).

## Open Questions

- Incloure esdeveniments **amagats** (`hidden_at`) a les agregacions o només catàleg visible.
- Etiqueta mostrada per `category` buida: text en català fix (“Sense categoria”).
- Ordre del llistat per esdeveniment: per ingressos (desc) per defecte.
