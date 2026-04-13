## Why

Cal tancar el DoD de la Phase 1 Setup (T001–T006) segons `specs/001-seat-map-entry-validation/tasks.md` amb un pipeline automatitzat que verifiqui el setup complet. A `docs/tasksPendents.md` hi ha el deute P-T005-T006-CI i A-CI-DOCKER: no existeix job automatitzat que aixequi el stack Docker iexecutei smoke tests.

## What Changes

- Crear GitHub Actions workflow per CI que faci `docker compose -f docker/dev/docker-compose.yml up --build`
- Afegir pas de wait fins health endpoint (`GET /api/health` quan llest)
- Afegir pas de smoke API test (GET /api/health)
- Opcionalment executar Cypress E2E (`npm run cypress:run`)
- Opcionalment verificar existència de carpetes T001 (monorepo paths)

## Capabilities

### New Capabilities
- `ci-pipeline-smoke`: Pipeline CI amb Docker Compose i smoke tests per tancar Phase 1

### Modified Capabilities
- (none)

## Impact

- `.github/workflows/` - Workflow de GitHub Actions
- `docker/dev/` - Referància per docker-compose
- `specs/.../quickstart.md` - Documentació de referencia