/**
 * T023: smoke UI mapa de seients amb resposta API interceptada (no cal backend real).
 */
describe('Pàgina seients (T023)', () => {
  it('mostra zones i seients del seatmap', () => {
    cy.intercept('GET', '**/api/events/*/seatmap', {
      statusCode: 200,
      body: {
        snapshotImageUrl: null,
        zones: [
          { id: '10', label: 'Platea prova' },
        ],
        seats: [
          { id: 101, zoneId: '10', key: 'Fila-A-1', status: 'available' },
          { id: 102, zoneId: '10', key: 'Fila-A-2', status: 'sold' },
        ],
      },
    }).as('seatmap');

    cy.visit('/events/1/seats');
    cy.wait('@seatmap');
    cy.contains('Platea prova');
    cy.contains('Fila-A-1');
    cy.contains('Fila-A-2');
    cy.contains('Seleccionats: 0 / 6');
  });
});
