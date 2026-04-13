## ADDED Requirements

### Requirement: Pantalla de tiquets de l'usuari
El sistema SHALL mostrar una pantalla amb els tiquets de l'usuari quan accedeix a /tickets.

#### Scenario: Accés a la pantalla de tiquets
- **WHEN** l'usuari navega a /tickets
- **THEN** el sistema mostra els tiquets de l'usuari

### Requirement: Swipe horitzontal de tiquets
El sistema SHALL permetre navegar entre tiquets mitjançant un滑动 (swipe) horitzontal.

#### Scenario: Navegació entre tiquets
- **WHEN** l'usuari fa swipe horitzontal
- **THEN** es mostra el tiquet següent/anterior

### Requirement: Informació de l'esdeveniment sota cada tiquet
El sistema SHALL mostrar la informació de l'esdeveniment associat a cada tiquet a sota del tiquet.

#### Scenario: Visualització d'informació
- **WHEN** l'usuari visualitza un tiquet
- **THEN** la informació de l'esdeveniment es mostra a sota

### Requirement: Footer amb botó enrere i hora
El sistema SHALL mostrar un footer fixe amb un botó per tornar enrere i l'hora de l'esdeveniment.

#### Scenario: Footer visible
- **WHEN** l'usuari està a la pantalla de tiquets
- **THEN** el footer amb "Enrere" i l'hora de l'esdeveniment és visible

### Requirement: Detall del tiquet individual
El sistema SHALL permetre accedir al detall d'un tiquet especificant mitjançant /tickets/:id.

#### Scenario: Accés al detall
- **WHEN** l'usuari selecciona un tiquet específic
- **THEN** es mostra el detall del tiquet amb el QR