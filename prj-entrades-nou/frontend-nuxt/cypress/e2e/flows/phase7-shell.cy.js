/**
 * T045 — navegació 6 seccions (consumidor) sense autenticar on cal.
 */
describe('FE — shell fase 7 (T045)', () => {
  const base = Cypress.config('baseUrl') || 'http://localhost:3000';

  it('mostra enllaços principals des de la home', () => {
    cy.visit(`${base}/`);
    cy.contains('h1', 'Inici');
    cy.get('a[href="/search"]').first().should('be.visible');
    cy.get('a[href="/tickets"]').first().should('be.visible');
    cy.get('a[href="/saved"]').first().should('be.visible');
    cy.get('a[href="/social"]').first().should('be.visible');
    cy.get('a[href="/profile"]').first().should('be.visible');
  });

  it('navega a Cercar', () => {
    cy.visit(`${base}/search`);
    cy.contains('h1', 'Cercar esdeveniments');
  });
});
