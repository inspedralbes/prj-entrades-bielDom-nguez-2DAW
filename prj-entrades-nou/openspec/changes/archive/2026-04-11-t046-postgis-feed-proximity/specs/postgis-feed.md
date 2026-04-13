## ADDED Requirements

### Requirement: Feed ordered by proximity when enabled
The feed SHALL be ordered by ST_Distance when user has proximity enabled in settings.

#### Scenario: Proximity enabled
- **WHEN** user_settings.proximity_personalization_enabled = true AND lat/lng provided
- **THEN** events ordered by distance

#### Scenario: Proximity disabled
- **WHEN** user_settings.proximity_personalization_enabled = false
- **THEN** return default order (not by proximity)

### Requirement: Venue coordinates required
Venues SHALL have PostGIS POINT geometry.