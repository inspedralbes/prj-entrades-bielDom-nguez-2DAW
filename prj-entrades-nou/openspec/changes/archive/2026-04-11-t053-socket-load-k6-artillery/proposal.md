## Why

El deute P-T053-LOAD de `docs/tasksPendents.md` (Task T053) requereix proves de càrrega pel Socket.IO: connexions massives a canals pública per eventId + subconnexions JWT a rooms privades, mesurar percentils.

## What Changes

- Script de load test per Socket.IO (k6 o Node)
- Métriques: 95% latència, throughput
- Documentació a load/README.md

## Capabilities

### New Capabilities
- `socket-load-testing`: Proves de càrrega Socket.IO

## Impact

- `load/` - Scripts de test