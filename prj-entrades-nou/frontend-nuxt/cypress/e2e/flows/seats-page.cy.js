/**
 * US1: E2E complet mapa de seients + Pinia (T023-T024)
 */
describe('Mapa de seients (US1)', () => {
  const eventId = '1'

  beforeEach(() => {
    cy.intercept('GET', `**/api/events/${eventId}/seatmap`, {
      statusCode: 200,
      body: {
        snapshotImageUrl: 'https://example.com/seatmap.png',
        zones: [
          { id: '10', label: 'Platea' },
          { id: '20', label: 'VIP' },
        ],
        seats: [
          { id: 101, zoneId: '10', key: 'A-1', status: 'available', price: 50 },
          { id: 102, zoneId: '10', key: 'A-2', status: 'available', price: 50 },
          { id: 103, zoneId: '10', key: 'A-3', status: 'reserved', price: 50 },
          { id: 104, zoneId: '10', key: 'A-4', status: 'sold', price: 50 },
          { id: 105, zoneId: '20', key: 'VIP-1', status: 'available', price: 100 },
          { id: 106, zoneId: '20', key: 'VIP-2', status: 'available', price: 100 },
        ],
      },
    }).as('seatmap')
  })

  describe('Selecció de seients', () => {
    it('mostra seients carregats del seatmap', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('Platea')
      cy.contains('VIP')
      cy.contains('A-1')
    })

    it('permet seleccionar un seient', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('Seleccionats: 1 / 6')
    })

    it('limita a màxim 6 seients', () => {
      cy.intercept('POST', `**/api/events/${eventId}/holds`, {
        statusCode: 201,
        body: {
          hold_id: 'hold-123',
          expires_at: new Date(Date.now() + 240000).toISOString(),
          seat_ids: [101, 102, 103, 104, 105, 106],
        },
      }).as('createHold')

      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('A-2').click()
      cy.contains('A-3').click()
      cy.contains('A-4').click()
      cy.contains('VIP-1').click()
      cy.contains('VIP-2').click()
      cy.contains('Seleccionats: 6 / 6')
    })

    it('permet deseleccionar un seient', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('Seleccionats: 1 / 6')
      cy.contains('A-1').click()
      cy.contains('Seleccionats: 0 / 6')
    })
  })

  describe('Colors destat', () => {
    it('mostra color verd per available', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.get('[data-seat-id="101"]')
        .should('have.class', 'available')
        .and('not.have.class', 'reserved')
        .and('not.have.class', 'sold')
    })

    it('mostra color groc per reserved', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.get('[data-seat-id="103"]')
        .should('have.class', 'reserved')
    })

    it('mostra color gris per sold', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.get('[data-seat-id="104"]')
        .should('have.class', 'sold')
    })
  })

  describe('Hold i temporitzador', () => {
    it('crea hold quan usuari clicka Reservar', () => {
      cy.intercept('POST', `**/api/events/${eventId}/holds`, {
        statusCode: 201,
        body: {
          hold_id: 'hold-abc',
          expires_at: new Date(Date.now() + 240000).toISOString(),
          seat_ids: [101],
        },
      }).as('createHold')

      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('Reservar').click()
      cy.wait('@createHold')
      cy.contains(' hold-abc')
    })

    it('mostra temporitzador (countdown)', () => {
      cy.intercept('POST', `**/api/events/${eventId}/holds`, {
        statusCode: 201,
        body: {
          hold_id: 'hold-123',
          expires_at: new Date(Date.now() + 180000).toISOString(),
          seat_ids: [101],
        },
      })

      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('Reservar').click()
      cy.contains(/3:/)
    })
  })

  describe('Contenció', () => {
    it('mostra missatge quan seient ja ocupat', () => {
      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')

      cy.window().then(win => {
        win.$nuxt?.$emit?.('seat:contention', {
          eventId,
          seatId: 101,
          message: 'Seient no disponible',
        })
      })

      cy.contains('Seient no disponible')
    })
  })

  describe('Pinia store coherència', () => {
    it('actualitza store en crear hold', () => {
      cy.intercept('POST', `**/api/events/${eventId}/holds`, {
        statusCode: 201,
        body: {
          hold_id: 'hold-test',
          expires_at: new Date(Date.now() + 240000).toISOString(),
          seat_ids: [101],
        },
      })

      cy.visit(`/events/${eventId}/seats`)
      cy.wait('@seatmap')
      cy.contains('A-1').click()
      cy.contains('Reservar').click()

      cy.window().then(win => {
        const store = win.$nuxt.$pinia.state.value.hold
        expect(store.holdId).to.eq('hold-test')
      })
    })
  })
})