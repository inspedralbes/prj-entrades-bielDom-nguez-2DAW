## Context

`tickets-pages.cy.js` existeix. `stores/tickets.js` existeix. Pàgines: tickets/index.vue, tickets/[ticketId].vue.

## Goals / Non-Goals

**Goals:**
- Tests llista → detall → QR visible
- Tests estat utilitzat (overlay)
- Tests error QR

**Non-Goals:**
- No modificar codi de producció

## Decisions

1. **Test strategy**: Cypress intercept per API mocks