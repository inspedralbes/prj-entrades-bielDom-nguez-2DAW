# City Dropdown Search

## Purpose

Cercador de ciutats amb autocompletat i integració amb mapa a la pàgina de cerca. (TBD)

## Requirements

### Requirement: Cercador de ciutats amb autocompletat
El sistema SHALL proporcionar un cercador de text a la pàgina de cerca que mostri suggeriments de ciutats mentre l'usuari escriu.

#### Scenario: Autocompletat durant l'escriptura
- **WHEN** l'usuari tecleja "Ma" al cercador
- **THEN** apareixen suggeriments com "Madrid, Spain", "Màlaga, Spain"

#### Scenario: Select clear de ciutat
- **WHEN** l'usuari selecciona una ciutat de la llista
- **THEN** el cercador mostra el nom complet de la ciutat

### Requirement: Mapa desplaçable a la localització seleccionada
El sistema SHALL moure el mapa per centrar-se a la ciutat seleccionada quan l'usuari tria una opció del dropdown.

#### Scenario: Seleccionar Madrid
- **WHEN** l'usuari selecciona "Madrid, Spain" del dropdown
- **THEN** el mapa es centra a les coordenades de Madrid

### Requirement: Sincronització de la llista d'esdeveniments
El sistema SHALL actualitzar la llista d'esdeveniments per mostrar els resultats propers a la ciutat seleccionada quan s'escull una opció del dropdown.

#### Scenario: Resultats a la ciutat seleccionada
- **WHEN** l'usuari selecciona una ciutat
- **THEN** la llista d'esdeveniments es filtra per mostrar els esdeveniments propers a aquella ciutat

### Requirement: Filtres creuats amb el mapa
El sistema SHALL mantenir sincronitzats els filtres de categories, dates i text amb els marcadors del mapa en temps real.

#### Scenario: Canviar filtre de categoria
- **WHEN** l'usuari selecciona la categoria "Concerts"
- **THEN** els marcadors del mapa s'actualitzen per mostrar només concerts

#### Scenario: Canviar filtre de dates
- **WHEN** l'usuari selecciona un rang de dates
- **THEN** els marcadors del mapa mostren només esdeveniments dins d'aquest rang

### Requirement: Mapa d'Espanya complet
El sistema SHALL mostrar marcadors de tots els esdeveniments disponibles a Espanya (dins del conjunt importat i regles de visibilitat) al mapa de la pàgina de cerca.

#### Scenario: Carregar mapa
- **WHEN** l'usuari carrega la pàgina de cerca
- **THEN** el mapa mostra marcadors de tots els esdeveniments d'Espanya