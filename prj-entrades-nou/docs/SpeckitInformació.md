# Informació Speckit — context del projecte

Aquest fitxer resumeix **on és cada cosa** del Speckit en aquest repositori (`prj-entrades-nou`), **què conté** i **com es relaciona**, per donar context ràpid a un agent o a un desenvolupador nou.

**Identificador de funcionalitat Speckit**: `001-seat-map-entry-validation`  
**Branca base del repositori (Git Flow)**: **`dev`** — les branques de treball es creen des de `dev`; integració amb **pull request** cap a `dev` (detall al [tasks.md](../specs/001-seat-map-entry-validation/tasks.md)).  
**Directori Speckit del feature**: [`specs/001-seat-map-entry-validation/`](../specs/001-seat-map-entry-validation/)

**Base de dades (esquema)**: carpeta [`database/`](../database/) — **`init.sql`** + **`inserts.sql`** (PostgreSQL/PostGIS al Docker); **`testing/schema.sqlite.sql`** per a PHPUnit. **Adminer 4.8.1** al `docker/dev/docker-compose.yml` (p. ex. port 8080). Sense migracions Laravel per al DDL (`backend-api/database/migrations/README.md`).

---

## 1. Mapa de fitxers del feature (`specs/001-seat-map-entry-validation/`)

| Fitxer | Funció |
|--------|--------|
| [**spec.md**](../specs/001-seat-map-entry-validation/spec.md) | Especificació normativa: clarificacions per sessions, **FR-001…FR-025**, user stories (US1–US3), criteris d’acceptació, **interfície per rol** (mapa de pantalles), definicions de rols (**Usuari**, **Validador**, **Administrador**), arquitectura de seguretat. |
| [**plan.md**](../specs/001-seat-map-entry-validation/plan.md) | Pla d’implementació: stack (Laravel 11, Nuxt 3, Redis, Socket.IO…), esdeveniments Socket, **Inicialització de Cypress** al monorepo, estructura de carpetes del monorepo, Docker dev/prod, variables `.env`, Constitution Check. |
| [**tasks.md**](../specs/001-seat-map-entry-validation/tasks.md) | Llista de tasques **T001–T053** per fases; **workflow Git** (`001-seat-map-tNNN-…` per tasca); **Cypress** obligatori (API + fluxos UI); prerequisits i dependències entre fases/US. |
| [**data-model.md**](../specs/001-seat-map-entry-validation/data-model.md) | Model de dades: `events`, `venues`, `zones`, `seats`, `orders`, `tickets`, **`friend_invites`**, **`ticket_transfers`**, usuaris, concurrència PostgreSQL, Redis holds. |
| [**research.md**](../specs/001-seat-map-entry-validation/research.md) | Decisions d’investigació: Top Picks, holds Redis, JWT/Socket híbrid, validació, Gemini només recomanació JSON. |
| [**quickstart.md**](../specs/001-seat-map-entry-validation/quickstart.md) | Arrencada local amb Docker (`docker/dev/`), variables `.env` per servei, verificació ràpida; enllaç a **Cypress** al pla. |
| [**contracts/openapi.yaml**](../specs/001-seat-map-entry-validation/contracts/openapi.yaml) | Contracte REST (OpenAPI 3.1): auth, seatmap, holds, login-grace, tickets, QR, validació, **social** (`friend-invites`, transferències), **admin** (Discovery sync). |
| [**checklists/ticketing.md**](../specs/001-seat-map-entry-validation/checklists/ticketing.md) | Checklist de domini ticketing (qualitat / verificació). |
| [**checklists/requirements.md**](../specs/001-seat-map-entry-validation/checklists/requirements.md) | Checklist de requisits (qualitat / cobertura). |

**Fitxer de registre del feature**: [`.specify/feature.json`](../.specify/feature.json) — conté `feature_directory` apuntant a `specs/001-seat-map-entry-validation`.

---

## 2. Constitució i plantilles (`.specify/`)

| Ubicació | Funció |
|----------|--------|
| [**memory/constitution.md**](../.specify/memory/constitution.md) | **Constitució del projecte**: font de veritat al servidor, stack acotat, temps real subordinat a l’API, PostGIS, Gemini assistencial, governança. **Prioritat** sobre altres guies en conflicte de principis. |
| `templates/spec-template.md` | Plantilla per noves especificacions. |
| `templates/plan-template.md` | Plantilla de pla. |
| `templates/tasks-template.md` | Plantilla de tasques. |
| `templates/constitution-template.md` | Plantilla de constitució. |
| `templates/checklist-template.md` | Plantilla de checklist. |
| `templates/agent-file-template.md` | Plantilla per fitxers d’agent. |
| `extensions/git/` | Scripts i comandes Speckit Git (crear branca feature, commit, validació, init repo, etc.). |
| `scripts/powershell/` | Scripts auxiliars (`check-prerequisites.ps1`, `create-new-feature.ps1`, `setup-plan.ps1`, `update-agent-context.ps1`, …). |
| `integrations/` | Manifests (p. ex. Cursor agent, speckit). |

---

## 3. Skills Speckit a Cursor (`.cursor/skills/`)

Al projecte hi ha skills amb prefix **`speckit-*`** (especificar, planificar, tasques, implementar, analitzar, checklist, aclarir, constitució, git, etc.). Ruta base: **`.cursor/skills/`** (relatiu a l’arrel del repo).

Servir per comandes tipus «speckit specify / plan / tasks / implement» segons el `SKILL.md` de cada un.

---

## 4. Documentació general a `docs/`

| Fitxer | Funció |
|--------|--------|
| [**ContextChat/Chat1.md**](ContextChat/Chat1.md) | Context exportat d’una conversa anterior (decisions **friend_invites**, Socket híbrid, Redis 4+2, guards Nuxt, T053, OpenAPI, workflow branca + Cypress). |
| [**ContextChat/Chat2.md**](ContextChat/Chat2.md) | Implementació US1 (holds, orders, mapa seients, Docker/Nuxt). |
| [**ContextChat/Chat3.md**](ContextChat/Chat3.md) | Tasques **T025–T029** (tickets JWT, QR SVG, API tickets, UI Nuxt), flux Git a **`3-Tr3`**, merge a **`dev`**. |
| **SpeckitInformació.md** (aquest fitxer) | Índex i resum del Speckit del projecte. |
| `tasks-mvp.md` | Tasques MVP (si existeix; pot complementar o precedir el detall del feature). |

La documentació SDD històrica (Constitution, Specify, Plan, TasksMvp) pot viure també sota `docs/` al monorepo d’aplicació; el **pla Speckit** de referència per aquest feature és el de **`specs/001-seat-map-entry-validation/plan.md`**.

---

## 5. Resum del contingut normatiu (una vista ràpida)

- **Monorepo previst**: `backend-api/` (Laravel), `frontend-nuxt/` (Nuxt + Pinia), `socket-server/` (Socket.IO + QR), `database/`, `docker/`.
- **Hold**: Redis, TTL base **240 s**, pròrroga **+120 s** una vegada des del checkout (`login-grace`), màxim efectiu **360 s** en aquesta política.
- **Socket.IO**: lectura **pública** d’estat de seients per `eventId`; **hold/compra** per API; **JWT** al handshake per **rooms privades** (`io.use()`).
- **Social**: taula **`friend_invites`**; transferències amb invalidació credencial antiga i **nou QR/SVG** al servidor.
- **Proves**: una **branca per tasca**; **Cypress** (`cy.request` + E2E UI); **k6/Artillery** per càrrega al socket-server (**T053**), no substitueix Cypress.
- **Cypress**: instruccions d’instal·lació al [**plan.md**](../specs/001-seat-map-entry-validation/plan.md) (secció *Inicialització de Cypress*); enllaç des del [**quickstart.md**](../specs/001-seat-map-entry-validation/quickstart.md).

---

## 6. Ordre de lectura recomanat per a un agent nou

1. [`.specify/memory/constitution.md`](../.specify/memory/constitution.md)  
2. [`specs/001-seat-map-entry-validation/spec.md`](../specs/001-seat-map-entry-validation/spec.md) (resum: FR i sessions)  
3. [`specs/001-seat-map-entry-validation/plan.md`](../specs/001-seat-map-entry-validation/plan.md)  
4. [`specs/001-seat-map-entry-validation/data-model.md`](../specs/001-seat-map-entry-validation/data-model.md)  
5. [`specs/001-seat-map-entry-validation/tasks.md`](../specs/001-seat-map-entry-validation/tasks.md)  
6. [`specs/001-seat-map-entry-validation/contracts/openapi.yaml`](../specs/001-seat-map-entry-validation/contracts/openapi.yaml)  
7. [`specs/001-seat-map-entry-validation/quickstart.md`](../specs/001-seat-map-entry-validation/quickstart.md)  
8. Opcional: [`docs/ContextChat/Chat1.md`](ContextChat/Chat1.md), [`Chat2.md`](ContextChat/Chat2.md), [`Chat3.md`](ContextChat/Chat3.md) per decisions i implementació ja documentades.

---

*Generat com a índex de context Speckit; actualitzar aquest fitxer si s’afegeixen nous directoris `specs/00x-*` o es reestructura `.specify/`.*
