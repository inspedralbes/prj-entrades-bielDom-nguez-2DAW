# Base de dades (monorepo)

Font de veritat de l’**esquema relacional** per a PostgreSQL:

| Fitxer | Propòsit |
|--------|----------|
| **`init.sql`** | DDL: extensions PostGIS, taules Laravel (sessió, cua, cache), domini (venues, events, seats, orders, tickets, …), Spatie permission. |
| **`inserts.sql`** | Dades inicials de desenvolupament (rols Spatie, usuari de prova opcional). |

**Docker (`docker/dev/docker-compose.yml`)**: els scripts es muntan a `postgres:/docker-entrypoint-initdb.d/` i s’executen **només en crear el volum de dades buit**. Si cal regenerar l’esquema des de zero: `docker compose -f docker/dev/docker-compose.yml down -v` (esborra dades) i tornar a aixecar el servei.

**Laravel**: no s’usen migracions PHP per a l’esquema (`backend-api/database/migrations/` buit, vegeu el README d’allà). El model Eloquent continua alineat amb aquestes taules.

**Tests PHPUnit (SQLite)**: paritat d’esquema a `database/testing/schema.sqlite.sql`; el trait `Tests\Concerns\RefreshDatabaseFromSql` la carrega automàticament.

**Adminer** (gestor web lleuger): servei `adminer` al compose, p. ex. `http://localhost:8080` — sistema **PostgreSQL**, servidor **`postgres`**, usuari/contrasenya `esdeveniments` / `esdeveniments`, base `esdeveniments`. Imatge: **Adminer 4.8.1**.

Documentació funcional del model: [specs/001-seat-map-entry-validation/data-model.md](../specs/001-seat-map-entry-validation/data-model.md).
