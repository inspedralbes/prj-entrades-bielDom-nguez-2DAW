## ADDED Requirements

### Requirement: FR-040 Guardar amb persistència i redirecció per usuaris anònims

El sistema SHALL persistir l'esdeveniment a la taula `saved_events` quan l'usuari autenticat activa Guardar.

#### Scenario: Usuari autenticat guarda
- **WHEN** l'usuari autenticat fa clic a Guardar al detall (D)
- **THEN** el sistema crea o manté el registre a `saved_events` per al parell (usuari, esdeveniment)

#### Scenario: Usuari anònim intenta guardar
- **WHEN** l'usuari no autenticat fa clic a Guardar
- **THEN** el sistema redirigeix a `/login` o al flux de registre equivalent
- **AND** el sistema conserva la intenció de completar el guardat de l'esdeveniment original

#### Scenario: Retorn després d'autenticació
- **WHEN** l'usuari completa login o registre amb èxit
- **THEN** el sistema el retorna automàticament a la pantalla de detall de l'esdeveniment original (D)
- **AND** el sistema completa la persistència a `saved_events` sense obligar l'usuari a tornar a cercar l'esdeveniment (US3.1)

### Requirement: FR-041 Compartir amb cercador d'amics

El sistema SHALL oferir una acció Compartir des del detall (D) que obri una vista (modal o pàgina) amb camp de cerca i indicador visual de cerca (p. ex. lupa).

#### Scenario: Obrir compartició
- **WHEN** l'usuari fa clic a Compartir
- **THEN** s'obre la vista de compartició amb un input de text per cercar

#### Scenario: Filtrat en temps real
- **WHEN** l'usuari escriu al cercador
- **THEN** el sistema filtra la llista d'amics corresponent a invitacions acceptades a `friend_invites` (només relacions vàlides d'amistat)
- **AND** la llista s'actualitza de forma immediata mentre l'usuari escriu (debounce permès per rendiment)

#### Scenario: Amic no autenticat per compartir esdeveniment
- **WHEN** l'usuari no està autenticat i intenta compartir
- **THEN** el sistema aplica la mateixa política d'autenticació que la resta de funcions socials (redirecció o bloqueig explícit amb missatge)

### Requirement: FR-042 Copiar enllaç al porta-retalls

El sistema SHALL oferir un botó per copiar l'URL pública de l'esdeveniment al porta-retalls del dispositiu.

#### Scenario: Còpia correcta
- **WHEN** l'usuari fa clic al botó de copiar enllaç
- **THEN** el sistema utilitza l'API del navegador (`navigator.clipboard` o equivalent segur) per desar la URL del detall de l'esdeveniment
- **AND** el sistema mostra confirmació visual accessible (p. ex. toast o text d'estat)

#### Scenario: Error de còpia
- **WHEN** la còpia falla (permisos, context no segur)
- **THEN** el sistema mostra un missatge d'error o una alternativa (p. ex. selecció manual) sense trencar la vista
