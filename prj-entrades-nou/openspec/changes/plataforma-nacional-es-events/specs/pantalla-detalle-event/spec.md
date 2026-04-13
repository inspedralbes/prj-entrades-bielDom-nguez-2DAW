## ADDED Requirements

### Requirement: Visualització del detall d'esdeveniment
El sistema SHALL mostrar la pantalla de detall d'un esdeveniment quan l'usuari accedeix a la ruta /events/:id.

#### Scenario: Accés al detall
- **WHEN** l'usuari fa clic a un esdeveniment
- **THEN** el sistema navega a /events/:id i mostra el detall complet

### Requirement: Botó de guardar esdeveniment
El sistema SHALL mostrar un botó per guardar l'esdeveniment als favorits.

#### Scenario: Guardar esdeveniment
- **WHEN** l'usuari fa clic al botó de guardar
- **THEN** l'esdeveniment es marca com a desat

### Requirement: Informació de l'esdeveniment
El sistema SHALL mostrar la informació completa de l'esdeveniment: nom, descripció, hora d'inici, ubicació exacta.

#### Scenario: Informació visible
- **WHEN** l'usuari visualitza el detall d'un esdeveniment
- **THEN** veu tota la informació de l'esdeveniment

### Requirement: Mapa de Google Maps amb marcador groc
El sistema SHALL mostrar un mapa de Google Maps amb un marcador de color groc a la ubicació de l'esdeveniment.

#### Scenario: Mapa amb marcador groc
- **WHEN** l'usuari visualitza el detall de l'esdeveniment
- **THEN** el mapa mostra un marcador de color groc

### Requirement: Modal de mapa enlargit
El sistema SHALL mostrar un modal (no pantalla completa) amb el mapa de major grandària quan l'usuari fa clic al mapa.

#### Scenario: Obertura del modal
- **WHEN** l'usuari fa clic al mapa
- **THEN** s'obre un modal amb el mapa enlargit

### Requirement: Enllaç a Google Maps
El sistema SHALL mostrar un botó "Open Google Maps" que obri la ubicació a l'aplicació o web de Google Maps.

#### Scenario: Obrir Google Maps
- **WHEN** l'usuari fa clic al botó "Open Google Maps"
- **THEN** s'obre Google Maps amb la ubicació de l'esdeveniment

### Requirement: Footer fixe amb preu i botó de compra
El sistema SHALL mostrar un footer fixe a la part inferior de la pantalla amb el preu per entrada a l'esquerra i el botó "Get Tickets" a la dreta.

#### Scenario: Footer visible
- **WHEN** l'usuari visualitza el detall de l'esdeveniment
- **THEN** el footer amb preu i "Get Tickets" és visible i fixe