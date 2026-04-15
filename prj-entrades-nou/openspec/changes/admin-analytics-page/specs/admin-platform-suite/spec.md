# Delta — `admin-platform-suite`

Actualització de la secció **E** i dels criteris de compliment per substituir **Informes** per **Analítiques** (capability detallada a `admin-analytics`).

## REMOVED Requirements

### Requirement: Evolució temporal de vendes

El sistema SHALL oferir un **gràfic de línies** de l’evolució de vendes amb capacitat de veure **pics de demanda** i **franges horàries** de major activitat de compra (agregació diària/horària segons el pla).

**Reason**: La pàgina d’informes és substituïda per **Analítiques** amb altres mètriques i un filtre global; el gràfic de línies deixa de ser el requisit central d’aquesta àrea.

**Migration**: Implementar la capability **`admin-analytics`** (`specs/admin-analytics/spec.md` d’aquest change); eliminar la dependència de la UI respecte d’aquest gràfic com a requisit de suite.

### Requirement: Percentatge d’ocupació

El sistema SHALL mostrar un **gràfic circular** (o donut) que compari **seients venuts** respecte a l’**aforament total** configurat per a l’esdeveniment seleccionat.

**Reason**: L’ocupació rellevant per a la suite passa a ser **agregada per categoria** a Analítiques, no un donut per esdeveniment seleccionat.

**Migration**: Cobrir ocupació per categoria i vistes barres/llista segons **`admin-analytics`**.

## ADDED Requirements

### Requirement: Secció E — Analítiques (substitució d’Informes)

La consola d’administració SHALL incloure una àrea **Analítiques** que substitueix el comportament descrit anteriorment per a **Informes** (línies i donut per esdeveniment). Els requisits funcionals detallats (filtres, totals, rendiment per esdeveniment, ocupació per categoria, API REST, seguretat) SHALL ser els de la capability **`admin-analytics`**.

#### Scenario: Punt de menú únic

- **WHEN** l’administrador busca l’analítica de vendes al panell
- **THEN** accedeix a **Analítiques** i no a una vista paral·lela «Informes» amb els antics requisits obligatoris

### Requirement: Criteri de compliment — fila Analítiques

El resum de **criteris de compliment** del document principal SHALL considerar complerta l’àrea d’analítica quan es compleix **`admin-analytics`**, descrita com a **Analítiques** amb filtre temporal global, total guanyat, rendiment per esdeveniment i ocupació per categoria (presentacions barres i llista), en substitució de la fila «Informes | Línies (evolució) + circular (ocupació)».

#### Scenario: Revisió de cobertura

- **WHEN** es valida el compliment de la suite admin després d’aquest canvi
- **THEN** l’avaluació de l’àrea E es fa contra **Analítiques** i la spec **`admin-analytics`**, no contra els requisits REMOVED anteriors
