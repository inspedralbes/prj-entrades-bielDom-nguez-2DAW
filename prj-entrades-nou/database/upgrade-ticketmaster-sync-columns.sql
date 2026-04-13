-- Patch opcional per bases PostgreSQL ja creades abans d’afegir la sincronització Ticketmaster.
-- Executar manualment contra la BD (p. ex. Adminer o psql) si no recrees el volum Docker.
-- Alternativa (recomanat en dev Docker): `php artisan db:patch-ticketmaster-schema` dins backend-api.

ALTER TABLE venues ADD COLUMN IF NOT EXISTS external_tm_id VARCHAR(255);
CREATE UNIQUE INDEX IF NOT EXISTS venues_external_tm_id_unique ON venues (external_tm_id) WHERE external_tm_id IS NOT NULL;

ALTER TABLE events ADD COLUMN IF NOT EXISTS tm_sync_paused BOOLEAN NOT NULL DEFAULT FALSE;
