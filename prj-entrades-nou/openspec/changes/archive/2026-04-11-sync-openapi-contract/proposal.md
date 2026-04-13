## Why

El contracte OpenAPI actual a `specs/001-seat-map-entry-validation/contracts/openapi.yaml` no està sincronitzat amb les rutes reals del backend Laravel (`backend-api/routes/api.php`). Això genera inconsistències entre la documentació i la API real, impedint la integració correcta amb clients externs i el testing automatitzat.

## What Changes

- Revisar i actualitzar `specs/001-seat-map-entry-validation/contracts/openapi.yaml` per reflectir totes les rutes API reals
- Afegir endpoints de fase 7 que falten: feed, search/events, saved-events, user/profile, user/settings (si existeixen al codi)
- Assegurar esquemes i exemples vàlids per a totes lesrespostes
- Incloure codis d'error 4xx rellevants per a cada endpoint
- Validar que el document YAML OpenAPI 3 és vàlid

## Capabilities

### New Capabilities
- `openapi-sync`: Sincronització del contracte OpenAPI amb totes les rutes API del backend Laravel, incloent endpoints de fase 7

### Modified Capabilities
- `seat-map-entry-validation`: Actualitzar el contracte per reflectir l'estat actual de la API

## Impact

- `specs/001-seat-map-entry-validation/contracts/openapi.yaml`: Document OpenAPI modificat
- `backend-api/routes/api.php`: Rutes de referència per a la sincronització