# Quickstart: entorn local (DAWTR3XXX-AppEsdeveniments)

## Requisits

- Docker + Docker Compose  
- Node.js 20 LTS, PHP 8.3, Composer (si s’executa fora de contenidors)

## Git: branca base `dev` (Git Flow)

La branca d’**integració** del codi és **`dev`**; les branques de treball (features, tasques Speckit) es cullen des de **`dev`** i es fusionen amb **pull request** cap a **`dev`**. Detall: [tasks.md](./tasks.md) (secció *Branca base `dev` i Git Flow*).

**Repositori nou o primer cop**

1. Inicialitzar i fer el primer commit (o usar el commit inicial existent):  
   `git init` (si cal) → afegir fitxers → `git commit -m "..."`  
2. Assegurar que la branca principal es diu **`dev`**:  
   - Si Git ha creat `master` o `main`: `git branch -m dev`  
   - O des d’un estat net: `git checkout -b dev`  
3. Pujar al remot i definir **`dev`** com a branca per defecte del desenvolupament:  
   `git remote add origin <url>` (si cal) → `git push -u origin dev`  
4. Les següents features: des de `dev` actualitzat (`git checkout dev && git pull`) → `git checkout -b 001-seat-map-tNNN-slug` (o el nom acordat) → PR cap a **`dev`**.

*Nota*: la carpeta **`docker/dev/`** és només l’entorn Docker de desenvolupament; no s’ha de confondre amb la branca Git **`dev`**, tot i que el nom coincideix per convenció.

## Arrencada amb Docker (carpeta `docker/dev/`)

Per desenvolupar la app s’usa el compose sota **`docker/dev/`** (no el de `docker/prod/`).

**Repositori amb carpeta `prj-entrades-nou/`** (monorepo): prefixa les rutes, p. ex.:

`docker compose -f prj-entrades-nou/docker/dev/docker-compose.yml up`

Des de l’arrel del monorepo quan el `docker-compose.yml` és directament a `docker/dev/`:

1. Copiar variables: `cp backend-api/.env.example backend-api/.env`, etc.  
2. Omplir com a mínim:

**backend-api/.env**

```env
DB_CONNECTION=pgsql
# Obligatori: postgres quan l’API corre dins el compose; 127.0.0.1 si l’API és al host amb Postgres exposat al 5432
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=esdeveniments
DB_USERNAME=...
DB_PASSWORD=...
REDIS_HOST=redis
REDIS_PORT=6379
TICKETMASTER_API_KEY=your_key
GEMINI_API_KEY=your_key
JWT_SECRET=your_jwt_secret
SOCKET_SERVER_INTERNAL_URL=http://socket-server:3001
SOCKET_INTERNAL_SECRET=your_internal_secret
```

El mateix **`JWT_SECRET`** ha de coincidir entre `backend-api`, `socket-server` i (per Socket privat) el client Nuxt si es prova validació / tickets en temps real. Si `SOCKET_SERVER_INTERNAL_URL` està buit, l’API no notificarà al socket (esborrany acceptable en dev).

**frontend-nuxt/.env**

```env
NUXT_PUBLIC_API_URL=http://localhost:8000
NUXT_PUBLIC_SOCKET_URL=http://localhost:3001
NUXT_PUBLIC_GOOGLE_MAPS_KEY=your_key
```

**socket-server/.env**

```env
REDIS_URL=redis://redis:6379
DATABASE_URL=postgresql://user:pass@postgres:5432/esdeveniments
JWT_SECRET=your_jwt_secret
```

3. Construir imatges (si el compose usa `build` cap a `docker/dockerfiles/`):  
   `docker compose -f docker/dev/docker-compose.yml build`  
4. Esquema PostgreSQL: **`database/init.sql`** + **`database/inserts.sql`** s’apliquen automàticament en el **primer** arrencada de Postgres (volum de dades nou). Si cal recrear l’esquema: `docker compose -f docker/dev/docker-compose.yml down -v` i tornar a pujar (esborra dades).  
5. `docker compose -f docker/dev/docker-compose.yml up`. **Adminer** (opcional, perfil `tools`): `docker compose --profile tools -f docker/dev/docker-compose.yml up` — després `http://localhost:8080` (PostgreSQL, servidor `postgres`, usuari/contrasenya com al compose).  
6. Obrir el Nuxt a la URL publicada (p. ex. `http://localhost:3000`).  
7. Opcional: `docker compose -f docker/dev/docker-compose.yml exec backend-api php artisan db:seed` per repetir seeds Laravel (rols addicionals, factories) si cal.

## Producció

Utilitzar **`docker/prod/docker-compose.yml`** (o el nom que fixeu) amb les mateixes imatges construïdes des de **`docker/dockerfiles/`** amb target **producció**, sense volums de codi editable. Vegeu `docker/prod/README.md` al repositori d’aplicació.

## Documentació SDD (`docs/`)

Mantenir **Constitution.md**, **Specify.md**, **Plan.md**, **TasksMvp.md** alineats amb `.specify/memory/constitution.md` i `specs/001-seat-map-entry-validation/plan.md` (o enllaçar entre repositoris si el codi i Speckit viuen en carpetes diferents).

## Verificació ràpida

- Health API: `GET /api/health` (afegir al backend).  
- Connexió Redis: `redis-cli PING` al contenidor.  
- PostGIS: `SELECT PostGIS_Version();` a Postgres.
- Rols Spatie (`user`, `validator`, `admin`): `php artisan db:seed --class=RoleSeeder` al contenidor `backend-api` si cal.
- Proves de càrrega complementàries del socket: vegeu `load/README.md` (T053).

## Verificació documentada (T038)

Stack aixecat amb `docker compose ... up`, variables `.env` omplies (almenys `JWT_SECRET`, `DB_*`, `REDIS_*`, URLs internes del socket) i `GET /api/health` retornant 200 des del host o des de `docker compose exec backend-api curl -s http://localhost:8000/api/health` segons exposició de ports del compose.

---

## T038 Sign-off (2026-04-11)

| Component | Versió | Estat |
|-----------|--------|-------|
| Docker Compose | 2.x | ✓ |
| Laravel | 13.x | ✓ |
| Nuxt | 3.x | ✓ |
| Socket.IO | 4.x | ✓ |
| Postgres (PostGIS) | 16-3.4 | ✓ |
| Redis | 7.2 | ✓ |
| Smoke API | OK | ✓ |

**Verificat:** 2026-04-11

## Cypress (proves E2E)

Per inicialitzar Cypress al monorepo (`frontend-nuxt/`, scripts, `cy.request` contra l’API), segueix la secció **Inicialització de Cypress (monorepo)** al [plan.md](./plan.md).
