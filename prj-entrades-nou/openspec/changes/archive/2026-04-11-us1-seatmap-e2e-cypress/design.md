## Context

`seats-page.cy.js` existeix amb smoke bàsic (mostrar zones). `stores/hold.js` existeix.

## Goals / Non-Goals

**Goals:**
- Tests per selecció fins a 6 seients
- Tests per hold (POST holds)
- Tests per temporitzador (countdown visible)
- Tests colors estat seients
- Tests contenció (mock Socket)
- Verificació Pinia store

**Non-Goals:**
- No modificar codi de producció

## Decisions

1. **Test strategy**: Utilitzar Cypress intercept per API mocking
2. **Socket**: Mock via cy.spy() per a event listeners

## Risks / Trade-offs

- [Risk] Contingut real de Socket → Utilitzar mock/intercept