## Why

Cal implementar tres funcionalitats relacionades amb esdeveniments:
1. Importació inicial des de Ticketmaster Discovery en arrencar Docker
2. Sync diari que només afegeix esdeveniments nous (no actualitza existents)
3. CRUD complet per administrador des de l'API

## What Changes

1. **Docker bootstrap**: Executar import TM en `docker compose up` inicial
2. **Daily sync**: Job scheduled que només INSERT nous events (deduplicació per external_tm_id)
3. **Admin CRUD**: Endpoints API per crear/llegir/actualitzar/esborrar events manualment

## Capabilities

### New Capabilities
- `tm-docker-bootstrap`: Import inicial TM en arrencar Docker
- `tm-daily-sync`: Sync diari insert-only
- `admin-events-crud`: CRUD d'esdeveniments per admin

## Impact

- `backend-api/app/Services/Ticketmaster/`
- `database/`
- `docker/dev/docker-compose.yml`
- `routes/console.php`