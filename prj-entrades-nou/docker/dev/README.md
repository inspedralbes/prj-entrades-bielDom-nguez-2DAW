# Docker — entorn de desenvolupament

Des de l’**arrel del monorepo**:

```bash
docker compose -f docker/dev/docker-compose.yml build
docker compose -f docker/dev/docker-compose.yml up
```

**Adminer** (opcional): no s’inclou per defecte per evitar errors de `docker pull` quan el CDN de Docker falla. Per arrencar-lo: `docker compose --profile tools -f docker/dev/docker-compose.yml up` (o afegir `--profile tools` al teu `up` habitual).

Abans, copia les variables (vegeu [quickstart.md](../../specs/001-seat-map-entry-validation/quickstart.md)):

- `cp backend-api/.env.example backend-api/.env` i ajusta secrets. **Dins Docker**, `entrypoint-api.sh` exporta **`DB_HOST=postgres`**, **`REDIS_HOST=redis`** i la URL interna del socket; el `.env` pot seguir amb `127.0.0.1` per si executes Artisan al **host** amb Postgres exposat al 5432.
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

---

## Error al fer `up`: `short read` / `unexpected EOF` (pull d’imatges)

Això **no és un error del `docker build`** dels serveis del repo (aquest acostuma a acabar bé). Passa quan **Docker descarrega** imatges base (p. ex. Redis) des de Docker Hub i la xarxa talla o la capa queda corrupta.

1. Torna a provar el pull només de les imatges:
   ```bash
   docker compose -f docker/dev/docker-compose.yml pull
   docker compose -f docker/dev/docker-compose.yml up
   ```
2. Si persisteix, elimina la imatge Redis parcial i torna a baixar-la:
   ```bash
   docker rmi public.ecr.aws/docker/library/redis:7.2
   docker compose -f docker/dev/docker-compose.yml pull redis
   docker compose -f docker/dev/docker-compose.yml up
   ```
3. Opcional: `Docker Desktop` → **Restart**, o provar sense VPN / altra xarxa.

El servei **Redis** usa l’espeig **`public.ecr.aws/docker/library/redis:7.2`** (imatge oficial equivalent a `redis:7.2` a Docker Hub, però servida des d’AWS). Això evita sovint errors de pull cap al CDN R2 de Docker Hub (`cloudflarestorage.com`) quan la xarxa o el firewall tallen aquesta ruta.

Si fins i tot ECR falla, comprova proxy/VPN o configura un **registry mirror** al Docker Engine (Windows: Docker Desktop → Settings → Docker Engine).
