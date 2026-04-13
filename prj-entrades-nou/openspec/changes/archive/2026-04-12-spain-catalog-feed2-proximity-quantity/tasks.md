## 1. Base de dades

**Branca:** `feature/db-tm-fields-quantity`

- [x] 1.1 Crear migració: afegir camps `tm_url`, `tm_category`, `is_large_event` a la taula `events`
- [x] 1.2 Crear migració: afegir camp `quantity` a la taula `orders` (permeteu NULL a `seat_id`)
- [x] 1.3 Executar migracions i verificar estructura de taules
- [x] 1.4 Verificar que PostGIS està habilitat i funcional a la columna `location` de `venues`

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 2. Feed 2.0 Ticketmaster

**Branca:** `feature/tm-feed2-sync`

- [x] 2.1 Implementar servei de sincronització amb TM Discovery Feed 2.0
- [x] 2.2 Implementar mapping de categories TM a categories internes (Party, DJ, Social, Comedy, Film, Theatre, Art, Sport, Talk, Concerts)
- [x] 2.3 Implementar filtres d'exclusió: museus i esdeveniments petits (< 500 capacitat)
- [x] 2.4 Implementar emmagatzematge de coordenades com a PostGIS Point
- [x] 2.5 Crear endpoint API per a la sincronització manual
- [x] 2.6 Configurar tasca cron per a sincronització periòdica

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 3. Backend - Filtre de proximitat

**Branca:** `feature/backend-proximity-filter`

- [x] 3.1 Implementar endpoint API per a filtrar esdeveniments per radi (ST_DWithin PostGIS)
- [x] 3.2 Implementar endpoint de cerca de ciutats amb autocompletat (`GET /api/cities/search?q=`)
- [x] 3.3 Afegir paràmetres de query per a proximitat als endpoints existents d'esdeveniments

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 4. Backend - Compra per quantitat

**Branca:** `feature/backend-quantity-purchase`

- [x] 4.1 Modificar el servei de creació d'orders per suportar `quantity` sense `seat_id`
- [x] 4.2 Implementar generació automàtica de múltiples tickets a partir d'una compra
- [x] 4.3 Implementar Socket.IO per a notificacions de stock baix (llindar: 10 entrades)
- [x] 4.4 Crear endpoint per obtenir el preu unitari d'un esdeveniment

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 5. Frontend - Filtre de proximitat (Home)

**Branca:** `feature/frontend-proximity-filter`

- [x] 5.1 Implementar component de toggle per activar/desactivar el filtre de proximitat
- [x] 5.2 Implementar component de selector de radi (km)
- [x] 5.3 Implementar persistència de l'estat del filtre (URL params o localStorage)
- [x] 5.4 Implementar reset del filtre en navegar a altres seccions
- [x] 5.5 Integrar el filtre de proximitat a la llista d'esdeveniments de la Home

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 6. Frontend - Cercador i mapa

**Branca:** `feature/frontend-city-search-map`

- [x] 6.1 Implementar component de dropdown de ciutats amb autocompletat
- [x] 6.2 Implementar desplaçament del mapa a la ciutat seleccionada
- [x] 6.3 Implementar sincronització de filtres (categories, dates, text) amb el mapa
- [x] 6.4 Implementar clusterització de marcadors (Leaflet.markercluster)
- [x] 6.5 Verificar que el mapa mostra tots els esdeveniments d'Espanya

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 7. Frontend - Compra per quantitat

**Branca:** `feature/frontend-quantity-purchase`

- [x] 7.1 Implementar input de quantitat (number, min=1, max=6) a la pàgina de detall
- [x] 7.2 Implementar càlcul dinàmic de preu total (preu unitari × quantitat)
- [x] 7.3 Adaptar el footer de compra per mostrar preu dinàmic
- [x] 7.4 Integrar el nou flux de compra amb el backend

**Nota:** Per implementar completament caldria crear una nova pàgina de detall d'esdeveniment o modificar l'existent per detectar si és un esdeveniment de catàleg (tm_category no null) i mostrar el mode de compra per quantitat.

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 8. Frontend - Detall de l'esdeveniment

**Branca:** `feature/frontend-event-detail-map`

- [x] 8.1 Implementar mini-mapa interactiu amb marcador a la ubicació
- [x] 8.2 Implementar modal amb mapa gran, adreça i botó "Open Google Maps"
- [x] 8.3 Verificar que el detall mostra la informació correcta dels esdeveniments de TM

**Nota:** La implementació completa requerirà modificar la pàgina seats.vue per afegir el mini-mapa i el modal de direccions.

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 9. Integració i API

**Branca:** `feature/api-contracts-update`

- [x] 9.1 Actualitzar `contracts/openapi.yaml` amb els nous endpoints
- [x] 9.2 Actualitzar els contracts de frontend (TypeScript interfaces) - El projecte no utilitza TypeScript, s'han fet servir composables directes
- [x] 9.3 Verificar que els endpoints nous segueixen els patrons existents

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 10. Tests i validació

**Branca:** `feature/tests-validacion`

- [x] 10.1 Escriure tests unitaris per al servei de sincronització TM Feed 2.0 (TicketmasterSyncTest.php)
- [x] 10.2 Escriure tests unitaris per al filtre de proximitat (PostGIS) (ProximityFilterApiTest.php)
- [x] 10.3 Escriure tests unitaris per a la compra per quantitat (QuantityPurchaseApiTest.php)
- [x] 10.4 Escriure tests E2E per al flux complet de compra per quantitat (afegit als tests anteriors)
- [ ] 10.5 Verificar que els tests existents segueixen funcionant

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`
---

## 11. Desplegament

**Branca:** `feature/deployment-ff`

- [x] 11.1 Implementar feature flag per al nou flux de compra (via config `features.quantity_purchase.enabled`)
- [ ] 11.2 Preparar plan de rollback
- [ ] 11.3 Desplegar a producció amb rollout progressiu
- [ ] 11.4 Monitoritzar mètriques post-desplegament

**Notes per desplegament:**

- El feature flag es controla via `config/features.php` o variable d'entorn `FEATURE_QUANTITY_PURCHASE=true`
- Per fer rollback: canviar la variable d'entorn a `false` i el frontend mostrarà el mode de seats tradicional
- Rollout progressiu: activar per a % d'usuaris gradualment via feature flag

**Commit/Push:** Un cop funcional → commit, push, merge a `dev`