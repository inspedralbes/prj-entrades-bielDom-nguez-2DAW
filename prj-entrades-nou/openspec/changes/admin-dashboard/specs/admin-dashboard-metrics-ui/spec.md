# Delta — `admin-dashboard-metrics-ui`



Especificació del panell `admin/index` i complement del dashboard global.



## ADDED Requirements



### Requirement: Targetes d’informació al dashboard



El sistema SHALL mostrar, com a mínim, les dades següents de forma llegible (targetes o blocs equivalents), **sense** JSON brut ni depuració com a element principal:



- **Ingressos del dia** (import amb moneda).

- **Comandes pagades el mateix dia natural** (recompte enter, mateixa finestra temporal que els ingressos del dia segons el disseny).

- **Usuaris connectats actualment** (recompte en temps real alineat amb presència).

- **Tiquets venuts històricament** (recompte total acumulat segons regles de negoci al disseny).



#### Scenario: Càrrega inicial



- **WHEN** un administrador obre el dashboard

- **THEN** els valors es omplen des del resum HTTP (`GET /api/admin/summary` o successor) i es mostren formats



### Requirement: Actualització en viu compartida (KPIs numèrics)



El sistema SHALL **actualitzar sense recarregar la pàgina** els KPIs numèrics coherents amb el resum quan arribi `admin:metrics` (snapshot) de manera que **tots els admins** al room del panell vegin els mateixos valors alhora.



#### Scenario: Canvi de mètriques



- **WHEN** es publica un nou snapshot complet

- **THEN** les targetes numèriques es refresquen sense `location.reload`



### Requirement: Registre d’auditoria `admin_logs`



El sistema SHALL persistir les accions d’administrador que **modifiquin dades** a la base de dades (creació, actualització, eliminació rellevant segons l’abast definit al pla tècnic) a la taula **`admin_logs`**, amb identificació de l’administrador, tipus d’acció, entitat afectada i marca temporal.



#### Scenario: Acció registrada



- **WHEN** un usuari amb rol `admin` completa una operació que alteri persistència dins l’abast auditat

- **THEN** es crea una fila a `admin_logs` consultable des del backend



### Requirement: Vista prèvia i llista paginada de logs (només REST, format llista)



El sistema SHALL mostrar al dashboard **només els 5 darrers** registres de `admin_logs` (ordre cronològic descendent) en una **llista** (un element per registre), **sense usar GraphQL** per obtenir aquests registres.



Cada element visible SHALL incloure com a mínim: **nom de l’administrador**, **dia**, **hora**, **adreça IP** i **descripció de l’acció** (el que s’ha fet).



#### Scenario: Vista compacta



- **WHEN** l’administrador visualitza el dashboard

- **THEN** veu fins a 5 entrades en format llista amb nom, dia, hora, IP i acció



#### Scenario: Obrir el llistat complet



- **WHEN** l’administrador fa clic al contenidor del bloc de logs (o control equivalent explícit)

- **THEN** accedeix a una vista amb **llista** de tots els registres (mateix format de fila), **paginació de 10 en 10**, obtinguda via **REST**



#### Scenario: Paginació



- **WHEN** hi ha més de 10 registres

- **THEN** l’usuari pot navegar per pàgines sense carregar tots els registres en una sola resposta



#### Scenario: Sense GraphQL per a logs



- **WHEN** el client necessita logs (prèvia o llistat complet)

- **THEN** utilitza **`GET /api/admin/summary`** (camp de prèvia) i/o **`GET /api/admin/logs`** i **no** envia consultes GraphQL per llegir `admin_logs`



### Requirement: API REST per a logs d’administració



El sistema SHALL exposar **`GET /api/admin/logs`** accessible **només** a rol `admin`, amb **paginació** (`page`, `per_page` per defecte 10). Cada ítem del recurs SHALL exposar dades suficients per a la UI: **nom de l’admin**, **data i hora** (o `created_at` ISO processable al client), **`ip_address`**, i text de **què s’ha fet**.



El sistema SHALL **NOT** exposar la lectura de `admin_logs` mitjançant **GraphQL** en aquest change.



#### Scenario: Accés vàlid



- **WHEN** un administrador sol·licita una pàgina vàlida

- **THEN** rep fins a 10 registres amb els camps esmentats i metadades de paginació



#### Scenario: Accés denegat



- **WHEN** un usuari no administrador crida l’endpoint

- **THEN** rep **403** (o equivalent del projecte)



### Requirement: Dades dels gràfics via GraphQL

Aquest endpoint GraphQL és **només** per a les **sèries dels dos gràfics**; **no** s’hi han d’exposar ni consultar els `admin_logs`.

El sistema SHALL obtenir les sèries dels **dos gràfics** del dashboard exclusivament mitjançant **`POST /api/graphql`**:



- **Ingressos per dia** — **últims 30 dies** — per al **gràfic de línies**.

- **Comandes pagades per dia** — **últims 30 dies** — per al **gràfic de barres** (mateix nombre de dies que el gràfic d’ingressos, eix temporal alineat).



#### Scenario: Consulta vàlida com a administrador



- **WHEN** un administrador executa les consultes GraphQL previstes

- **THEN** rep punts diaris coherents amb la TZ de negoci i pot renderitzar ambdós gràfics



#### Scenario: Accés denegat



- **WHEN** un usuari sense rol `admin` executa les consultes

- **THEN** no rep les sèries



### Requirement: Presentació en text llegible (sense JSON a pantalla)



El sistema SHALL mostrar tota la informació del panell d’administració (KPIs, alertes de sincronització, entrades de **admin_logs**, títols i llegenda dels gràfics) com a **text normal** entenedor: etiquetes, frases, imports i dates **formatejats**, de manera que els administradors **no** hagin de llegir estructures tipus JSON ni cadenes serialitzades per entendre el contingut.



El sistema SHALL **NOT** renderitzar com a contingut principal de lectura el **JSON** dels objectes de resposta (logs, resum, alertes) ni utilitzar patrons tipus serialització crua del payload a la vista.



#### Scenario: Logs en text pla



- **WHEN** es mostren les entrades d’auditoria (prèvia o llistat complet)

- **THEN** cada ítem es veu com a línies o blocs de **text** (nom, data/hora, IP, descripció), **sense** que el cos de l’entrada sigui un blob JSON visible



#### Scenario: KPIs i alertes



- **WHEN** l’administrador llegeix les targetes i les alertes TM

- **THEN** els valors i missatges es mostren com a text i números formats, no com a estructura JSON imprimible



### Requirement: Prohibició de superfície tipus depuració



El sistema SHALL **NOT** mostrar com a UX principal el JSON del resum HTTP ni el payload socket en brut (p. ex. `<pre>` de producció).



#### Scenario: Ús normal



- **WHEN** l’administrador usa el dashboard

- **THEN** no depèn de sortides crues per llegir KPIs, gràfics ni logs



### Requirement: Alertes de sincronització Ticketmaster



El sistema SHALL mostrar les alertes de sync (`sync_alerts` o equivalent) amb jerarquia visual clara quan n’hi hagi.



#### Scenario: Alertes presents



- **WHEN** el resum inclou alertes

- **THEN** són identificables sense eines de desenvolupador



### Requirement: Snapshot socket complet



El sistema SHALL emetre snapshots `admin:metrics` que permetin actualitzar els **KPIs numèrics** del panell de forma equivalent al resum HTTP quan escaigui (presència, canvis de comandes, etc.). Els **logs** poden actualitzar-se per nova lectura REST o snapshot addicional segons el disseny, sense trencar el requisit de vista prèvia de 5 i llistat paginat.



#### Scenario: Presència



- **WHEN** canvia el recompte d’usuaris en línia

- **THEN** la targeta corresponent s’actualitza en viu per a tots els admins al panell


