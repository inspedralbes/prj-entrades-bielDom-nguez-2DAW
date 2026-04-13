## Why

El deute P-T046-POSTGIS-FEED de `docs/tasksPendents.md` requereix feed per proximitat PostGIS com a opció independent de Gemini, activada per checkbox de l'usuari.

## What Changes

- Consulta PostGIS ST_Distance per proximitat
- Opt-in via `user_settings.proximity_personalization_enabled`
- Acceptar paràmetres lat/lng al feed API
- Venue amb coordenades PostGIS

## Capabilities

### New Capabilities
- `postgis-feed-proximity`: Feed ordenat per proximitat (independent de Gemini)

## Impact

- backend-api/FeedController.php
- database/