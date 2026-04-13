## ADDED Requirements

### Requirement: User can select up to 6 seats
The user SHALL be able to select between 1 and 6 seats on the seatmap.

#### Scenario: Select single seat
- **WHEN** user clicks on available seat
- **THEN** seat becomes selected, counter shows "1 / 6"

#### Scenario: Select maximum 6 seats
- **WHEN** user selects 6 seats
- **THEN** counter shows "6 / 6", cannot select more

#### Scenario: Deselect seat
- **WHEN** user clicks selected seat
- **THEN** seat becomes available, counter decrements

### Requirement: User can create hold
The user SHALL be able to create a hold via API.

#### Scenario: Create hold via API
- **WHEN** user clicks "Reservar"
- **THEN** POST /api/events/{id}/holds returns holdId

### Requirement: Countdown timer visible
The countdown timer SHALL be visible during hold.

#### Scenario: Timer displays
- **WHEN** hold is active
- **THEN** countdown timer is displayed

### Requirement: Seat status colors work
Seats SHALL show correct colors for their status.

#### Scenario: Available seats green
- **WHEN** seat status is "available"
- **THEN** seat shows green color

#### Scenario: Reserved seats yellow
- **WHEN** seat status is "reserved" (by other user)
- **THEN** seat shows yellow color

#### Scenario: Sold seats gray
- **WHEN** seat status is "sold"
- **THEN** seat shows gray color

### Requirement: Contention message displays
When a seat is taken, user SHALL see a message.

#### Scenario: Contention message
- **WHEN** Socket receives seat:contention event
- **THEN** toast/message shows "Seient no disponible"