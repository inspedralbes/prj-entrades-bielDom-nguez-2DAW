## Context

Socket.IO híbrid implementat a `socket-server/src/index.js`:
- Canal **públic** `/` - subscripció a `event:{eventId}` via query `?eventId=X` (sense JWT)
- Namespace **privat** `/private` - middleware valida JWT al `handshake.auth.token`
- Emet `server:hello` en connexió vàlida

## Goals / Non-Goals

**Goals:**
- Test connexió pública rep event sense JWT
- Test namespace privat rebutja sense JWT
- Test accés privat amb JWT vàlid

**Non-Goals:**
- No modificar socket-server.js
- No implementar UI per a Socket

## Decisions

1. **Test framework**: Cypresstest d'integració Node o Cypress amb socket.io-client
2. **Test ubicació**: `frontend-nuxt/cypress/e2e/socket-*.cy.js`
3. **JWT per a testing**: Generar token amb `jwt.sign()` a test

## Risks / Trade-offs

- [Risk] Socket server no arriba → Afegir depends_on al workflow de smoke
- [Risk] Timeout en CI → Configurar timeout adequate