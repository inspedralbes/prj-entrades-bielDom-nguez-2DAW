## Why

El deute P-T029-CYPRESS de `docs/tasksPendents.md` indica que `tickets-pages.cy.js` existeix però cal revisar cobertura: ticket utilitzat, error QR, overlay.

## What Changes

- Ampliar tests Cypress per cobrir llista tickets → detall
- Afegir test per QR visible
- Afegir test per estat "utilitzat" (overlay X)
- Afegir test per error QR
- Verificar coherència amb stores/tickets.js

## Capabilities

### New Capabilities
- `tickets-e2e-coverage`: Cobertura E2E completa per US2 (tickets → QR)

### Modified Capabilities
- (none)

## Impact

- `frontend-nuxt/cypress/e2e/flows/tickets-pages.cy.js` - Tests augmentats