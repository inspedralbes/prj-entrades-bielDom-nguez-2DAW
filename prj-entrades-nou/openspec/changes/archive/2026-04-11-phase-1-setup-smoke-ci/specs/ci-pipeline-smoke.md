## ADDED Requirements

### Requirement: Docker Compose stack starts successfully in CI
The CI pipeline SHALL start the Docker Compose stack using `docker compose -f docker/dev/docker-compose.yml up --build` and wait until all containers reach healthy state.

#### Scenario: All containers healthy
- **WHEN** CI executes `docker compose up --build`
- **THEN** all services (postgres, redis, backend-api, frontend-nuxt, socket-server) report healthy status

#### Scenario: Container failure detected
- **WHEN** any container fails to start or becomes unhealthy
- **THEN** CI job fails with descriptive error

### Requirement: API health endpoint responds
The backend API SHALL respond to `GET /api/health` with HTTP 200 after stack is healthy.

#### Scenario: Health endpoint returns 200
- **WHEN** health check script calls `GET /api/health`
- **THEN** response status is 200 and body contains `{"status":"ok"}` or similar

#### Scenario: Health endpoint unavailable
- **WHEN** health check times out or returns error
- **THEN** CI job fails indicating API not ready

### Requirement: Optional Cypress E2E tests pass
If Cypress is configured, the CI pipeline SHALL execute `npm run cypress:run` and report results.

#### Scenario: Cypress tests pass
- **WHEN** `npm run cypress:run` executes
- **THEN** all tests pass (exit code 0)

#### Scenario: Cypress tests skip (not configured)
- **WHEN** Cypress config does not exist
- **THEN** CI continues without failure

### Requirement: Optional monorepo paths exist (Phase 1 T001 verification)
The CI pipeline MAY verify that T001 monorepo directories exist.

#### Scenario: All required directories exist
- **WHEN** CI checks for `backend-api/`, `frontend-nuxt/`, `socket-server/`, `database/`, `docker/dev/`
- **THEN** all directories exist

#### Scenario: Missing directories
- **WHEN** any required directory is missing
- **THEN** CI job fails indicating missing paths