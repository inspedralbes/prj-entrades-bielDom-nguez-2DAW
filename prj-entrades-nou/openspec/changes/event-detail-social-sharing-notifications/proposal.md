## Why

La pantalla de Detall d'esdeveniment (D) i la secció Social (G/H) necessiten fluxos coherents de **guardat amb retorn post-login**, **compartició ràpida amb amics** i un **historial tipus xat només lectura** per a notificacions d'actius (esdeveniments i entrades), amb **transferència segura d'entrades** i actualització en temps real.

## What Changes

- **FR-040**: Botó Guardar → `saved_events`; usuari anònim redirigit a `/login` (o registre) amb **retorn automàtic** al detall de l'esdeveniment per completar l'acció.
- **FR-041 / FR-042**: Modal o vista de **Compartir** amb cercador d'amics (invitacions acceptades a `friend_invites`) en temps real; **copiar enllaç** amb Clipboard API.
- **FR-043–FR-045**: Secció Social com a **feed de notificacions** entrant/sortint (només lectura; sense missatges lliures); layouts diferenciats per esdeveniment (foto, nom, hora, lloc → D) i entrada (miniatura QR, descripció → F).
- **FR-046**: En enviar una entrada a un amic, el servidor fa **transferència de propietat** (invalida QR antic, nou JWT + QR SVG); l'entrada apareix al moneder del destinatari **sense acció manual** (auto-save).
- **SoT**: PostgreSQL (`friend_invites`, `ticket_transfers`, nova taula `notifications`).
- **Temps real**: Socket.IO a la room `user:{id}` per al punt de notificació instantani.

## Capabilities

### New Capabilities

- `event-detail-save-redirect`: Persistència `saved_events` + flux `redirect` post-autenticació cap al detall original.
- `share-friends-search-copy`: UI de compartició amb filtre d'amics i còpia d'URL.
- `social-notification-feed`: Feed només lectura amb tipus esdeveniment i entrada.
- `ticket-transfer-notify-socket`: Transferència segura de tickets + notificacions + socket al destinatari.

### Modified Capabilities

- Pantalla D (`pages/events/[eventId]/index.vue`): integració Guardar/Compartir/Copiar segons FR.
- Pantalla Social (G/H): redisseny com a feed de notificacions.

## Impact

- **Backend**: Migració `notifications`, endpoints de llistat/marcatge, enviament d'esdeveniment a amic, reforç del flux de transferència de tickets existent, publicació Redis/Socket.
- **Frontend**: Middleware o utilitat de `returnUrl`, modal compartir, components de notificació, subscripció socket a room d'usuari.
- **Contractes**: Actualitzar `specs/001-seat-map-entry-validation/contracts/openapi.yaml` (o delta dins el canvi) amb nous recursos.
- **Proves**: Feature tests API + E2E flux guardat + compartició (on sigui viable en CI).

## User Stories (traçabilitat)

- **US3.1**: Com a usuari no registrat, vull que l'app recordi quin concert volia guardar després del login per no haver de tornar a cercar-lo cobert per FR-040 i escenaris de retorn al detall.
- **US3.2**: Com a usuari, vull enviar ràpidament un esdeveniment als meus amics amb un cercador (FR-041).
- **US3.3**: Com a usuari, vull que les entrades regalades apareguin al moneder sense acció manual (FR-046).
