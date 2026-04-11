/**
 * T025 — confirmació de pagament crea tickets (venuda) + camps a la resposta.
 * Requereix PHP al PATH i API Laravel (p. ex. localhost:8000).
 * El task `seedCypressOrderFlow` executa `php artisan db:seed --class=CypressOrderFlowSeeder`.
 */
describe('API — order confirm → tickets (T025)', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('hold → order → confirm-payment retorna 2 tickets venuda', { defaultCommandTimeout: 60000 }, () => {
    cy.task('seedCypressOrderFlow').then((demo) => {
      const eventId = demo.eventId;

      cy.request('GET', `${api}/api/events/${eventId}/seatmap`).then((sm) => {
        expect(sm.status).to.eq(200);
        const seats = sm.body.seats;
        expect(seats.length).to.be.at.least(2);
        const seatIds = [seats[0].id, seats[1].id];
        const sess = `cypress-t025-${Date.now()}`;
        const username = `cy_t025_${Date.now()}`;

        cy.request('POST', `${api}/api/auth/register`, {
          name: 'Cypress T025',
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
              body: {
                hold_id: holdId,
                anonymous_session_id: sess,
              },
              headers: { Authorization: `Bearer ${token}` },
            }).then((ord) => {
              expect(ord.status).to.eq(201);

              cy.request({
                method: 'POST',
                url: `${api}/api/orders/${ord.body.order_id}/confirm-payment`,
                headers: { Authorization: `Bearer ${token}` },
              }).then((conf) => {
                expect(conf.status).to.eq(200);
                expect(conf.body.state).to.eq('paid');
                expect(conf.body.tickets).to.be.an('array').with.length(2);
                expect(conf.body.tickets[0].status).to.eq('venuda');
                expect(conf.body.tickets[0].public_uuid).to.be.a('string').and.not.be.empty;
                expect(conf.body.tickets[0].jwt_expires_at).to.be.a('string');
              });
            });
          });
        });
      });
    });
  });
});
