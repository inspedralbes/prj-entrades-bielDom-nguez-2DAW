/**
 * T027 — GET /api/tickets/{ticketId}/qr retorna image/svg+xml (requereix API + socket intern al backend).
 */
describe('API — ticket QR SVG (T027)', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('després de confirm → GET /tickets/:id/qr retorna SVG', { defaultCommandTimeout: 60000 }, () => {
    cy.task('seedCypressOrderFlow').then((demo) => {
      const eventId = demo.eventId;

      cy.request('GET', `${api}/api/events/${eventId}/seatmap`).then((sm) => {
        expect(sm.status).to.eq(200);
        const seats = sm.body.seats;
        expect(seats.length).to.be.at.least(1);
        const seatIds = [seats[0].id];
        const sess = `cypress-t027-${Date.now()}`;
        const username = `cy_t027_${Date.now()}`;

        cy.request('POST', `${api}/api/auth/register`, {
          username,
          email: `${username}@example.com`,
          password: 'password12345',
          password_confirmation: 'password12345',
        }).then((reg) => {
          expect(reg.status).to.eq(201);
          const token = reg.body.token;

          cy.request('POST', `${api}/api/events/${eventId}/holds`, {
            seat_ids: seatIds,
            anonymous_session_id: sess,
          }).then((hold) => {
            expect(hold.status).to.eq(201);
            const holdId = hold.body.hold_id;

            cy.request({
              method: 'POST',
              url: `${api}/api/orders`,
              body: { hold_id: holdId, anonymous_session_id: sess },
              headers: { Authorization: `Bearer ${token}` },
            }).then((ord) => {
              expect(ord.status).to.eq(201);

              cy.request({
                method: 'POST',
                url: `${api}/api/orders/${ord.body.order_id}/confirm-payment`,
                headers: { Authorization: `Bearer ${token}` },
              }).then((conf) => {
                expect(conf.status).to.eq(200);
                const ticketId = conf.body.tickets[0].id;

                cy.request({
                  method: 'GET',
                  url: `${api}/api/tickets/${ticketId}/qr`,
                  headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'image/svg+xml',
                  },
                }).then((qr) => {
                  expect(qr.status).to.eq(200);
                  expect(qr.headers['content-type']).to.match(/image\/svg\+xml/i);
                  expect(qr.body).to.contain('<svg');
                });
              });
            });
          });
        });
      });
    });
  });
});
