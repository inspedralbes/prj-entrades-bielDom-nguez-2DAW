# Delta — `admin-audit-logs`

Requisits de **persistència i exposició** de l’auditoria (`admin_logs`). La **lectura** es fa **només per REST** (llistes al dashboard); **no** hi ha queries GraphQL sobre aquesta taula.

## ADDED Requirements

### Requirement: Esquema de taula `admin_logs`

El sistema SHALL mantenir una taula **`admin_logs`** amb, com a mínim: identificador, **usuari administrador** (FK), **acció** (codi o etiqueta), **tipus d’entitat**, **identificador d’entitat** (nullable), **resum textual** de l’acció escrit en **llenguatge natural** (frase o text pla que es mostrarà tal qual a la UI), **sense** emmagatzemar com a contingut de mostra un objecte JSON serialitzat només per ser “dumpat” a pantalla; **`ip_address`**; **created_at**.

#### Scenario: Resum llegible

- **WHEN** es persisteix un log

- **THEN** el camp de resum és llegible directament per una persona (p. ex. “S’ha actualitzat el preu de l’esdeveniment X”), no un JSON opac com a únic text visible

#### Scenario: Migració aplicada

- **WHEN** es desplega el canvi de base de dades
- **THEN** la taula existeix amb `ip_address` i índexs per consultes recents i paginades

### Requirement: Cobertura d’escriptura

El sistema SHALL registrar accions quan un **admin** executi operacions que **modifiquin** registres rellevants, desant la **IP** obtinguda de la petició HTTP (tenint en compte proxies si el projecte ho configura).

#### Scenario: Operació coberta

- **WHEN** es completa una operació CUD coberta
- **THEN** existeix una fila nova a `admin_logs` amb IP i resum coherents

### Requirement: Exposició només REST

El sistema SHALL exposar els logs d’administració mitjançant **`GET /api/admin/logs`** (i el camp de prèvia al summary si escau), **no** mitjançant l’esquema GraphQL.

#### Scenario: Sense lectura GraphQL de logs

- **WHEN** un client necessita el llistat de logs
- **THEN** utilitza endpoints REST i no existeix query GraphQL de lectura sobre `admin_logs` en aquest change
