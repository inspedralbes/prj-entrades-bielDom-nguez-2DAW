/**
 * T029 — UI entrades: llista, detall i QR SVG (intercept API, sessió localStorage).
 */
describe('Pàgines entrades (T029)', () => {
  const TID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

  function seedAuth (win) {
    win.localStorage.setItem(
      'speckit_auth_token',
      'cypress-fake-jwt-token-for-ui',
    );
    win.localStorage.setItem(
      'speckit_auth_user',
      JSON.stringify({ id: 1, name: 'Usuari Cypress', email: 'cy@example.com' }),
    );
  }

  it('llista mostra esdeveniment, seient i enllaç al detall', () => {
    cy.intercept('GET', '**/api/tickets', {
      statusCode: 200,
      body: {
        tickets: [
          {
            id: TID,
            public_uuid: 'pub-uuid-1',
            status: 'venuda',
            jwt_expires_at: null,
            used_at: null,
            order_id: 1,
            event: {
              id: 10,
              name: 'Concert interceptat',
              starts_at: '2026-12-01T20:00:00.000000Z',
            },
            seat: { id: 5, key: 'B-4' },
            created_at: '2026-01-01T12:00:00.000000Z',
          },
        ],
      },
    }).as('tickets');

    cy.visit('/tickets', { onBeforeLoad: seedAuth });
    cy.wait('@tickets');
    cy.contains('Concert interceptat');
    cy.contains('Seient B-4');
    cy.contains('Vàlida');
    cy.contains('a', 'Veure QR')
      .should('have.attr', 'href')
      .and('include', `/tickets/${TID}`);
  });

  it('detall mostra QR SVG', () => {
    cy.intercept('GET', '**/api/tickets', {
      statusCode: 200,
      body: {
        tickets: [
          {
            id: TID,
            public_uuid: 'pub-uuid-1',
            status: 'venuda',
            jwt_expires_at: null,
            used_at: null,
            order_id: 1,
            event: {
              id: 10,
              name: 'Concert interceptat',
              starts_at: null,
            },
            seat: { id: 5, key: 'B-4' },
            created_at: null,
          },
        ],
      },
    }).as('tickets');

    cy.intercept('GET', `**/api/tickets/${TID}/qr`, {
      statusCode: 200,
      headers: { 'content-type': 'image/svg+xml; charset=utf-8' },
      body: '<svg xmlns="http://www.w3.org/2000/svg"><rect width="80" height="80" fill="#000"/></svg>',
    }).as('qr');

    cy.visit(`/tickets/${TID}`, { onBeforeLoad: seedAuth });
    cy.wait('@tickets');
    cy.wait('@qr');
    cy.contains('Concert interceptat');
    cy.contains('Seient B-4');
    cy.get('.ticket-detail__qr svg').should('exist');
  });
});
