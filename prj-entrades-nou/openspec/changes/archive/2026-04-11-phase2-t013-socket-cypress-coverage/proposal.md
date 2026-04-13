## Why

El deute P-T013-SOCKET-TEST de `docs/tasksPendents.md` indica que manquen proves Cypress percoibrir el Socket.IO híbrid segons FR-014: connexió pública per `eventId`, handshake privat amb JWT, rebutjar sense JWT.

## What Changes

- Crear test d'integració per a connexió Socket pública a canal `eventId` (sense JWT)
- Crear test per validar que rooms privades rebutgen connexions sense JWT
- Crear test per validar accés privat amb JWT vàlid
- Executar via Cypress o script d'integració Node

## Capabilities

### New Capabilities
- `socket-test-coverage`: Cobertura de proves Cypress per Socket.IO FR-014

### Modified Capabilities
- (none)

## Impact

- `socket-server/` - handlers a provar
- `frontend-nuxt/cypress/` - nous specs