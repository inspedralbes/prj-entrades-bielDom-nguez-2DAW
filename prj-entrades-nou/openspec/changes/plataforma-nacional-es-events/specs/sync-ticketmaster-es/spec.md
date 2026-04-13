## ADDED Requirements

### Requirement: Sincronització completa d'Espanya
El sistema SHALL executar la sincronització de Ticketmaster per a tot el territori espanyol, processant totes les pàgines de resultats disponibles.

#### Scenario: Sincronització completa
- **WHEN** s'executa el job de sincronització Ticketmaster
- **THEN** el sistema processa totes les pàgines de resultats per a Espanya, no només les primeres

### Requirement: Paginació de la sincronització
El sistema SHALL implementar paginació per recórrer tots els resultats de l'API de Ticketmaster.

#### Scenario: Processament de múltiples pàgines
- **WHEN** l'API de Ticketmaster retorna més d'una pàgina de resultats
- **THEN** el sistema itera per totes les pàgines fins completar-les

### Requirement: Persistència de preu
El sistema SHALL guardar el preu de cada esdeveniment a la base de dades quan la informació està disponible a la resposta de Ticketmaster.

#### Scenario: Preu emmagatzemat
- **WHEN** la sincronització rep un esdeveniment amb informació de preu
- **THEN** el preu es persisteix a la taula d'esdeveniments

### Requirement: Persistència d'URL externa
El sistema SHALL guardar l'URL externa de Ticketing de cada esdeveniment quan està disponible.

#### Scenario: URL de compra emmagatzemada
- **WHEN** la sincronització rep un esdeveniment amb URL de compra
- **THEN** l'URL s'emmagatzema a la base de dades

### Requirement: Payload complet de Ticketmaster
El sistema SHALL guardar el màxim de informació disponible de Ticketmaster (venue, horaris, imatges, classificació) per als esdeveniments del catàleg.

#### Scenario: Dada de venue emmagatzemada
- **WHEN** la sincronització rep dades del venue
- **THEN** les dades del venue s'emmagatzemen a la base de dades

### Requirement: Filtre per categories a la sincronització
El sistema SHALL sincronitzar només els esdeveniments que pertanyin a les categories del catàleg (party, dj, social, comedy, film, theatre, art, sport, talk, concerts).

#### Scenario: Esdeveniments fora de categories no sincronitzats
- **WHEN** Ticketmaster retorna un esdeveniment que no pertany a les categories del catàleg
- **THEN** l'esdeveniment NO es sincronitza