## ADDED Requirements

### Requirement: Vista global per defecte a la Home
El sistema SHALL mostrar tots els esdeveniments d'Espanya (dins del filtre de categories del producte) en carregar la pàgina Home.

#### Scenario: Carregar Home
- **WHEN** l'usuari carrega la pàgina Home
- **THEN** es mostren tots els esdeveniments importats d'Espanya sense aplicar filtre de proximitat

### Requirement: Toggle de proximitat
El sistema SHALL proporcionar un botó a la part superior de la Home per activar/desactivar el filtre de proximitat.

#### Scenario: Activar filtre de proximitat
- **WHEN** l'usuari fa clic al botó de proximitat
- **THEN** apareix un selector de radi (km) i els esdeveniments es filten segons el radi seleccionat

#### Scenario: Desactivar filtre de proximitat
- **WHEN** l'usuari torna a fer clic al botó de proximitat
- **THEN** el selector de radi desapareix i es mostren tots els esdeveniments d'Espanya

### Requirement: Selector de radi
El sistema SHALL mostrar un selector de radi en kilòmetres quan el filtre de proximitat està actiu.

#### Scenario: Canviar radi
- **WHEN** l'usuari selecciona un nou valor de radi (ex: 50km)
- **THEN** els esdeveniments es tornen a filtrar segons el nou radi

### Requirement: Persistència del filtre en refresh
El sistema SHALL mantenir l'estat del filtre de proximitat (actiu/inactiu i valor del radi) quan l'usuari fa refresh de la pàgina Home.

#### Scenario: Refrescar pàgina
- **WHEN** l'usuari fa refresh de la pàgina Home amb filtre actiu
- **THEN** el filtre segueix actiu amb el mateix radi seleccionat

### Requirement: Reset del filtre en navegar
El sistema SHALL resetejar l'estat del filtre de proximitat quan l'usuari navega a una altra secció i torna a la Home.

#### Scenario: Tornar a Home des d'altra secció
- **WHEN** l'usuari navega a Cerca o Detall i posteriorment torna a Home
- **THEN** el filtre de proximitat està inactiu (vista global)

### Requirement: Independència del filtre de cerca
El sistema SHALL mantenir els estats del filtre de proximitat de la Home i de la pàgina de Cerca de manera independent.

#### Scenario: Canviar secció
- **WHEN** l'usuari activa el filtre a Home i navega a Cerca
- **THEN** el filtre a Cerca està inactiu (per defecte) i no es veu afectat pel filtre de Home