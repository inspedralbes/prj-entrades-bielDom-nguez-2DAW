describe('smoke — estructura monorepo', () => {
  it('mostra la pàgina inicial', () => {
    cy.visit('/');
    cy.contains('Entrades', { matchCase: false });
  });
});
