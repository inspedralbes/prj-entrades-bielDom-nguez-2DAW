# Docker — entorn de desenvolupament

Des de l’**arrel del monorepo**:

```bash
docker compose -f docker/dev/docker-compose.yml build
docker compose -f docker/dev/docker-compose.yml up
```

Abans, copia les variables (vegeu [quickstart.md](../../specs/001-seat-map-entry-validation/quickstart.md)):

- `cp backend-api/.env.example backend-api/.env` i ajusta secrets (**`DB_HOST=postgres`** si l’API corre dins el compose).
- `cp frontend-nuxt/.env.example frontend-nuxt/.env` si cal.
- `cp socket-server/.env.example socket-server/.env` si cal.

**Esquema PostgreSQL:** `database/init.sql` i `database/inserts.sql` s’executen en crear el volum de dades de Postgres (primera vegada). Si cal recrear tot: `docker compose -f docker/dev/docker-compose.yml down -v` i tornar a pujar.

**(Opcional) Seeds Laravel** (rols addicionals, factories):

```bash
docker compose -f docker/dev/docker-compose.yml exec backend-api php artisan db:seed
```

**Adminer** (inspecció de taules): http://localhost:8080 — sistema **PostgreSQL**, servidor **`postgres`**, usuari/contrasenya com al servei `postgres` (per defecte `esdeveniments` / `esdeveniments`), base `esdeveniments`.

**Provar la UI:** Nuxt a http://localhost:3000 · API a http://localhost:8000 · Un cop tinguis un `event_id` amb zones i seients a la BD: http://localhost:3000/events/{eventId}/seats

**Ports (per defecte):** Postgres `5432`, Redis `6379`, Laravel `8000`, Nuxt `3000`, Socket.IO `3001`, **Adminer `8080`**.

**Nota (imatge `frontend-nuxt`):** el build Docker fa `npm ci --omit=dev` per **no instal·lar Cypress** dins la imatge (el postinstall de Cypress baixa un binari extern i sovint falla en builds sense DNS/restriccions). Per executar Cypress, fes-ho al host: `cd frontend-nuxt && npm install && npm run cypress:open`.

**Nota:** el `composer create-project` actual ha instal·lat **Laravel 13**; la constitució del projecte cita Laravel 11 — revisar `composer.json` si cal alinear versions.
