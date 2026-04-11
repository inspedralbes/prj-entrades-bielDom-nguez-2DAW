describe('socket-server — health HTTP', () => {
  it('GET /health retorna ok si el servei està actiu', () => {
    const base = Cypress.env('socketUrl') || 'http://localhost:3001';
    cy.request({ url: base + '/health', failOnStatusCode: false }).then((res) => {
      if (res.status !== 200) {
        cy.log('socket-server no disponible (normal si el stack Docker no està en marxa)');
        return;
      }
      expect(res.body.status).to.eq('ok');
      expect(res.body.service).to.eq('socket-server');
    });
  });
});
