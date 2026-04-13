## ADDED Requirements

### Requirement: OpenAPI sync with backend API routes
El contracte OpenAPI a `specs/001-seat-map-entry-validation/contracts/openapi.yaml` SHALL reflectir totes les rutes definides a `backend-api/routes/api.php`.

#### Scenario: All routes documented
- **WHEN** es compara el contracte OpenAPI amb les rutes de api.php
- **THEN** cada ruta te un endpoint documentat equivalent

#### Scenario: New endpoints from Phase 7 added
- **WHEN** es troben endpoints de fase 7 al codi que no estan al contracte
- **THEN** s'afegeixen al document OpenAPI

### Requirement: Valid OpenAPI 3 YAML
El document YAML SHALL ser vàlid segons l'especificació OpenAPI 3.1.

#### Scenario: YAML validation passes
- **WHEN** s'executa un validador OpenAPI (swagger-cli)
- **THEN** no retorna errors de validació

### Requirement: Accurate schemas and examples
Cada schema SHALL tenir exemples vàlids que representin resposta reals.

#### Scenario: All response schemas have examples
- **WHEN** es revisa un schema de resposta
- **THEN** té un camp `example` o `examples` definit

### Requirement: Proper error codes
Cada endpoint SHALL documentar codis d'error 4xx rellevants.

#### Scenario: Error responses documented
- **WHEN** un endpoint pot retornar errors
- **THEN** te responses definides pels codis 4xx aplicables