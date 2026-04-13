## ADDED Requirements

### Requirement: Docker Compose starts
The docker compose SHALL start without errors.

#### Scenario: All services start
- **WHEN** `docker compose up` executes
- **THEN** all containers are running

### Requirement: Smoke API works
The API SHALL respond to health check.

#### Scenario: /api/health returns 200
- **WHEN** curl GET /api/health
- **THEN** returns 200