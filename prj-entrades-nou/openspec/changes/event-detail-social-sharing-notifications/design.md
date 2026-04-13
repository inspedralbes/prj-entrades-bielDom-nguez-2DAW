## Context

El repositori ja té:

- Taula `saved_events` (usuari + esdeveniment únic).
- `friend_invites` (sender, receiver, estat; amistat efectiva quan l'estat és acceptat — cal definir valor exacte al codi existent).
- `ticket_transfers` (historial de transferències entre usuaris).
- Pantalla de detall `frontend-nuxt/pages/events/[eventId]/index.vue` amb botó Guardar i sense flux complet de compartició social descrit als FR.

Aquest canvi amplia D i G/H sense convertir la Social en un xat obert: la interacció addicional ve del flux **Compartició** (enviament d'actius), no de missatgeria lliure.

## Goals / Non-Goals

**Goals:**

- Implementar **retorn post-login** per completar Guardar (US3.1).
- Implementar **cercador d'amics** en temps real sobre la llista d'amics acceptats (FR-041) i **Clipboard API** per a l'URL (FR-042).
- Modelar **notifications** a PostgreSQL com a font de veritat del feed Social.
- **Socket.IO** cap a `user:{recipientId}` en crear notificació rellevant.
- **Transferència de tickets** amb regeneració de credencials (JWT ticket + QR SVG) i aparició automàtica a Tickets del destinatari (FR-046, US3.3).

**Non-Goals:**

- Xat amb text lliure entre usuaris.
- Notificacions push fora del web (APNs/FCM) en aquest canvi.
- Canvi del model de negoci principal de compres fora del que calgui per a transferències.

## Decisions

### D1: Persistència de la intenció "Guardar" (anònim)

**Decisió:** Abans de redirigir a `/login`, desar a `sessionStorage` o `localStorage` (clau estable del projecte) el parell `{ action: 'save_event', eventId }` i/o utilitzar query `redirect` o `return` a la URL de login (coherent amb `utils/authGate`).

**Rationale:** El middleware d'auth ja força cookie; cal una convenció única `returnUrl` o `redirect` que `login.vue` llegeixi i faci `navigateTo` després d'èxit.

### D2: Taula `notifications`

**Decisió:** Nova taula amb camps mínims: `id`, `user_id` (destinatari o titular del feed segons tipus; en el feed "Social" es mostra el que afecta l'usuari autenticat), `type` (`event_shared` | `ticket_shared` | altres futurs), `payload` JSON (ids, snapshots de text per a UI), `read_at`, `created_at`. Opcionalment `actor_user_id` (qui envia).

**Rationale:** Historial consultable, idempotent amb la lògica de negoci; el JSON permet evolucionar layouts sense migracions per cada camp nou de UI.

### D3: Filtre d'amics (FR-041)

**Decisió:** Endpoint `GET /api/friends?q=` que retorni usuaris amb relació acceptada (ambdues direccions segons model actual d'`friend_invites`). El frontend fa debounce sobre l'input.

**Rationale:** Filtrat al servidor per consistència i paginació futura; temps real "percebut" amb resposta ràpida i filtre incremental.

### D4: Esdeveniment compartit com a notificació

**Decisió:** En enviar, crear fila `notifications` per al destinatari i emetre socket. El payload inclou `event_id` i dades denormalitzades per al card (imatge, nom, hora, lloc) o joins a la lectura.

**Rationale:** FR-044 exigeix layout concret; denormalització opcional per rendiment de llista.

### D5: Seguretat ticket (FR-046)

**Decisió:** Reutilitzar i documentar el protocol existent de `ticket_transfers` + regeneració JWT/QR; assegurar transacció DB: transferència, invalidació antic QR, nou ticket/credencials per al nou propietari.

**Rationale:** Una sola font de veritat; tests de regressió sobre `Phase6AdminSocialApiTest` o tests nous dedicats.

### D6: Socket.IO

**Decisió:** Esdeveniment p.ex. `notification:new` amb payload lleuger (`notification_id`, `type`) o payload complet segons mida; room `user:{id}` ja assumida al disseny del projecte.

**Rationale:** Actualització instantània del badge/llista sense polling.

## Risks / Trade-offs

- **R1:** Duplicació de dades en `payload` vs joins — mitigar amb TTL de coherència o refresc en obrir detall.
- **R2:** Race entre login i guardat automàtic — idempotència de `saved_events` (UNIQUE user_id, event_id).
- **R3:** Clipboard API requereix context segur (HTTPS) i pot fallar en alguns navegadors — mostrar fallback (selecció manual o toast).

## Migration Plan

1. Migració `notifications` + índexos per `user_id`, `created_at`.
2. Endpoints Laravel + tests.
3. Frontend D + modal compartir + login redirect.
4. Redisseny G/H + socket client.
5. OpenAPI delta + verificació manual E2E.

## Open Questions

- **Q1:** Valor exacte d'estat `friend_invites` per "acceptat" al codi actual (`accepted` vs `completed`) — alinear amb seed i factories.
- **Q2:** El feed Social mostra només notificacions entrants o també sortints (FR-043 diu entrant/sortida) — confirmar disseny de dues pestanyes o timeline unificada amb indicador de direcció.
