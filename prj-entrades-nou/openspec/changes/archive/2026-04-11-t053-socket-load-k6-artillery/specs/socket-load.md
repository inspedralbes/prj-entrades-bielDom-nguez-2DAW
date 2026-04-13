## ADDED Requirements

### Requirement: Public channel load test
The system SHALL handle many concurrent connections to public event channel.

#### Scenario: 100 concurrent connections
- **WHEN** 100 clients connect to public event channel
- **THEN** all connections succeed within 5s

### Requirement: Private channel with JWT
The system SHALL handle authenticated connections.

#### Scenario: 50 JWT connections
- **WHEN** 50 clients connect with JWT to private rooms
- **THEN** all authenticate