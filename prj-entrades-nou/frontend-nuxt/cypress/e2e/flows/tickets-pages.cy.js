/**
 * US2: E2E tickets -> QR (T029 + ampliació)
 */
describe('Entrades (US2)', () => {
  const TICKET_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'
  const USED_TICKET_ID = 'b1ffccaa-0c1a-5ag9-ca77-7cc0ce491b22'

  function seedAuth(win) {
    const token = 'cypress-fake-jwt-token'
    // Cal coincidir amb middleware (cookie `auth_token`); localStorage sol no és vàlid.
    win.document.cookie = `auth_token=${encodeURIComponent(token)}; path=/`
    win.localStorage.setItem('speckit_auth_token', token)
    win.localStorage.setItem('speckit_auth_user', JSON.stringify({ id: 1, name: 'Test', email: 'test@example.com' }))
  }

  describe('Llista de tickets', () => {
    it('mostra tickets del usuari', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: {
          tickets: [
            {
              id: TICKET_ID,
              public_uuid: 'pub-uuid-1',
              status: 'venuda',
              used_at: null,
              event: { id: 1, name: 'Concert Test', starts_at: '2026-12-01T20:00:00Z' },
              seat: { id: 1, key: 'A-1' },
            },
          ],
        },
      }).as('tickets')

      cy.visit('/tickets', { onBeforeLoad: seedAuth })
      cy.wait('@tickets')
      cy.contains('Concert Test')
      cy.contains('A-1')
    })

    it('mostra missatge quan no hi ha tickets', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: { tickets: [] },
      })

      cy.visit('/tickets', { onBeforeLoad: seedAuth })
      cy.contains('No tens entrades')
    })
  })

  describe('Detall de ticket', () => {
    it('mostra QR quan ticket vàlid', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: {
          tickets: [
            {
              id: TICKET_ID,
              status: 'venuda',
              used_at: null,
              event: { name: 'Concert Test' },
              seat: { key: 'A-1' },
            },
          ],
        },
      })

      cy.intercept('GET', `**/api/tickets/${TICKET_ID}/qr`, {
        statusCode: 200,
        headers: { 'content-type': 'image/svg+xml' },
        body: '<svg><rect width="80" height="80"/></svg>',
      }).as('qr')

      cy.visit(`/tickets/${TICKET_ID}`, { onBeforeLoad: seedAuth })
      cy.wait('@qr')
      cy.get('svg').should('exist')
    })

    it('mostra overlay quan ticket utilitzat', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: {
          tickets: [
            {
              id: USED_TICKET_ID,
              status: 'utilitzada',
              used_at: '2026-01-15T18:00:00Z',
              event: { name: 'Concert Test' },
              seat: { key: 'A-2' },
            },
          ],
        },
      })

      cy.visit(`/tickets/${USED_TICKET_ID}`, { onBeforeLoad: seedAuth })
      cy.contains('Utilitzada')
      cy.get('[data-testid="used-overlay"]').should('exist')
      cy.contains('X').should('exist')
    })

    it('mostra error quan QR no disponible', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: {
          tickets: [
            {
              id: TICKET_ID,
              status: 'venuda',
              event: { name: 'Concert Test' },
              seat: { key: 'A-1' },
            },
          ],
        },
      })

      cy.intercept('GET', `**/api/tickets/${TICKET_ID}/qr`, {
        statusCode: 500,
        body: { error: 'QR generation failed' },
      }).as('qrFail')

      cy.visit(`/tickets/${TICKET_ID}`, { onBeforeLoad: seedAuth })
      cy.wait('@qrFail')
      cy.contains('Error').or('error')
    })
  })

  describe('Navegació', () => {
    it('navega de llista a detall', () => {
      cy.intercept('GET', '**/api/tickets', {
        statusCode: 200,
        body: {
          tickets: [
            {
              id: TICKET_ID,
              status: 'venuda',
              event: { name: 'Concert Test', starts_at: '2026-12-01T20:00:00Z' },
              seat: { key: 'A-1' },
            },
          ],
        },
      })

      cy.intercept('GET', `**/api/tickets/${TICKET_ID}/qr`, {
        statusCode: 200,
        headers: { 'content-type': 'image/svg+xml' },
        body: '<svg><rect width="80" height="80"/></svg>',
      })

      cy.visit('/tickets', { onBeforeLoad: seedAuth })
      cy.contains('Concert Test').click()
      cy.url().should('include', `/tickets/${TICKET_ID}`)
    })
  })
})