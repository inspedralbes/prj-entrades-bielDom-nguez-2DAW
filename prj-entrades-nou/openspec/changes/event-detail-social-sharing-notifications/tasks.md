## 1. Base de dades i models

**Branca suggerida:** `feature/notifications-table-social`

- [ ] 1.1 Crear migració Laravel per la taula `notifications` (tipus, payload JSON, `user_id`, `actor_user_id` opcional, `read_at`, timestamps)
- [ ] 1.2 Afegir índexos per consulta per usuari i data (`user_id`, `created_at DESC`)
- [ ] 1.3 Model Eloquent `Notification` amb casts de `payload`
- [ ] 1.4 Sincronitzar `database/init.sql`, `database/testing/schema.sqlite.sql` i documentació de BD si el projecte ho exigeix

**Commit:** Un cop migració verificada en local/Docker

---

## 2. Backend — Amics i compartició d'esdeveniment

**Branca suggerida:** `feature/api-friends-share-event`

- [ ] 2.1 Endpoint `GET /api/friends` o `/api/friends/search` amb paràmetre `q` per filtrar amics acceptats (relació basada en `friend_invites`)
- [ ] 2.2 Endpoint `POST /api/social/share-event` (o sota namespace coherent): cos `{ event_id, to_user_id }` — valida amistat, crea notificació tipus `event_shared`
- [ ] 2.3 Publicar esdeveniment Socket.IO cap a la room del destinatari després de persistència
- [ ] 2.4 Tests Feature per cerca d'amics i compartició (401, 403 si no amic, 200)

**Commit:** API estable amb proves verdes

---

## 3. Backend — Notificacions i transferència de tickets

**Branca suggerida:** `feature/api-notifications-ticket-share`

- [ ] 3.1 `GET /api/notifications` (paginació, ordre desc) i opcionalment `PATCH` per marcar llegides
- [ ] 3.2 Endpoint d'enviament d'entrada a amic (si encara no existeix en la forma requerida): transacció amb `ticket_transfers`, regeneració JWT/QR SVG, notificació `ticket_shared`
- [ ] 3.3 Emetre Socket.IO al destinatari en cada notificació rellevant
- [ ] 3.4 Tests Feature: després de transferència, el destinatari té el ticket llistat a l'API de tickets; l'emissor el perd

**Commit:** Cobertura mínima dels fluxos FR-045/046

---

## 4. Frontend — Detall d'esdeveniment (D)

**Branca suggerida:** `feature/event-detail-save-share-ui`

- [ ] 4.1 Guardar: si no autenticat, desar intenció (`save_event` + `eventId`) i navegar a `/login` amb `returnUrl` (o convenció del projecte)
- [ ] 4.2 Després de login/registre correcte, llegir `returnUrl` i executar POST a `saved_events` (o crida existent) abans de mostrar el detall
- [ ] 4.3 Botó Compartir: obrir modal amb input + icona cerca, crida debounced a l'API d'amics, llista filtrada
- [ ] 4.4 Botó copiar enllaç: `navigator.clipboard.writeText` amb URL absoluta de l'esdeveniment + feedback UI (toast/text)
- [ ] 4.5 Enviar esdeveniment seleccionant amic des del modal (crida API 2.2)

**Commit:** Flux US3.1 i FR-040–042 verificable manualment

---

## 5. Frontend — Social (G/H) i temps real

**Branca suggerida:** `feature/social-feed-socket`

- [ ] 5.1 Redissenyar pàgina Social: llista de notificacions (només lectura), estils DICE/coherents amb l'app
- [ ] 5.2 Component card esdeveniment: imatge, nom, hora, lloc; clic → `/events/{id}` (D)
- [ ] 5.3 Component card entrada: miniatura QR, descripció; clic → detall ticket (F) — ruta segons codi actual (`/tickets/...`)
- [ ] 5.4 Subscripció al socket en autenticat: en `notification:new`, refrescar llista o afegir entrada
- [ ] 5.5 Badge/comptador de no llegides (opcional segons temps)

**Commit:** Feed funcional amb dades reals

---

## 6. Contractes i documentació

- [ ] 6.1 Actualitzar `contracts/openapi.yaml` (o delta al directori del feature principal) amb nous paths i esquemes
- [ ] 6.2 Revisar `data-model.md` del Speckit si cal referència creuada

---

## 7. QA

- [ ] 7.1 Prova E2E (Cypress): anònim clica Guardar → login → torna al detall i esdeveniment desat (o es desa en arribar)
- [ ] 7.2 Prova manual: compartir entrada i verificar ticket al destinatari sense clicar la notificació
