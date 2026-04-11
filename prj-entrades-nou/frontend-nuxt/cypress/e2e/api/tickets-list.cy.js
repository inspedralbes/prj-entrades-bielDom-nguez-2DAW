/**
 * T028 — GET /api/tickets retorna historial després de compra.
 */
describe('API — GET /api/tickets (T028)', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('GET /api/tickets amb 1 entrada després de confirm', { defaultCommandTimeout: 60000 }, () => {
    cy.task('seedCypressOrderFlow').then((demo) => {
      const eventId = demo.eventId;

      cy.request('GET', `${api}/api/events/${eventId}/seatmap`).then((sm) => {
        expect(sm.status).to.eq(200);
        const seats = sm.body.seats;
        expect(seats.length).to.be.at.least(1);
        const seatIds = [seats[0].id];
        const sess = `cypress-t028-${Date.now()}`;
        const username = `cy_t028_${Date.now()}`;

        cy.request('POST', `${api}/api/auth/register`, {
          name: 'Cypress T028',
          username,
          email: `${username}@example.com`,
          password: 'password12345',
          password_confirmation: 'password12345',
        }).then((reg) => {
          expect(reg.status).to.eq(201);
          const token = reg.body.token;

          cy.request({
            method: 'GET',
            url: `${api}/api/tickets`,
            headers: { Authorization: `Bearer ${token}` },
          }).then((empty) => {
            expect(empty.status).to.eq(200);
            expect(empty.body.tickets).to.be.an('array').that.has.length(0);
          });

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

                cy.request({
                  method: 'GET',
                  url: `${api}/api/tickets`,
                  headers: { Authorization: `Bearer ${token}` },
                }).then((list) => {
                  expect(list.status).to.eq(200);
                  expect(list.body.tickets).to.be.an('array').with.length(1);
                  expect(list.body.tickets[0]).to.have.property('id');
                  expect(list.body.tickets[0]).to.have.property('status', 'venuda');
                  expect(list.body.tickets[0]).to.have.property('event');
                  expect(list.body.tickets[0]).to.have.property('seat');
                });
              });
            });
          });
        });
      });
    });
  });
});
