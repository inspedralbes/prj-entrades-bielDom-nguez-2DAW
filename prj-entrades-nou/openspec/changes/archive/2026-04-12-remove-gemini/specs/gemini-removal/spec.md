## REMOVED Requirements

### Requirement: Gemini recommendation service
The system SHALL NOT use Google Gemini for personalized event recommendations.

#### Scenario: User visits home page
- **WHEN** user visits the home page
- **THEN** the feed shows events without Gemini-based recommendations

#### Scenario: User visits profile settings
- **WHEN** user visits profile settings
- **THEN** there is no option to enable/disable Gemini personalization

**Reason**: User decision to remove Gemini from the project entirely.

**Migration**: Remove all Gemini-related code, configs, and database columns. Feed "Triats per a tu" will now show events ordered by proximity or chronologically.