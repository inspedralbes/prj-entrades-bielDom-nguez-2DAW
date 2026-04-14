# Tasques — `admin-analytics-page`

**Especificacions**: `specs/admin-analytics/spec.md`, `specs/admin-platform-suite/spec.md`  
**Disseny**: `design.md`

Ordre suggerit: servei Laravel + controlador + rutes → proves feature API → Nuxt `analytics.vue` + layout + redirect → neteja endpoints vells (si cal) → OpenAPI.

## 1. Backend — servei i agregacions

- [x] 1.1 Crear `App\Services\Admin\AdminAnalyticsService` amb mètodes per resum total, per esdeveniment i ocupació per categoria; reutilitzar semàntica `Order::STATE_PAID` i rang sobre `updated_at` com `AdminReportsController`; bucles `foreach` (sense `map`/`filter` a PHP segons regles del repo).
- [x] 1.2 Calcular `days_in_period` com a dies naturals inclusius entre `date_from` i `date_to`; `avg_daily_revenue_eur` per esdeveniment = ingressos / max(1, days_in_period).
- [x] 1.3 Ocupació per categoria: agrupar per `Event.category`, capacitat = recompte `Seat`, venuts = `Ticket` amb estat venut i comanda pagada; percentatge = sold/capacity quan capacity > 0.

## 2. Backend — API i rutes

- [x] 2.1 Crear `AdminAnalyticsController` (o mètodes prims) amb `GET .../analytics/summary`, `.../analytics/events`, `.../analytics/categories/occupancy` amb validació `date_from`/`date_to`, límit de rang (p. ex. 93 dies) i respostes JSON alineades amb `design.md`.
- [x] 2.2 Registrar rutes dins del grup `jwt.auth` + `role:admin` + prefix `admin` a `routes/api.php`.
- [x] 2.3 Tests feature: 403 sense rol admin; rang invàlid 422; resum i llistat amb dades de fàbrica mínimes.

## 3. Documentació d’API

- [x] 3.1 Afegir o actualitzar paths al fitxer OpenAPI del projecte (p. ex. `specs/001-seat-map-entry-validation/contracts/openapi.yaml` o delta que faci servir el repo) per als tres endpoints d’analítiques.

## 4. Frontend Nuxt

- [x] 4.1 Crear `pages/admin/analytics.vue` amb títol **Analítiques**, presets 7/30 dies i selectors personalitzats, crides als tres endpoints i estats de càrrega/error per secció.
- [x] 4.2 Mostrar total guanyat, taula o llista d’esdeveniments (ingressos + mitjana diària), secció categories amb toggle barres / llista i percentatges accessibles (`aria` on calgui).
- [x] 4.3 Actualitzar `layouts/admin.vue`: enllaç **Analítiques** cap a `/admin/analytics`.
- [x] 4.4 Implementar redirecció `/admin/reports` → `/admin/analytics` (`middleware` o pàgina minimal `reports.vue` que faci `navigateTo`, segons patró Nuxt del projecte).
- [x] 4.5 Eliminar o buidar el contingut antic de `reports.vue` si ja no és necessari (sense trencar la redirecció).

## 5. Neteja i sincronització d’especificacions

- [x] 5.1 Cercar al repositori referències a `GET /api/admin/reports/sales` i `occupancy`; si només les usava la vista antiga, eliminar rutes/controlador només quan la UI nova estigui completa i les proves passin.
- [x] 5.2 Després d’implementar, executar `/opsx:verify` o revisió manual contra `specs/admin-analytics/spec.md`.
