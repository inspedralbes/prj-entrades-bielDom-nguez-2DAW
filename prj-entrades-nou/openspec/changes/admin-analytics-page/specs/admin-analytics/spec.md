# Capability — `admin-analytics`

Analítiques al panell d’administració: substitució de la vista Informes, filtre temporal global, ingressos totals, rendiment per esdeveniment i ocupació agregada per categoria.

**Idioma de requeriments**: català.

## ADDED Requirements

### Requirement: Substituir Informes per Analítiques (navegació i ruta)

El sistema SHALL exposar una pàgina **Analítiques** al panell d’administració que **substitueixi** la vista anteriorment denominada **Informes** (sense mantenir dues entrades de menú equivalents per al mateix propòsit). La ruta canònica del client SHALL ser **`/admin/analytics`**. El sistema SHOULD redirigir **`/admin/reports`** cap a **`/admin/analytics`** per a enllaços antics.

#### Scenario: Enllaç del menú lateral

- **WHEN** un administrador visualitza el layout del panell admin
- **THEN** l’entrada de menú mostra el text **Analítiques** (o equivalent acordat al producte) i enllaça a **`/admin/analytics`**

#### Scenario: Ruta antiga

- **WHEN** un usuari amb permís accedeix a **`/admin/reports`**
- **THEN** el client el porta a **`/admin/analytics`** sense error visible (redirecció o navegació equivalent)

### Requirement: Filtre global de període

El sistema SHALL permetre seleccionar un **període d’anàlisi** que aplica a **totes** les mètriques de la pàgina: presets **últims 7 dies**, **últims 30 dies** i mode **personalitzat** amb **`date_from`** i **`date_to`** (incloses) en format data natural. El canvi de període SHALL tornar a carregar o recalcular les dades mostrades.

#### Scenario: Preset 7 dies

- **WHEN** l’administrador tria el preset de 7 dies
- **THEN** el període actiu cobreix els 7 dies naturals finals acordats al disseny (incloent el dia actual si així s’ha definit)

#### Scenario: Rang personalitzat

- **WHEN** l’administrador introdueix un interval de dates vàlid
- **THEN** totes les seccions de la pàgina usen el mateix interval per a les consultes

### Requirement: Total guanyat al període

El sistema SHALL mostrar el **total d’ingressos** (en moneda del negoci) resultant de **comandes pagades** el **pagament de les quals** caigui dins del període seleccionat, d’acord amb la regla temporal definida al servei (alineada amb l’informe de vendes existent: mateixa semàntica que les agregacions d’admin sobre comandes **paid**).

#### Scenario: Resum coherent amb l’API

- **WHEN** l’administrador obre Analítiques amb un període donat
- **THEN** el total mostrat coincideix amb el camp de resum retornat per **`GET /api/admin/analytics/summary`** (o successor documentat) per aquell període

### Requirement: Rendiment per esdeveniment

El sistema SHALL mostrar, per a cada esdeveniment amb activitat al període (o segons criteri d’inclusió definit al disseny), com a mínim: **identificador/nom de l’esdeveniment**, **ingressos totals** al període i **venda mitjana per dia** calculada com a ingressos del període dividits pel **nombre de dies naturals** del període (inclosos), amb un mínim d’**1** dia per evitar divisió per zero.

#### Scenario: Dades per esdeveniment

- **WHEN** el backend retorna el llistat d’esdeveniments per al període
- **THEN** cada ítem inclou ingressos i mitjana diària coherents amb **`GET /api/admin/analytics/events`**

### Requirement: Ocupació mitjana per categoria (dades)

El sistema SHALL calcular, per a cada **categoria** d’esdeveniment (`Event.category`, tractant valor buit com a categoria distingible per a la UI), l’**ocupació global** com a **seients venuts** respecte a la **capacitat total** (recompte de seients) dels esdeveniments d’aquesta categoria, i SHALL exposar **percentatge** arrodonit de forma estable. La definició de «venut» SHALL ser la mateixa que la de l’informe d’ocupació per esdeveniment (tiquets venuts amb comanda pagada).

#### Scenario: Agrupació per categoria

- **WHEN** existeixen diversos esdeveniments de la mateixa categoria
- **THEN** el percentatge reflecteix **venuts agregats / capacitat agregada**, no una mitjana simple d’percentatges per esdeveniment

### Requirement: Ocupació per categoria (presentació barres i llista)

El sistema SHALL mostrar les mètriques d’ocupació per categoria en **dues representacions** alternables: **barres de progrés** amb el percentatge visible i **vista llista** (elements apilats verticalment) amb el mateix percentatge llegible.

#### Scenario: Commutació de vista

- **WHEN** l’administrador canvia entre mode barres i mode llista
- **THEN** les dades mostrades són les mateixes; només canvia la presentació

### Requirement: API d’analítiques restringida a administradors

Les rutes **`/api/admin/analytics/*`** SHALL exigir autenticació JWT i rol **`admin`**. Un client no autoritzat SHALL rebre **403** (o **401** si sense token, d’acord amb el patró del projecte).

#### Scenario: Accés denegat

- **WHEN** un usuari sense rol `admin` crida un endpoint d’analítiques
- **THEN** la resposta és d’error d’autorització sense cos de dades d’analítica

### Requirement: Validació del rang de dates

El sistema SHALL rebutjar rangs de dates invàlids (data inicial posterior a la final) i SHALL limitar l’amplària màxima del rang (p. ex. **93 dies** com als informes actuals o el valor definit al disseny) amb missatge d’error clar.

#### Scenario: Rang massa ampli

- **WHEN** el client envia un rang que supera el màxim permès
- **THEN** l’API respon amb error de validació sense executar agregacions completes
