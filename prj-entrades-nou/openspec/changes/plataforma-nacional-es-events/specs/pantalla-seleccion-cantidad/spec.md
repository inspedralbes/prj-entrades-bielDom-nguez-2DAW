## ADDED Requirements

### Requirement: Pantalla de selecció de quantitat
El sistema SHALL mostrar una pantalla per triar la quantitat d'entrades quan l'usuari fa clic a "Get Tickets" des del detall d'un esdeveniment.

#### Scenario: Accés a la pantalla
- **WHEN** l'usuari fa clic a "Get Tickets" al footer del detall
- **THEN** el sistema navega a /events/:id/seats

### Requirement: Input number per a quantitat
El sistema SHALL proporcionar un input de tipus number per seleccionar la quantitat d'entrades, amb rang permès d'1 a 6.

#### Scenario: Selecció de quantitat
- **WHEN** l'usuari interactua amb l'input de quantitat
- **THEN** només pot introduir valors entre 1 i 6

### Requirement: Total en viu (live total)
El sistema SHALL calcular i mostrar el preu total en viu segons la quantitat seleccionada.

#### Scenario: Actualització del total
- **WHEN** l'usuari canvia la quantitat d'entrades
- **THEN** el preu total s'actualitza automàticament

### Requirement: Mapa de la sala
El sistema SHALL mostrar un mapa estàtic de la sala (snapshot) a la pantalla de selecció de quantitat.

#### Scenario: Visualització del mapa de sala
- **WHEN** l'usuari està a la pantalla de selecció de quantitat
- **THEN** veu el mapa de la sala

### Requirement: Botó de compra
El sistema SHALL mostrar un botó per procedir al checkout.

#### Scenario: Inici del checkout
- **WHEN** l'usuari selecciona una quantitat i fa clic a "Comprar"
- **THEN** el sistema navega al checkout

### Requirement: Sense selecció de seients individuals
El sistema SHALL permetre la compra sense que l'usuari trii seients específics.

#### Scenario: Compra sense selecció de seats
- **WHEN** l'usuari selecciona quantitat i procedeix
- **THEN** no se li demana seleccionar seients específics