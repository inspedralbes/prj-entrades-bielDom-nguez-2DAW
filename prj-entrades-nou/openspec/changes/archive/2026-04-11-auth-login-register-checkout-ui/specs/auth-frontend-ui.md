## ADDED Requirements

### Requirement: Login form submits to API
The login form SHALL submit credentials to `POST /api/auth/login` and store the JWT token.

#### Scenario: Successful login
- **WHEN** user enters valid email/password and clicks login
- **THEN** API returns token, store is updated, user is redirected

#### Scenario: Invalid credentials
- **WHEN** user enters invalid credentials
- **THEN** error message is displayed below the submit button

#### Scenario: Field-level errors
- **WHEN** specific field has error (e.g., invalid email format)
- **THEN** error message is displayed next to that field in red text

### Requirement: Register form submits to API
The register form SHALL submit to `POST /api/auth/register` and auto-login after registration.

#### Scenario: Successful registration
- **WHEN** user fills all required fields and submits
- **THEN** user is registered and logged in

#### Scenario: Registration field errors
- **WHEN** field validation fails
- **THEN** error message displayed next to invalid field

### Requirement: Token expiry triggers logout
When the JWT token expires, the system SHALL clear the session and redirect to login.

#### Scenario: Token expired
- **WHEN** user action detects expired token (401 response)
- **THEN** session is cleared, user redirected to /login with message

### Requirement: Auth middleware protects routes
The auth middleware SHALL redirect unauthenticated users to /login.

#### Scenario: Access protected route without auth
- **WHEN** user navigates to /tickets without token
- **THEN** user is redirected to /login

#### Scenario: Access protected route with auth
- **WHEN** user navigates to /tickets with valid token
- **THEN** user can access the page

### Requirement: Checkout page exists with auth
The checkout page SHALL require authentication.

#### Scenario: Access checkout without auth
- **WHEN** user navigates to /checkout without token
- **THEN** user is redirected to /login