## ADDED Requirements

### Requirement: Gemini returns event IDs
The service SHALL return IDs of existing events from Gemini.

#### Scenario: Gemini returns IDs
- **WHEN** Gemini API is called
- **THEN** returns array of event IDs

### Requirement: Opt-in required
The service SHALL only call Gemini if user has enabled personalization.

#### Scenario: Opt-in disabled
- **WHEN** user_settings.gemini_personalization_enabled = false
- **THEN** return default feed (no Gemini call)

### Requirement: Fallback on error
The service SHALL fallback to default feed if Gemini fails.

#### Scenario: Gemini API error
- **WHEN** Gemini returns error
- **THEN** return default feed