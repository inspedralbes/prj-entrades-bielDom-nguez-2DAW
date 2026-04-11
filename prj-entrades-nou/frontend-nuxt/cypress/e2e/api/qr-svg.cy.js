/**
 * T026 — POST /internal/qr-svg retorna image/svg+xml (socket-server).
 */
describe('API — socket internal QR SVG (T026)', () => {
  const socket = Cypress.env('socketUrl') || 'http://localhost:3001';
  const secret = Cypress.env('socketInternalSecret') || '';

  it('POST /internal/qr-svg retorna SVG', () => {
    const headers = { 'Content-Type': 'application/json' };
    if (secret) {
      headers['X-Internal-Secret'] = secret;
    }

    cy.request({
      method: 'POST',
      url: `${socket}/internal/qr-svg`,
      headers,
      body: {
        text: 'test-public-uuid-or-jwt-payload',
        width: 128,
      },
    }).then((res) => {
      expect(res.status).to.eq(200);
      expect(res.headers['content-type']).to.match(/image\/svg\+xml/i);
      expect(res.body).to.contain('<svg');
    });
  });
});
