## Context

El projecte utilitza Docker Compose a `docker/dev/docker-compose.yml` per arrencar stack local (Postgres, Redis, backend-api, frontend-nuxt, socket-server). Manualment funciona segons `quickstart.md` però no hi ha pipeline automatitzat per verificar el setup en CI.

## Goals / Non-Goals

**Goals:**
- Crear GitHub Actions workflow que faci `docker compose up --build`
- Verificar que tots els contenidors arriveen a estat "healthy"
- Executar smoke test contra API (`GET /api/health`)
- Executar Cypress tests E2E opcionalment

**Non-Goals:**
- No modificar els Dockerfiles existents
- No crear infraestructura de producció
- No-covereix testing de funcions no-relacionadesamb Phase 1

## Decisions

1. **GitHub Actions com a CI**: Ja existeix estructura `.github/workflows/`? →Workflow nou.
2. **Docker Compose file**: `docker/dev/docker-compose.yml` (segons quickstart).
3. **Health check**: `GET /api/health` (implementat a backend-api/routes/api.php).
4. **Wait strategy**: Utilitzar `healthcheck` del compose o script bash amb retry.
5. **Cypress**: Executar només si existeix `frontend-nuxt/cypress.config.*`.

## Risks / Trade-offs

- [Risk] Timeouts en CI → Configurar timeout adequat (10 min)
- [Risk] Ports ocupats al runner → Utilitzar ports alternatius o cleanup
- [Risk] Health endpoint no disponible → Verificar que existeix abans deworkflow