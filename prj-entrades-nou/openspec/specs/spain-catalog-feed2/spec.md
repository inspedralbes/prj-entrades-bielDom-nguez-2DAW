# Spain Catalog Feed 2.0

## Purpose

Gestionar la importació d'esdeveniments des del catàleg de Ticketmaster Feed 2.0 per al territori espanyol. (TBD)

## Requirements

### Requirement: Importar esdeveniments d'Espanya des de TM Feed 2.0
El sistema SHALL utilitzar el Ticketmaster Discovery Feed 2.0 per importar esdeveniments del territori espanyol. La font de dades és Ticketmaster i el backend (Laravel) sincronitza periòdicament els esdeveniments.

#### Scenario: Sincronització inicial
- **WHEN** el sistema executa la tasca de sincronització amb TM Feed 2.0
- **THEN** s'importen tots els esdeveniments d'Espanya amb les categories especificades

#### Scenario: Sincronització periòdica
- **WHEN** la tasca cron executa la sincronització
- **THEN** els esdeveniments nous s'afegeixen i els actualitzats s'actualitzen a la base de dades

### Requirement: Filtrar esdeveniments per categories
El sistema SHALL excloure de la importació els esdeveniments de tipus "Museu" i els esdeveniments petits (menors de certa capacitat). Només s'importaran esdeveniments de lleure massiu.

#### Scenario: Filtrar museus
- **WHEN** TM Feed retorna un esdeveniment de categoria "Museum"
- **THEN** l'esdeveniment NO s'importa a la base de dades

#### Scenario: Filtrar esdeveniments petits
- **WHEN** TM Feed retorna un esdeveniment amb capacitat inferior a 500 persones
- **THEN** l'esdeveniment NO s'importa a la base de dades

### Requirement: Categories permeses
El sistema SHALL importar esdeveniments de les següents categories: Party, DJ, Social, Comedy, Film, Theatre, Art, Sport, Talk, Concerts.

#### Scenario: Importar concert
- **WHEN** TM Feed retorna un esdeveniment de categoria "Concert"
- **THEN** l'esdeveniment s'importa correctament

#### Scenario: Importar festa
- **WHEN** TM Feed retorna un esdeveniment de categoria "Party"
- **THEN** l'esdeveniment s'importa correctament

### Requirement: Persistir dades completes de l'esdeveniment
El sistema SHALL persistir per a cada esdeveniment: preu, URL oficial de Ticketmaster, ubicació exacta (PostGIS Point), horari d'inici, i tota la informació rellevant.

#### Scenario: Persistir ubicació
- **WHEN** s'importa un esdeveniment de TM
- **THEN** les coordenades s'emmagatzemen com a tipus PostGIS Point a la taula venues

#### Scenario: Persistir URL externa
- **WHEN** s'importa un esdeveniment
- **THEN** el camp tm_url s'omple amb l'URL oficial de Ticketmaster per a aquest esdeveniment