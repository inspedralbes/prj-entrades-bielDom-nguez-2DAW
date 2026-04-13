# Consola d’administració: dashboard global, CRUD d’esdeveniments, monitoratge temps real, usuaris i informes

## Purpose

Definir una **suite de funcionalitat** per al **rol Administrador** (Spatie `admin`) que centralitzi la supervisió del negoci, el catàleg d’esdeveniments, el detall operatiu per esdeveniment (mapa i mètriques en viu) i la gestió d’usuaris i analítica. La visualització **en temps real** (sobretot mapa de seients i comptadors) ha de reutilitzar **Socket.IO** i **Redis** com a reflex del que ja valida **Laravel + PostgreSQL**, amb **Pinia** al **Nuxt** per a estat reactiu immediat quan arriben esdeveniments del socket.

**Identificador proposat (carpeta OpenSpec)**: `admin-platform-suite`  
**Estat**: Esborrany per a implementació / planificació  
**Idioma de requeriments**: català (alineat amb `Agents/` i especificacions existents del monorepo)

### Abast fora d’aquesta especificació (explícit)

- **Rol validador**: aquesta consola **no** inclou fluxos ni pantalles de **validador** dins del panell d’administració. El producte pot mantenir o retirar el rol `validator` al domini; en qualsevol cas **no** forma part dels requisits d’aquesta suite per al panell admin descrit aquí (vegeu **Requirement: Model de rols a la consola admin**).

---

## Arquitectura de temps real (transversal)

### Requirement: Coherència Socket.IO + Redis + Pinia

El sistema SHALL garantir que les actualitzacions visuals del **mapa de seients** i els **comptadors en viu** reflecteixin l’estat autoritatiu després de validació al **Laravel**; **Redis** gestiona holds i estat volàtil amb latència mínima; el **client Nuxt** actualitza **Pinia** en rebre esdeveniments Socket.IO perquè la UI de l’administrador percebi **temps real** (mateixa semàntica que la de l’usuari final al mapa).

#### Scenario: Actualització des de socket

- **WHEN** el socket-server retransmet un canvi d’estat de seient o de comptador derivat del backend
- **THEN** el client admin actualitza el store Pinia i la vista (mapa / números) sense requerir recàrrega completa de pàgina

#### Scenario: Font de veritat

- **WHEN** hi ha divergència entre estat mostrat i persistència
- **THEN** la **API Laravel** i **PostgreSQL** continuen sent la font de veritat; la UI ha de **resincronitzar** (p. ex. via fetch o esdeveniment de resync) segons patrons ja definits al projecte (FR-014 / constitució)

---

## A — Dashboard general (vista global)

### Requirement: Mètriques d’usuaris connectats (temps real)

El sistema SHALL mostrar un **comptador d’usuaris** actius a l’aplicació que **s’actualitzi en viu** (increment/decrement) mitjançant **Socket.IO** quan els usuaris entren o surten (definició operativa d’“online” a concretar al pla tècnic: sessions, heartbeat, namespace, etc.).

#### Scenario: Connexió d’usuari

- **WHEN** un client es considera “dins” de l’aplicació segons la regla acordada
- **THEN** el dashboard admin rep l’increment del comptador sense retard perceptible de segons

#### Scenario: Desconnexió

- **WHEN** l’usuari deixa d’estar comptabilitzat com a actiu
- **THEN** el comptador disminueix en conseqüència

### Requirement: Resum de vendes globals (dia natural)

El sistema SHALL mostrar **ingressos del dia** com a **suma** dels imports associats a **entrades venudes** amb data de venda el **dia natural corrent** (des de les **00:00** fins a la frontera temporal definida al backend, p. ex. TZ Europe/Madrid).

#### Scenario: Càlcul diari

- **WHEN** l’administrador obre el dashboard
- **THEN** veu el total d’ingressos del dia segons la regla de negoci (només compres confirmades, excloure cancel·lacions segons model de dades)

### Requirement: Estat de comandes en pagament pendent

El sistema SHALL mostrar **quants usuaris / comandes** estan en estat **`pending_payment`** (o equivalent al model de comandes del repositori) **ara mateix**.

#### Scenario: Actualització

- **WHEN** una comanda passa a o surt de `pending_payment`
- **THEN** el comptador del dashboard reflecteix el canvi (temps real o refresc periòdic definit al pla; preferible temps real si ja hi ha canal)

### Requirement: Alertes de sincronització Ticketmaster Discovery

El sistema SHALL mostrar **alertes** quan la importació d’esdeveniments des del **Discovery / Feed** (p. ex. Discovery API o pipeline documentat al projecte) **falla** o reporta condicions d’error recuperables (timeout, rate limit, errors de camp obligatori, etc.).

#### Scenario: Fallada d’import

- **WHEN** un job o acció d’import detecta error persistent segons llindar definit
- **THEN** l’administrador veu una alerta al dashboard amb text comprensible i, si escau, enllaç a detall o log segur

---

## B — CRUD de gestió d’esdeveniments

### Requirement: Importació massiva des de Ticketmaster Discovery

El sistema SHALL proporcionar un **cercador integrat** amb la **Ticketmaster Discovery API** (o client HTTP existent al backend) que permeti seleccionar un esdeveniment extern i accionar **Importar** per **copiar** a PostgreSQL: **nom**, **data**, **imatge**, **recinte** (i camps mínims addicionals definits al pla de dades).

#### Scenario: Importar èxit

- **WHEN** l’administrador tria un resultat de Discovery i confirma Importar
- **THEN** es crea o s’actualitza la fila local segons regles d’`external_tm_id` / política d’ingesta del repositori

#### Scenario: Error de import

- **WHEN** l’API externa o la validació local falla
- **THEN** es retorna error clar sense deixar dades inconsistents

### Requirement: Edició i creació manual

El sistema SHALL permetre **crear** esdeveniments **manuals** i **editar** els importats (camps permesos: dates, copy, visibilitat, vinculació a recinte, etc., segons taules `events` / `venues`).

### Requirement: Configuració de preus

El sistema SHALL permetre que l’administrador defineixi el **preu general** de les entrades per esdeveniment (alineat amb el model de preu únic o per zona segons el que fixi el pla tècnic i `events.price` o equivalent).

### Requirement: Ocultació lògica (“soft hide”)

El sistema SHALL implementar **eliminació lògica** d’esdeveniments: en “eliminar”, l’esdeveniment **deixa de ser visible** per als usuaris finals (`hidden_at` o camp equivalent), però **es conserva** a la base de dades per a **històric administratiu**.

#### Scenario: Catàleg públic

- **WHEN** `hidden_at` (o equivalent) no és nul
- **THEN** l’esdeveniment **no** apareix als llistats públics ni al flux de compra

---

## C — Monitoratge i detall de l’esdeveniment (temps real)

### Requirement: Mini-mapa interactiu d’estat (mateix mapa que l’usuari)

El sistema SHALL mostrar un **mapa visual de seients** per a un esdeveniment seleccionat, **carregant el mateix model de mapa** que la vista d’usuari, amb colors actualitzats en viu via **Socket.IO**:

- **Verd**: lliure  
- **Groc**: seleccionat / reservat (hold actiu) per algun usuari en aquest moment  
- **Vermell**: venut  

#### Scenario: Paritat amb usuari

- **WHEN** l’administrador observa un seient
- **THEN** l’estat visual coincideix amb el que veu un usuari al mateix esdeveniment (mateixa font de veritat post-validació Laravel)

### Requirement: Comptadors de seients en viu

El sistema SHALL mostrar:

- **Entrades venudes**: total de tiquets en estat **venut** (o equivalent al domini)  
- **Entrades restants**: **aforament restant** calculat respecte al **aforament total** configurat per a l’esdeveniment  
- **Reserves actives (holds)**: llista de seients bloquejats actualment a **Redis** amb **compte enrere** visible (coherent amb el mapa)

### Requirement: Finances de l’esdeveniment

El sistema SHALL mostrar la **recaptació total real** de l’esdeveniment **sense desglossament per categories** (import únic agregat).

---

## D — CRUD de gestió d’usuaris i tiquets

### Requirement: Gestió d’usuaris (creació i eliminació)

El sistema SHALL permetre a l’administrador:

- **Crear** usuaris nous (dades demogràfiques / credencials segons política de seguretat) i **assignar rols** (dins del conjunt permès al producte; vegeu requisit de rol validador)  
- **Eliminar definitivament** usuaris de la base de dades quan la política de negoci ho permeti (FK, anonimització o cascade definits al pla de dades)

#### Scenario: Assignació de rols

- **WHEN** l’administrador assigna rols en crear/editar
- **THEN** els rols es reflecteixen a Spatie (`model_has_roles`) amb `guard` correcte

### Requirement: Historial de transaccions per usuari

El sistema SHALL permetre consultar **tots** els **pedidos** i **tiquets** d’un usuari seleccionat, incloent estat de **validació** i **transferències** a amistats quan el model de dades ho suporti.

---

## E — Informes i analítica (pàgina d’informes)

### Requirement: Evolució temporal de vendes

El sistema SHALL oferir un **gràfic de línies** de l’evolució de vendes amb capacitat de veure **pics de demanda** i **franges horàries** de major activitat de compra (agregació diària/horària segons el pla).

### Requirement: Percentatge d’ocupació

El sistema SHALL mostrar un **gràfic circular** (o donut) que compari **seients venuts** respecte a l’**aforament total** configurat per a l’esdeveniment seleccionat.

---

## Model de rols a la consola admin

### Requirement: Sense validador al panell admin

La consola descrita en aquest document SHALL estar **restringida al rol `admin`** (middleware JWT + Spatie). **No** s’han d’incloure en aquest abast fluxos de **validador** (escaneig a porta) ni navegació específica de validador dins del **layout** d’administració. Si el repositori encara exposa rutes `/validator/*`, la decisió de mantenir-les o unificar-les queda com a **tasques d’implementació** separades; aquesta especificació **no** les requereix.

#### Scenario: Accés denegat

- **WHEN** un usuari sense rol `admin` intenta accedir a les rutes del panell
- **THEN** rep **403** a API i redirecció o bloqueig a Nuxt segons middleware existent

---

## Criteris de compliment (resum)

| Àrea | Ha de complir |
|------|----------------|
| Dashboard | Usuaris en viu (socket), ingressos dia, `pending_payment`, alertes sync TM |
| Esdeveniments | Cerca Discovery + import, CRUD manual, preu, soft-hide |
| Detall esdeveniment | Mapa = mateix que usuari, colors temps real, comptadors, holds Redis, recaptació |
| Usuaris / tiquets | CRUD usuari + històric complet de compres |
| Informes | Línies (evolució) + circular (ocupació) |
| Temps real | Socket.IO + Redis + Pinia alineats amb Laravel |
| Rols | Només `admin` al panell; sense validador dins d’aquest abast |

---

## Dependencies & assumptions

- **Stack** del monorepo: Laravel 11 (API), Nuxt 3 (JS), Socket.IO, Redis, PostgreSQL, Spatie Permission (vegeu `.cursor/rules/agents-stack.mdc` i `specs/001-seat-map-entry-validation/` on escaigui).
- Els **noms d’esdeveniments Socket**, **namespaces** i **claus Redis** han de **reutilitzar o estendre** els existents al `socket-server` i al frontend per evitar duplicar lògica de mapa.
- La **definició exacta** de “usuari connectat” per al comptador global requereix un **petit apartat tècnic** (proposal/design) per evitar comptar doble finestra o sessions fantasma.

---

## Obertes (per a `plan.md` / `design.md`)

1. TZ i frontera del “dia natural” per a ingressos.  
2. Model exacte d’estats de comanda (`pending_payment`, venut, etc.) i joins per a informes.  
3. Esdeveniments Socket per al comptador global d’usuaris (nou canal vs reutilització).  
4. Estratègia de **soft delete** vs `hidden_at` per esdeveniments (camp ja existent o migració).  
5. Permisos finos: només `admin` o sub-rols dins admin (fora d’abast si no es demana).
