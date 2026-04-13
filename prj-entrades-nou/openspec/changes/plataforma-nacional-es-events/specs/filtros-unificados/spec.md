## ADDED Requirements

### Requirement: Filtres a la pàgina de cerca
El sistema SHALL mostrar els filtres de dia, ubicació (text), text lliure i categories a la pàgina /search.

#### Scenario: Filtres visibles a la cerca
- **WHEN** l'usuari accedeix a la pàgina /search
- **THEN** veu els filtres de dia, ubicació, text i categories

### Requirement: Filtres a la pàgina de mapa
El sistema SHALL mostrar els mateixos filtres (dia, ubicació, text, categories) a la pàgina /search/map.

#### Scenario: Filtres visibles al mapa
- **WHEN** l'usuari accedeix a la pàgina /search/map
- **THEN** veu els mateixos filtres que a /search

### Requirement: Unificació de filtres entre llista i mapa
El sistema SHALL aplicar els mateixos filtres tant a la llista com al mapa.

#### Scenario: Filtres afecten llista i mapa
- **WHEN** l'usuari selecciona un filtre (ex: dia concret)
- **THEN** tant la llista com el mapa mostren només els esdeveniments que compleixen el filtre

### Requirement: Filtrebylabel de dia (calendari)
El sistema SHALL permetre filtrar per un dia específic mitjançant un calendari.

#### Scenario: Selecció de dia
- **WHEN** l'usuari selecciona un dia al calendari
- **THEN** només es mostren esdeveniments d'aquell dia

### Requirement: Filtrebylabel d'ubicació (text)
El sistema SHALL permetre filtrar per ubicació mitjançant text (ex: "Madrid, Madrid, Spain").

#### Scenario: Selecció d'ubicació
- **WHEN** l'usuari introdueix una ubicació
- **THEN** el sistema suggereix ciutats i l'usuari pot seleccionar-ne una

### Requirement: Filtrebylabel de categories
El sistema SHALL permetre filtrar per múltiples categories simultàniament.

#### Scenario: Selecció múltiple de categories
- **WHEN** l'usuari selecciona vàries categories
- **THEN** es mostren esdeveniments de qualsevol de les categories seleccionades

### Requirement: Mapa mostra el mateix subconjunt que la llista
El sistema SHALL fer que el mapa mostri només els esdeveniments que compleixen els filtres actius.

#### Scenario: Mapa reflecteix filtres
- **WHEN** hi ha filtres actius
- **THEN** els marcadors del mapa només representen els esdeveniments que compleixen els filtres