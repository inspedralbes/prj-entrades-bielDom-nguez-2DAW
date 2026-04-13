## ADDED Requirements

### Requirement: FR-043 Canal Social tipus xat només lectura

El sistema SHALL mostrar la secció Social (G/H) com un historial de notificacions d'entrada i sortida relatives a actius compartits.

#### Scenario: Sense missatges lliures
- **WHEN** l'usuari visualitza la secció Social
- **THEN** no hi ha camp per escriure missatges de text lliure

#### Scenario: Origen de les interaccions
- **WHEN** l'usuari vol enviar un esdeveniment o una entrada
- **THEN** només pot fer-ho des del flux de Compartir (o equivalent definit al detall / tickets), no des del feed Social

### Requirement: FR-044 Layout de notificació d'esdeveniment

Les notificacions que representin un esdeveniment compartit SHALL mostrar un bloc visual amb foto, nom, hora i lloc (segons dades disponibles de l'esdeveniment).

#### Scenario: Navegació al detall
- **WHEN** l'usuari fa clic en una notificació d'aquest tipus
- **THEN** el sistema navega a la pantalla de Detall d'esdeveniment (D) de l'esdeveniment corresponent

### Requirement: FR-045 Layout de notificació d'entrada

Les notificacions que representin una entrada compartida SHALL mostrar miniatura del QR (o representació acordada) i descripció de l'entrada.

#### Scenario: Navegació al detall de ticket
- **WHEN** l'usuari fa clic en una notificació d'aquest tipus
- **THEN** el sistema navega al Detall del ticket (F)

### Requirement: FR-046 Auto-save de tickets al destinatari

Quan un usuari envia una entrada a un amic, el servidor SHALL executar la transferència de propietat d'acord amb el protocol de seguretat del producte.

#### Scenario: Invalidació i nou credencial
- **WHEN** la transferència es completa amb èxit
- **THEN** el QR anterior deixa de ser vàlid per al propietari anterior
- **AND** es genera un nou JWT de ticket i un nou QR en format SVG per al destinatari

#### Scenario: Visibilitat al moneder sense clic
- **WHEN** el destinatari rep l'entrada
- **THEN** l'entrada apareix a la secció Tickets del destinatari encara que no obri la notificació (US3.3)

### Requirement: Font de veritat PostgreSQL

El sistema SHALL registrar les notificacions i les transferències a PostgreSQL.

#### Scenario: Taules implicades
- **WHEN** es processa una acció social coberta per aquest canvi
- **THEN** les dades queden persistides a `friend_invites` (quan aplica), `ticket_transfers` (quan aplica) i la taula `notifications`

### Requirement: Temps real Socket.IO

Després d'enviar un esdeveniment o una entrada a un amic, el sistema SHALL notificar el destinatari en temps real mitjançant Socket.IO.

#### Scenario: Emissió a room d'usuari
- **WHEN** es crea una notificació per al destinatari
- **THEN** el servidor emet un esdeveniment Socket.IO cap a la room privada del destinatari (p. ex. `user:{id}`)
- **AND** la UI pot actualitzar el punt de notificació o la llista sense esperar un refresh manual
