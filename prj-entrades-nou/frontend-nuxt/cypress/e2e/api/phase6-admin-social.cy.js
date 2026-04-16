/**
 * Fase 6 (T035/T036): smoke API — admin 403, social invite/friends, transferència amb amistat.
 * Requereix API Laravel + `php artisan db:seed --class=CypressOrderFlowSeeder` via task (mateix patró que T025).
 */
describe('API — fase 6 admin + social (T035/T036)', () => {
  const api = Cypress.env('apiUrl') || 'http://localhost:8000';

  it('GET /api/admin/summary retorna 403 sense rol admin', () => {
    const username = `cy_adm403_${Date.now()}`;
    cy.request('POST', `${api}/api/auth/register`, {
      username,
      email: `${username}@example.com`,
      password: 'password12345',
      password_confirmation: 'password12345',
    }).then((reg) => {
      expect(reg.status).to.eq(201);
      cy.request({
        method: 'GET',
        url: `${api}/api/admin/summary`,
        headers: { Authorization: `Bearer ${reg.body.token}` },
        failOnStatusCode: false,
      }).then((r) => {
        expect(r.status).to.eq(403);
      });
    });
  });

  it('invitació → acceptar → llista amics', () => {
    const u1 = `cy_soc_a_${Date.now()}`;
    const u2 = `cy_soc_b_${Date.now()}`;

    cy.request('POST', `${api}/api/auth/register`, {
      username: u1,
      email: `${u1}@example.com`,
      password: 'password12345',
      password_confirmation: 'password12345',
    }).then((a) => {
      expect(a.status).to.eq(201);
      const tokenA = a.body.token;

      cy.request('POST', `${api}/api/auth/register`, {
        username: u2,
        email: `${u2}@example.com`,
        password: 'password12345',
        password_confirmation: 'password12345',
      }).then((b) => {
        expect(b.status).to.eq(201);
        const idB = b.body.user.id;
        const tokenB = b.body.token;

        cy.request({
          method: 'POST',
          url: `${api}/api/social/friend-invites`,
          body: { receiver_id: idB },
          headers: { Authorization: `Bearer ${tokenA}` },
        }).then((inv) => {
          expect(inv.status).to.eq(201);
          const inviteId = inv.body.id;

          cy.request({
            method: 'PATCH',
            url: `${api}/api/social/friend-invites/${inviteId}`,
            body: { action: 'accept' },
            headers: { Authorization: `Bearer ${tokenB}` },
          }).then((patch) => {
            expect(patch.status).to.eq(200);
            expect(patch.body.status).to.eq('accepted');

            cy.request({
              method: 'GET',
              url: `${api}/api/social/friends`,
              headers: { Authorization: `Bearer ${tokenA}` },
            }).then((friends) => {
              expect(friends.status).to.eq(200);
              expect(friends.body.friends).to.be.an('array').with.length(1);
              expect(friends.body.friends[0].id).to.eq(idB);
            });
          });
        });
      });
    });
  });

  it(
    'transferència d’entrada: 403 sense amistat, 200 després d’acceptar invitació',
    { defaultCommandTimeout: 60000 },
    () => {
      cy.task('seedCypressOrderFlow').then((demo) => {
        const eventId = demo.eventId;

        cy.request('GET', `${api}/api/events/${eventId}/seatmap`).then((sm) => {
          expect(sm.status).to.eq(200);
          const seatId = sm.body.seats[0].id;
          const sess = `cypress-t036-${Date.now()}`;
          const owner = `cy_tr_own_${Date.now()}`;
          const dest = `cy_tr_dst_${Date.now()}`;

          cy.request('POST', `${api}/api/auth/register`, {
            username: owner,
            email: `${owner}@example.com`,
            password: 'password12345',
            password_confirmation: 'password12345',
          }).then((regO) => {
            expect(regO.status).to.eq(201);
            const tokenO = regO.body.token;

            cy.request('POST', `${api}/api/auth/register`, {
              username: dest,
              email: `${dest}@example.com`,
              password: 'password12345',
              password_confirmation: 'password12345',
            }).then((regD) => {
              expect(regD.status).to.eq(201);
              const realDestId = regD.body.user.id;
              const tokenD = regD.body.token;

              cy.request('POST', `${api}/api/events/${eventId}/holds`, {
                seat_ids: [seatId],
                anonymous_session_id: sess,
              }).then((hold) => {
                expect(hold.status).to.eq(201);

                cy.request({
                  method: 'POST',
                  url: `${api}/api/orders`,
                  body: {
                    hold_id: hold.body.hold_id,
                    anonymous_session_id: sess,
                  },
                  headers: { Authorization: `Bearer ${tokenO}` },
                }).then((ord) => {
                  expect(ord.status).to.eq(201);

                  cy.request({
                    method: 'POST',
                    url: `${api}/api/orders/${ord.body.order_id}/confirm-payment`,
                    headers: { Authorization: `Bearer ${tokenO}` },
                  }).then((conf) => {
                    expect(conf.status).to.eq(200);
                    const ticketId = conf.body.tickets[0].id;

                    cy.request({
                      method: 'POST',
                      url: `${api}/api/tickets/${ticketId}/transfer`,
                      body: { to_user_id: realDestId },
                      headers: { Authorization: `Bearer ${tokenO}` },
                      failOnStatusCode: false,
                    }).then((denied) => {
                      expect(denied.status).to.eq(403);

                      cy.request({
                        method: 'POST',
                        url: `${api}/api/social/friend-invites`,
                        body: { receiver_id: realDestId },
                        headers: { Authorization: `Bearer ${tokenO}` },
                      }).then((inv) => {
                        expect(inv.status).to.eq(201);

                        cy.request({
                          method: 'PATCH',
                          url: `${api}/api/social/friend-invites/${inv.body.id}`,
                          body: { action: 'accept' },
                          headers: { Authorization: `Bearer ${tokenD}` },
                        }).then((acc) => {
                          expect(acc.status).to.eq(200);

                          cy.request({
                            method: 'POST',
                            url: `${api}/api/tickets/${ticketId}/transfer`,
                            body: { to_user_id: realDestId },
                            headers: { Authorization: `Bearer ${tokenO}` },
                          }).then((ok) => {
                            expect(ok.status).to.eq(200);
                            expect(String(ok.body.ticket_id)).to.eq(String(ticketId));
                          });
                        });
                      });
                    });
                  });
                });
              });
            });
          });
        });
      });
    },
  );
});
