## ADDED Requirements

### Requirement: Docker bootstrap imports TM events
The system SHALL import Ticketmaster events on first Docker start.

#### Scenario: First Docker start
- **WHEN** docker compose up runs first time
- **THEN** TM import populates events/venues

### Requirement: Daily sync inserts only new events
The daily sync SHALL only INSERT new events, never update existing.

#### Scenario: New events added
- **WHEN** TM has new external_tm_id
- **THEN** INSERT new event record

#### Scenario: Existing event
- **WHEN** external_tm_id already exists
- **THEN** skip (no update)

### Requirement: Admin can CRUD events
Admin SHALL have full CRUD over events via API.

#### Scenario: Create event
- **WHEN** POST /api/admin/events
- **THEN** event created

#### Scenario: Update event
- **WHEN** PATCH /api/admin/events/{id}
- **THEN** event updated

#### Scenario: Delete event
- **WHEN** DELETE /api/admin/events/{id}
- **THEN** event deleted