## ADDED Requirements

### Requirement: Input de quantitat a la interfície de compra
El sistema SHALL mostrar un input de tipus number per seleccionar la quantitat d'entrades a la pàgina de detall de l'esdeveniment.

#### Scenario: Mostrar input de quantitat
- **WHEN** l'usuari accedeix a la pàgina de detall d'un esdeveniment del catàleg
- **THEN** es mostra un input number per seleccionar quantitat (1-6 entrades)

#### Scenario: Valor per defecte
- **WHEN** l'usuari arriba a la pàgina de compra
- **THEN** el valor per defecte de l'input és 1

### Requirement: Validació de quantitat
El sistema SHALL permetre seleccionar una quantitat mínima de 1 i màxima de 6 entrades.

#### Scenario: Quantitat mínima
- **WHEN** l'usuari intenta establir la quantitat a 0
- **THEN** el sistema no ho permet i mostra un error o automaticamente ajusta a 1

#### Scenario: Quantitat màxima
- **WHEN** l'usuari intenta establir la quantitat a 7
- **THEN** el sistema no ho permet i mostra un error o automàticament ajusta a 6

### Requirement: Càlcul dinàmic de preu total
El sistema SHALL actualitzar el preu total al frontend quan l'usuari canvia la quantitat d'entrades.

#### Scenario: Canviar quantitat
- **WHEN** l'usuari selecciona 3 entrades
- **THEN** el preu total mostrat és preu_unitari × 3

#### Scenario: Actualització en temps real
- **WHEN** l'usuari canvia la quantitat de 2 a 4
- **THEN** el preu total s'actualitza immediatament sense recarregar la pàgina

### Requirement: compres sense seat_id obligatori
El sistema SHALL permetre crear orders sense específicar seat_id quan s'utilitza el mode de compra per quantitat.

#### Scenario: Crear order amb quantitat
- **WHEN** l'usuari completa una compra de 4 entrades
- **THEN** es crea una order amb quantity=4 i seat_id=NULL

### Requirement: Generació de múltiples tickets
El sistema SHALL generar tants tickets com la quantitat seleccionada una vegada completada la compra.

#### Scenario: Compra de 4 entrades
- **WHEN** l'usuari compra 4 entrades d'un esdeveniment
- **THEN** es generen 4 tickets diferents vinculats a aquesta order

### Requirement: Accés als tickets des de la secció Tickets
El sistema SHALL permetre a l'usuari veure i accedir a cadascun dels tickets generats des de la secció Tickets.

#### Scenario: Veure tickets comprats
- **WHEN** l'usuari navega a la secció Tickets
- **THEN** veu tots els tickets generats, incloent els de compres per quantitat