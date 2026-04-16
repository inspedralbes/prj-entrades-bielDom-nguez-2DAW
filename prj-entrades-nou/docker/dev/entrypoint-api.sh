#!/bin/sh
set -e

cd /var/www/html

# El bind mount `backend-api:/var/www/html` substitueix el vendor de la imatge; en CI o clone fresc
# no hi ha `vendor/` al host. Composer ve a la imatge Docker (Dockerfile).
if [ ! -f vendor/autoload.php ]; then
  echo "Falta vendor/ al directori muntat; executant composer install..."
  composer install --no-interaction --prefer-dist --no-scripts
  composer dump-autoload -o
fi

# El volum munta `backend-api/.env` del host (normalment DB_HOST=127.0.0.1). Dins del contenidor,
# 127.0.0.1 és el propi contenidor, no Postgres/Redis. Els noms de servei del compose han de prevaldre.
export DB_HOST=postgres
export REDIS_HOST=redis
# Laravel (dins Docker) crida el socket per HTTP intern; ha de ser el nom DNS del servei, no localhost.
export SOCKET_SERVER_INTERNAL_URL=http://socket-server:3001

# Volums Postgres buits (sense init d'entrada): carregar init.sql + inserts del repo.
# Si falta `events` però ja hi ha altres taules, cal esborrar el volum (`docker compose down -v`).
export PGPASSWORD="${DB_PASSWORD:-esdeveniments}"

run_psql () {
  psql -v ON_ERROR_STOP=1 -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-esdeveniments}" -d "${DB_DATABASE:-esdeveniments}" "$@"
}

TABLE_COUNT=$(run_psql -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'" 2>/dev/null || echo "0")
EVENTS_COUNT=$(run_psql -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'events'" 2>/dev/null || echo "0")
USERS_COUNT=$(run_psql -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'" 2>/dev/null || echo "0")

# Cal users i events: abans només es comprovava «events»; si «events» existia però «users» no (BD trencada), Laravel retornava 500 a totes les rutes JWT.
if [ "$EVENTS_COUNT" != "1" ] || [ "$USERS_COUNT" != "1" ]; then
  if [ "$TABLE_COUNT" = "0" ] || [ -z "$TABLE_COUNT" ]; then
    if [ -f /var/www/database/init.sql ]; then
      echo "Base de dades buida: executant /var/www/database/init.sql ..."
      run_psql -f /var/www/database/init.sql
    fi
    if [ -f /var/www/database/inserts.sql ]; then
      echo "Executant /var/www/database/inserts.sql ..."
      run_psql -f /var/www/database/inserts.sql
    fi
  else
    echo "ERROR: falta public.users o public.events (esquema incomplet) però la BD no està buida."
    echo "Solució: atura els serveis i esborra el volum de Postgres, després torna a aixecar:"
    echo "  docker compose -f docker/dev/docker-compose.yml down -v"
    echo "  docker compose -f docker/dev/docker-compose.yml up --build"
    exit 1
  fi
fi

php artisan config:clear

# Volums Postgres antics: els scripts initdb només corren el primer cop; a cada arrencada local
# tornem a aplicar inserts.sql (idempotent: ON CONFLICT / NOT EXISTS) per alinear admin@example.com i demo.
if [ "${APP_ENV:-local}" = "local" ] && [ -f /var/www/database/inserts.sql ]; then
  USERS_TABLE_EXISTS=$(run_psql -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'" 2>/dev/null || echo "0")
  if [ "$USERS_TABLE_EXISTS" = "1" ]; then
    echo "Sincronitzant dades de desenvolupament (inserts.sql) ..."
    run_psql -f /var/www/database/inserts.sql
  fi
fi

# Si admin@example.com es va registrar abans via API, només tenia rol «user»; cal «admin» per /api/admin/* i /admin.
if [ "${APP_ENV:-local}" = "local" ]; then
  echo "Sincronitzant rols Spatie (admin+user) per admin@example.com ..."
  php artisan db:ensure-dev-admin-roles || true
fi

# Volums Postgres antics: no tornen a executar init.sql; columnes TM poden faltar.
php artisan db:patch-ticketmaster-schema

if [ "$APP_ENV" != "testing" ]; then
    echo "Running Ticketmaster initial sync..."
    php artisan ticketmaster:sync-events --pages=2 || true
fi

# Nginx + PHP-FPM (més ràpid i concurrent que el servidor integrat de `artisan serve`).
# FPM executa com a www-data: cal escriptura a storage i bootstrap/cache (abans sovint root amb `artisan serve`).
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

php-fpm -D
exec nginx -g "daemon off;"
