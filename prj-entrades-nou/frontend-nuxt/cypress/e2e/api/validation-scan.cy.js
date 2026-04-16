/**
 * T030 — POST /api/validation/scan (rol validador).
 * Requereix API Laravel (p. ex. localhost:8000).
 */
describe('API — validation scan (T030)', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('retorna 401 sense Bearer', () => {
    cy.request({
      method: 'POST',
      url: `${api}/api/validation/scan`,
      body: { token: 'x' },
      failOnStatusCode: false,
    }).then((res) => {
      expect(res.status).to.eq(401);
    });
  });

  it('retorna 403 si l’usuari no és validador', () => {
    const u = `cy_val_blk_${Date.now()}`;
    cy.request('POST', `${api}/api/auth/register`, {
      username: u,
      email: `${u}@example.com`,
      password: 'password12345',
      password_confirmation: 'password12345',
    }).then((reg) => {
      expect(reg.status).to.eq(201);
      cy.request({
        method: 'POST',
        url: `${api}/api/validation/scan`,
        body: { token: 'not-a-jwt' },
        headers: { Authorization: `Bearer ${reg.body.token}` },
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.status).to.eq(403);
      });
    });
  });
});
