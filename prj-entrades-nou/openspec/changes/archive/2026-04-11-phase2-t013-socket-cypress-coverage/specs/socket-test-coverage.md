## ADDED Requirements

### Requirement: Public socket connection without JWT
The socket server SHALL allow connections to the public namespace without JWT and emit `server:hello`.

#### Scenario: Public connection succeeds
- **WHEN** client connects to Socket.IO with query `?eventId=123`
- **THEN** client joins room `event:123` and receives `server:hello` event

#### Scenario: Public connection receives broadcast
- **WHEN** client subscribed to `event:123` receives broadcast from server
- **THEN** client can receive `seat:contention` events without JWT

### Requirement: Private namespace rejects without JWT
The private namespace SHALL reject connections that do not provide a valid JWT token.

#### Scenario: Private connection without token fails
- **WHEN** client connects to `/private` namespace without auth.token
- **THEN** connection is rejected with error "Unauthorized"

### Requirement: Private namespace accepts valid JWT
The private namespace SHALL accept connections with valid JWT and allow access to user-specific rooms.

#### Scenario: Private connection with valid JWT succeeds
- **WHEN** client connects to `/private` with `auth.token: "Bearer <valid_jwt>"`
- **THEN** connection is accepted and joins `user:{userId}` room

#### Scenario: Private emits to user room
- **WHEN** server emits to `user:42`
- **THEN** only client with JWT sub=42 receives the event