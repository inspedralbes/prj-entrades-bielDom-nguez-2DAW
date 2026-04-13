## ADDED Requirements

### Requirement: Catàleg nacional per defecte
El sistema SHALL mostrar per defecte tots els esdeveniments de tot el territori espanyol quan l'usuari accedeix a la pàgina d'inici, sense aplicar cap filtre de proximitat.

#### Scenario: Accés a la pàgina d'inici
- **WHEN** l'usuari accedeix a la ruta `/` sense paràmetres
- **THEN** el sistema mostra una llista d'esdeveniments de totes les ciutats d'Espanya

### Requirement: Categories del catàleg
El sistema SHALL filtrar els esdeveniments per les categories definides: party, dj, social, comedy, film, theatre, art, sport, talk, concerts.

#### Scenario: Filtre per categoria
- **WHEN** l'usuari selecciona una o més categories
- **THEN** el sistema mostra només esdeveniments que pertanyin a les categories seleccionades

### Requirement: Exclusió de museus
El sistema SHALL excloure els museus i l'oferta cultural genèrica que no pertanyi a les categories especificades del catàleg.

#### Scenario: Cer到的 esdeveniments no són museus
- **WHEN** l'usuari cerca esdeveniments sense especifcar categories
- **THEN** els resultats no inclouen museus ni exposicions genèriques

### Requirement: Preselecció de categories
El sistema SHALL mostrar per defecte les categories de música, festa i entreteniment (party, dj, concerts) quan no s'aplica cap filtre de categories.

#### Scenario: Categories per defecte actives
- **WHEN** l'usuari accedeix a la pàgina d'inici sense filtres
- **THEN** les categories party, dj i concerts estan pre-seleccionades