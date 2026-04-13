## Why

El deute P-T023-T024-CYPRESS de `docs/tasksPendents.md` indica que `seats-page.cy.js` és smoke parcial i cal ampliar cobertura E2E: selecció fins 6 seients, hold, temporitzador, colors temps real, contenció, coherència amb Pinia store.

## What Changes

- Ampliar tests Cypress per cobrir selecció múltiple (fins a 6 seients)
- Afegir test per a creació de hold (POST /api/events/{id}/holds)
- Afegir test per a temporitzador (countdown visible)
- Afegir test per a colors d'estat (available, reserved, sold)
- Afegir test per a missatge contenció (si simulable)
- Verificar coherència amb stores/hold.js (Pinia)

## Capabilities

### New Capabilities
- `seatmap-e2e-coverage`: Cobertura E2E completa per US1 (mapa de seients)

### Modified Capabilities
- (none)

## Impact

- `frontend-nuxt/cypress/e2e/flows/seats-page.cy.js` - Tests augmentats
- `frontend-nuxt/stores/hold.js` - Verificat