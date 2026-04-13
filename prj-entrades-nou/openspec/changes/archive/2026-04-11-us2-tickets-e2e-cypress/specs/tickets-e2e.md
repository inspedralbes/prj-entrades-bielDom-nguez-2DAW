## ADDED Requirements

### Requirement: Ticket list shows tickets
The tickets list SHALL display user's tickets.

#### Scenario: List shows tickets
- **WHEN** user visits /tickets
- **THEN** list shows ticket cards with event name

#### Scenario: Empty list
- **WHEN** user has no tickets
- **THEN** message shows "No tens entrades"

### Requirement: Ticket detail page shows QR
The ticket detail page SHALL display QR code.

#### Scenario: QR visible
- **WHEN** user visits /tickets/{ticketId}
- **THEN** QR image is visible

### Requirement: Used ticket shows overlay
Used tickets SHALL show visual indication.

#### Scenario: Used ticket overlay
- **WHEN** ticket status is "used"
- **THEN** overlay with "X" is displayed

### Requirement: QR error handling
QR generation errors SHALL be handled gracefully.

#### Scenario: QR error
- **WHEN** QR endpoint returns error
- **THEN** error message is displayed