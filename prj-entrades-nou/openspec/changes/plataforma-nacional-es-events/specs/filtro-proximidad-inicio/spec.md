## ADDED Requirements

### Requirement: Botó de proximitat a la pàgina d'inici
El sistema SHALL mostrar un botó a la part superior de la pàgina d'inici que permeti activar el filtre de proximitat.

#### Scenario: Botó visible a la pàgina d'inici
- **WHEN** l'usuari accedeix a la pàgina d'inici
- **THEN** el botó de proximitat és visible a la part superior de la pantalla

### Requirement: Configuració de radi en quilòmetres
El sistema SHALL permetre a l'usuari triar el radi de proximitat en quilòmetres (km).

#### Scenario: Selector de radi
- **WHEN** l'usuari activa el botó de proximitat
- **THEN** el sistema mostra un selector per triar el radi en km

### Requirement: Reseteig del filtre en canviar de ruta
El sistema SHALL desactivar el filtre de proximitat quan l'usuari navega fora de la pàgina d'inici i torna a entrar.

#### Scenario: Reseteig en navegar
- **WHEN** l'usuari navega des de la pàgina d'inici a una altra ruta i torna a la pàgina d'inici
- **THEN** el filtre de proximitat està desactivat

### Requirement: Persistència del filtre en refresh
El sistema SHALL mantenir l'estat del filtre de proximitat quan l'usuari fa refresh (F5) de la pàgina d'inici.

#### Scenario: Persistència en refresh
- **WHEN** l'usuari té el filtre de proximitat actiu i fa refresh de la pàgina
- **THEN** el filtre de proximitat segueix actiu

### Requirement: Filtratge per distància a l'API
El sistema SHALL filtrar els esdeveniments per distància quan el filtre de proximitat està actiu mitjançant crida a l'API.

#### Scenario: API filtra per distància
- **WHEN** el filtre de proximitat està actiu
- **THEN** l'API retorna només esdeveniments dins del radi especificat

### Requirement: El filtre de proximitat no afecta la cerca
El sistema SHALL mantenir el filtre de proximitat independent de la pàgina de cerca (/search).

#### Scenario: Filtre independent de cerca
- **WHEN** l'usuari activa el filtre de proximitat a la pàgina d'inici i navega a /search
- **THEN** el filtre de proximitat NO s'aplica a la cerca