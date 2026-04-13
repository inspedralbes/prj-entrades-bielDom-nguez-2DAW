# Context del chat 4 — Fase 6 (tancament), Fase 7 Speckit, Git, deute i `tasksPendents.md`

Aquest document resumeix el **fil d’aquest agent** sobre el monorepo **prj-entrades-nou**, feature **`001-seat-map-entry-validation`**, perquè un **nou agent** pugui continuar amb el mateix criteri.

**Relació amb xats anteriors:** [Chat1](./Chat1.md) (disseny, Socket, workflow), [Chat2](./Chat2.md) (US1), [Chat3](./Chat3.md) (T025–T029, US2). El **Chat 4** documenta: **pregunta sobre completitud de la fase 6**, **execució de la fase 7 (T045–T052) amb branques + merge a `dev` + push**, **taula d’auditoria spec ↔ codi**, creació de **`docs/tasksPendents.md`**, i **advertència de seguretat** (token GitHub exposat al xat).

---

## 1. Abast i referències

- Especificació: `specs/001-seat-map-entry-validation/` (`tasks.md`, `spec.md`, `contracts/openapi.yaml`).
- Codi principal: `prj-entrades-nou/backend-api/`, `frontend-nuxt/`, `socket-server/`, `database/`, `docker/`.
- **Git:** el repositori està a l’arrel **`3-Tr3`**; el codi de l’app en **`prj-entrades-nou/`**. Branca d’integració: **`dev`**; remot típic: `origin` → GitHub (`inspedralbes/prj-entrades-bielDom-nguez-2DAW` o equivalent).
- **Document nou d’aquest fil:** `docs/tasksPendents.md` — deute respecte al **text** del Speckit (buits per fase).

---

## 2. Resum de conversa (ordre cronològic)

1. **Fase 6 al 100%?** Resposta honesta: funcionalment tancada; matisos **T038** (verificació Docker manual), **T053** (k6 només smoke a `/health`, no stress complet SC-002/SC-004). Petició de **tatxar** tasques al `tasks.md` (Phase 6 amb ~~text~~).
2. **Implementar fase 7 + Git per tasca:** l’usuari demanava una **rama per tasca**, **commit**, **push** i **merge a `dev`**, i va incloure un **token GitHub (`ghp_…`)** al missatge. **No s’ha d’emmagatzemar el token al repo**; s’ha de **revocar** al GitHub si es va exposar. Els `git push` van funcionar amb credencials locals sense dependre del token al codi.
3. **Fase 7 implementada (T045–T052)** amb merges successius a `dev` i push de `origin/dev` i de les branques de feature. Inclou commits finals de documentació (`tasks.md` Phase 7 marcada completada) i neteja menor (`search/index.vue`).
4. **Pregunta «tot el Speckit implementat?»** Resposta: el **checklist** del `tasks.md` està marcat; hi ha **forats** entre el **text literal** del spec i el codi (Discovery proxy, admin complet, T053 exhaustiu, OpenAPI fase 7, Cypress DoD complet, etc.).
5. **Taula d’auditoria** (resposta al xat): resum en taula markdown dels buits (sense fitxer nou).
6. **Fitxer `docs/tasksPendents.md`:** creat amb format Speckit (YAML front matter, seccions per fase, ítems **P-Txxx** / **A-***) amb especificació, estat actual, buit, fitxers, tecnologies, DoD.

---

## 3. Fase 7 — Branques i lliurament (resum tècnic)

Cada tasca es va desenvolupar en una branca del tipus `001-seat-map-t0NN-<slug>`, merge a **`dev`**, push.

| Tasca | Branca (exemple) | Entregable principal |
|--------|-------------------|----------------------|
| T045 | `001-seat-map-t045-layouts` | `layouts/default.vue`, `layouts/admin.vue`, `app.vue` amb `<NuxtLayout>`, CSS `assets/css/app.css`, pàgines stub (search, saved, social, profile, admin), `middleware/admin.js`, Cypress `phase7-shell.cy.js` |
| T046 | `001-seat-map-t046-home-feed` | `FeedController`, `GeminiHomeRecommendService` (stub), `UserSetting`, rutes `/api/feed/featured`, `/api/feed/for-you`, `FeedApiTest`, home `pages/index.vue` |
| T047 | `001-seat-map-t047-search-list` | `SearchEventsController`, `SavedEventsController`, model `SavedEvent`, rutes search + saved-events, `SearchSavedApiTest`, UI `pages/search/index.vue`, `deleteJson` a `useAuthorizedApi.js` |
| T048 | `001-seat-map-t048-search-map` | Coordenades stub `map_lat`/`map_lng` a la resposta de cerca, `useGoogleMapsLoader.js`, `pages/search/map.vue` (Google Maps + «Com arribar») |
| T049 | `001-seat-map-t049-saved-profile` | `UserProfileController`, `GET/PATCH /api/user/profile`, `PATCH /api/user/settings`, `UserProfileApiTest`, `patchJson`, pàgines `saved`, `profile` |
| T050 | `001-seat-map-t050-social` | `pages/social/index.vue` (amics, invitacions, convidar per ID, helpers `canAcceptInvite` / `inviteDirection`) |
| T051 | `001-seat-map-t051-tickets-ui` | Modal «Enviar entrada» a `pages/tickets/index.vue` → `POST /api/tickets/{id}/transfer` |
| T052 | `001-seat-map-t052-admin-panel` | `pages/admin/index.vue` (polling `GET /api/admin/summary`, Socket `admin:metrics`), `useAdminDashboard.js`, **socket-server**: esdeveniment `join:admin-dashboard` → `socket.join('admin:dashboard')` per rebre emissions HTTP a la room correcta |

**Nota:** la cerca **T047** és sobre la taula **`events` local**, no un **proxy Ticketmaster Discovery** complet (documentat com a deute a `tasksPendents.md`).

---

## 4. Altres canvis rellevants (context tècnic)

- **`AuthenticateJwt` + Spatie `role:admin`:** el middleware de rol usa `Auth::guard('web')`; cal **sincronitzar l’usuari JWT** amb `Auth::guard('web')->setUser($user)` (ja present abans d’aquest fil en resums anteriors; important no treure’l o els endpoints admin retornen 403 als tests).
- **Tests PHPUnit:** la suite va créixer (p. ex. **42 tests** després dels nous `FeedApiTest`, `SearchSavedApiTest`, `UserProfileApiTest`; el nombre exacte pot variar si s’afegeixen més proves).
- **`docs/tasksPendents.md`:** llista prioritzada de buits (OpenAPI fase 7, CI Docker, stress T053, Discovery, admin FR-025 complet, Gemini real, login/checkout, Cypress DoD, etc.).

---

## 5. Seqüència Git (patró usat en aquest fil)

Des de l’arrel **`3-Tr3`**:

```bash
git checkout dev && git pull origin dev
git checkout -b 001-seat-map-t0NN-<slug>
# canvis sota prj-entrades-nou/
git add …
git commit -m "feat(T0NN): …"
git checkout dev
git merge 001-seat-map-t0NN-<slug> --no-edit
git push origin dev
git push origin 001-seat-map-t0NN-<slug>
```

La subcarpeta **`prj-entrades-a24biedommar`** (si apareix com a modificada) **no** forma part dels lliuraments descrits; sovint és submòdul o altre projecte al mateix repo.

---

## 6. Estat del `tasks.md` (referència)

- **Fases 1–6:** tasques originals marcades **[X]** (la fase 6 es va discutir amb format ~~tatxat~~ en un moment del fil; el fitxer pot tornar a **[X]** sense tatxat segons l’últim commit).
- **Fase 7:** marcada com a **completada** al checklist amb ítems tatxats (~~T045…~~) en el commit de documentació.

**Important:** «completat» al checklist **no** equival a **paritat 100%** amb cada paràgraf del `spec.md` — vegeu **`tasksPendents.md`**.

---

## 7. Idioma i convencions

- Respostes a l’usuari: sovint **castellà** (preferència d’usuari).
- Documentació del projecte i fitxers com aquest: **català**, alineat amb [Chat3](./Chat3.md).

---

## 8. Metadades d’aquest fitxer

| Camp | Valor |
|------|--------|
| Fitxer | `docs/ContextChat/Chat4.md` |
| Propòsit | Handoff del chat 4 (fase 6/7, Git, deute, `tasksPendents.md`, seguretat token) |
| Feature | `001-seat-map-entry-validation` |
| Data referència entorn | 2026-04-11 |

---

## 9. Següents passos suggerits (per un agent nou)

1. Revisar **`docs/tasksPendents.md`** i triar prioritat (OpenAPI, admin complet, Discovery, stress).
2. **Revocar** qualsevol **Personal Access Token** exposat en xats públics i en generar un de nou si cal.
3. Ampliar **`contracts/openapi.yaml`** amb els endpoints de la fase 7 abans de publicar API externament.

---

*Fi del context Chat4.*
