describe('API — auth', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('POST /api/auth/register + GET /api/auth/me', () => {
    const username = 'cypress_' + Date.now();
    const email = username + '@example.com';

    cy.request({
      method: 'POST',
      url: api + '/api/auth/register',
      body: {
        name: 'Cypress',
        username,
        email,
        password: 'password12345',
        password_confirmation: 'password12345',
      },
    }).then((res) => {
      expect(res.status).to.eq(201);
      expect(res.body.token).to.be.a('string');

      cy.request({
        method: 'GET',
        url: api + '/api/auth/me',
        headers: {
          Authorization: 'Bearer ' + res.body.token,
        },
      }).then((me) => {
        expect(me.status).to.eq(200);
        expect(me.body.email).to.eq(email);
      });
    });
  });
});
