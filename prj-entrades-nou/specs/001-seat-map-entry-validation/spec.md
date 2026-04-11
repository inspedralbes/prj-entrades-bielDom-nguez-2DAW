# Especificació de funcionalitat: Mapa de seients, bloquejos, entrades segures i validació

**Branca de funcionalitat (identificador Speckit)**: `001-seat-map-entry-validation`  
**Branca base del repositori (Git Flow)**: `dev` — totes les branques de treball es creen des de `dev`; integració via PR cap a `dev` (vegeu [tasks.md](./tasks.md)).  
**Creat**: 2026-04-11  
**Estat**: Esborrany  
**Entrada**: Mapa de Seients: integració de l’API Top Picks (Ticketmaster) per a imatge (`snapshotImageUrl`) i metadades de zones. Concurrència: bloqueig atòmic de fins a 6 seients (N=6) amb temps de reserva **per esdeveniment** dins del rang 3–5 minuts (vegeu clarificacions) via magatzem de bloquejos ràpid. Ticketing segur: credencials d’entrada signades pel servidor i codi escanejable en format vectorial. Validació: escaneig des de mòbil que marca l’entrada amb una «X» visual a l’app de l’usuari un cop validada pel servidor (Historial / pantalla 22).

### Context del repositori (estat actual — dades i eines)

- **Esquema PostgreSQL**: la font de veritat és SQL al monorepo: **`database/init.sql`** (DDL, PostGIS, domini, Spatie) i **`database/inserts.sql`** (dades inicials de desenvolupament). **No** s’usen migracions Laravel per definir taules; qualsevol canvi d’esquema ha anar reflectit en aquests fitxers i al [data-model.md](./data-model.md). Els tests PHPUnit carreguen **`database/testing/schema.sqlite.sql`** (paritat SQLite).
- **Docker dev** (`docker/dev/docker-compose.yml`): Postgres amb healthcheck; l’API Laravel ha d’usar **`DB_HOST=postgres`** quan corre dins el compose (i `php artisan config:clear` si s’havia fet `config:cache` amb un altre host).
- **Adminer** (**4.8.1**): servei web lleuger d’administració de bases de dades inclòs al compose (p. ex. port **8080** → sistema PostgreSQL, servidor **`postgres`**, credencials alineades amb el servei `postgres`). Per a futures funcionalitats: considerar Adminer com a eina de suport a desenvolupament, no com a dependència de runtime de l’app.

## Clarifications

### Session 2026-04-11

- Q: Qui pot disparar una validació d’entrada vàlida (registre d’ús al recinte)? → A: Només personal de porta o dispositius amb rol de validador (Opció A).
- Q: Model de credencials per compra de diversos seients? → A: Una credencial escanejable independent per cada seient (fins a sis per compra) (Opció A).
- Q: Validació sense connexió al servidor? → A: L’estat «usat» només es fixa amb connexió exitosa; sense xarxa no hi ha registre d’ús vàlid (Opció A).
- Q: Què fixa la durada concreta del hold dins del rang 3–5 minuts? → A: Sobretot **configuració per esdeveniment** (o política d’organitzador), estable durant la venda d’aquest esdeveniment (Opció A).
- Q: Pròrroga del hold abans d’expirar? → A: **Sense pròrroga**; en expirar cal tornar a seleccionar seients si encara hi ha disponibilitat (Opció A). *(Aquesta resposta queda **substituïda** per la regla definitiva de **Session 2026-04-15** — pròrroga única +2 min des del checkout.)*

### Session 2026-04-12 (autenticació i marge de login al checkout)

- **Esmena a la regla de pròrroga**: quan l’usuari **inicia login o registre des del checkout** (passarel·la de pagament), el servidor **atorga una pròrroga única** de **+2 minuts** al TTL del hold a **Redis** (en la mateixa finestra global màxima de producte), per evitar perdre els seients mentre completa credencials. No s’aplica pròrroga per altres motius (vegeu **FR-003**).
- **Mapa i hold sense sessió iniciada**: es pot **veure el mapa** i **seleccionar seients** (hold inicial) **sense** estar autenticat; és **obligatori** autenticar-se (login o registre) per **procedir al pagament** i finalitzar la compra.
- **JWT d’API**: **Laravel** és l’**únic emissor** de tokens JWT de sessió (registre, login, validació de peticions protegides); **Nuxt + Pinia** emmagatzemen el token i el perfil; **middleware** Nuxt protegeix rutes de compra (checkout), **Tickets** i **perfil**.
- **Socket.IO**: vegeu **FR-014** (arquitectura **híbrida** de canals: lectura pública de l’estat dels seients, JWT per a escriptures).

### Session 2026-04-13 (mapa de pantalles i navegació per rol)

- **Usuari normal (Comprador)**: navegació principal amb **Header** (desktop) i **Footer fix** (mòbil); experiència de descobriment **personalitzada** amb **Gemini** i **geolocalització** (PostGIS / Google Maps segons pantalla).
- **Administrador**: navegació amb **Sidebar** lateral (monitoratge i gestió).
- El detall de pantalles (A–J i A–E) queda **normatiu** per al disseny Nuxt; vegeu secció **Interfície per rol (mapa de pantalles)**.
- **Hold dinàmic en el flux de pagament**: el temps de reserva pot **augmentar** només d’acord amb les regles ja definides (**FR-003**: pròrroga **+2 min** en iniciar login des del checkout; **Pending Payment** manté el hold dins del TTL); la UI ha de reflectir el **temporitzador** i les **actualitzacions en temps real** dels seients (color) via **Socket.IO**.

### Session 2026-04-14 (tres rols, JWT, Pinia i navegació de 6 seccions)

- **Model de rols (3)**: **(1) Usuari** — agrupa **Comprador** i **Assistent** (mateix conjunt de permisos d’app de consum: JWT per a compra i consulta privada); **(2) Validador** — rol operatiu, interfície mòbil simplificada; **(3) Administrador** — panell global (Sidebar), disseny de plànols (**JSONB**), imports TM, dashboard temps real.
- **JWT (Laravel)**: emissió i validació **només** al **Laravel 11**; tota petició API protegida ha de portar **Bearer JWT** vàlid excepte endpoints públics explícits (p. ex. auth, catàleg genèric si escau). **Consulta privada** (tickets, favorits, perfil, social autenticat, historial de compres) **sempre** amb JWT.
- **Pinia (Nuxt 3)**: magatzem global per **sessió** (persistència del JWT d’usuari) i per **estat de compra** (seients seleccionats / hold abans de confirmació), alineat amb [tasks.md](./tasks.md) (stores `auth`, `hold`).
- **Hold línia base**: **4 minuts** a **Redis** (dins del rang 3–5 min per esdeveniment); si l’usuari **no està autenticat** en passar al **checkout** i inicia **login o registre**, s’aplica **+2 minuts** al TTL **una vegada** (**FR-003**).
- **Navegació consumidor**: **6 seccions** fixes al **Footer** (mòbil) i al **Header** (desktop), **sense subdividir-les en més ítems principals**: **Home**, **Buscador + Mapa** (una entrada de menú que cobreix llista i mapa interactiu), **Tickets**, **Guardats**, **Social**, **Perfil**.
- **Mapa interactiu**: **Google Maps** amb marcadors alimentats per **PostGIS**; enllaç extern a navegació (p. ex. «Com arribar») només després de **modal de confirmació**.

### Session 2026-04-15 (Socket.IO híbrid, Gemini, temps de reserva definitiu)

- **I1 — Socket.IO híbrid**: El mapa de seients és el nucli del **temps real**; **tots** els clients han de rebre les actualitzacions d’**estat visual** dels seients (colors, disponibilitat). **Arquitectura**: **canal / namespace públic** (o subscripció per `eventId`) per a la **lectura** dels esdeveniments Socket que reflecteixen l’estat dels seients **sense** exigir JWT; les **accions d’escriptura** (crear o alliberar **hold**, confirmar **compra**, operacions que alteren disponibilitat autoritària) exigeixen **JWT** validat via **API Laravel** (i, on escaigui, connexions o rooms **autenticades** per a notificacions privades com `ticket:validated`). El **socket-server** retransmet broadcasts derivats de l’estat ja validat al backend (constitució III).
- **I3 — Temps de reserva (regla única)**: Bloqueig **inicial** a **Redis**: **4 minuts** (240 s), línia base de producte. Quan el **servidor detecta** que un **usuari anònim** ha **iniciat el flux de login o registre des del checkout**, s’atorga **automàticament una pròrroga única de +2 minuts** al TTL (**+120 s**, una vegada per hold); el **màxim efectiu** en aquesta finestra és **6 minuts** (360 s) abans d’expirar. Tot dins del rang configurable per esdeveniment on el límit superior del producte ho permeti.
- **C1 — Gemini (recomanació, mai compra)**: **Gemini** actua només com a **motor de recomanació** al backend: retorna **JSON** amb **IDs d’esdeveniments** (o referències opaques) per al feed «Triats per a tu». **Mai** executa compres ni modifica disponibilitat. El **flux de compra** passa **sempre** per l’**API Laravel**, que consulta **PostgreSQL** com a **única font de veritat** per a disponibilitat i preus.
- **Terminologia (obligatòria en documentació i codi visible)**: **Usuari** = comprador/assistent final (app de consum); **Validador** = rol operatiu d’escaneig a porta; **Administrador** = gestió global i de negoci (panell). No usar sinònims inconsistents en documents Speckit.

### Actualització 2026-04-11 (resolució gaps checklist)

- **Catàleg Top Picks indisponible**: fallback automàtic a consulta **PostgreSQL** (ordenació per **proximitat PostGIS** o **rellevància / ordre manual** al model intern), sense mapes ni zones inventades. *(L’API **Gemini** no substitueix el catàleg de seients; roman només per usos assistencials fora d’aquest flux, vege constitució.)*
- **Esgotament de seients durant la compra**: el servidor, com a font de veritat, **denega** la transacció final i retorna error **«Seient ja no disponible»**; s’alliberen holds residuals associats.
- **Concurrència estricta**: transaccions amb **bloqueig de fila** a PostgreSQL; el primer usuari rep confirmació del bloqueig; el segon rep error **immediat** via **Socket.IO** amb el missatge **«Aquest seient acaba de ser seleccionat per un altre usuari»**; estat PostgreSQL i Redis sincronitzats en **mil·lisegons**.
- **Flux de pagament**: estat intermedi **«Pending Payment»**; el hold a **Redis** es manté mentre dura la transacció a la **passarel·la de pagament externa**; si el pagament **no** es confirma abans del **TTL del hold**, els seients s’alliberen automàticament.
- **Mètriques acordades**: durada de hold **oficial de producte 4 minuts** (dins del rang 3–5 min); TTL del JWT del QR **15 minuts** des de la generació (o vinculat a la sessió activa de l’usuari); tolerància màxima de **2 segons** de desfasament entre compte enrere del client i del servidor abans de forçar **resincronització via Socket.IO**.
- **Definicions de rols**: vege secció **Definicions de rols** (Comprador, Assistent, Validador).
- **Frau / captura**: el QR (SVG) inclou **identificador únic (UUID)** vinculat a la fila d’entrada a PostgreSQL; en escaneig vàlid pel Validador, l’estat passa de **venuda** a **utilitzada** en temps real, invalidant reutilitzacions posteriors del mateix codi.
- **Validació de grups**: una credencial per seient; el Validador pot escanejar **ràpid i successivament**; **cada escaneig** genera una **petició individual** al backend Laravel per validar aquell seient del grup.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Selecció de seients amb mapa i reserva temporal (Priority: P1)

Un usuari (amb o sense compte encara) consulta el mapa de la sala amb la imatge oficial de distribució i les zones identificades; pot seleccionar fins a sis seients i obtenir un hold **sense haver iniciat sessió**. Per **pagar i confirmar la comanda**, ha d’**iniciar sessió o registrar-se**; si obre el flux d’autenticació des del checkout, el sistema pot **allargar el hold +2 minuts** (una vegada) mentre completa el login. El sistema manté una reserva temporal amb temps límit clar fins al pagament o expiració.

**Why this priority**: Sense mapa fiable i bloqueig de seients no hi ha flux de venda usable ni es pot evitar la sobre-venda en condicions normals.

**Independent Test**: Es pot provar amb un esdeveniment de prova mostrant imatge i zones, seleccionant seients i comprovant que la reserva expira i allibera places segons les regles sense completar el pagament.

**Acceptance Scenarios**:

1. **Given** un esdeveniment amb mapa disponible, **When** l’usuari obre la selecció de seients, **Then** veu la imatge del mapa i les etiquetes de zona alineades amb la font de catàleg integrada.
2. **Given** seients lliures i un **temps de hold** definit per a aquest esdeveniment dins del rang permès, **When** l’usuari en tria fins a sis, **Then** el sistema els reserva de forma atòmica (tot el grup o cap) i mostra el **compte enrere** coherent amb aquesta durada configurada.
3. **Given** una reserva activa, **When** el temps s’esgota sense confirmar la compra, **Then** els seients tornen a estar disponibles per a altres compradors.
4. **Given** una reserva activa a punt d’expirar, **When** l’usuari intenta **allargar** el temps **sense** iniciar el flux d’autenticació des del checkout, **Then** el sistema **no** ofereix pròrroga genèrica; en expirar, ha de **tornar a iniciar** la selecció de seients (subjecte a disponibilitat actual).
5. **Given** un hold actiu i l’usuari **inicia login o registre des del checkout**, **When** el backend rep l’esdeveniment de «marge de login», **Then** el TTL del hold a Redis s’**incrementa en 2 minuts** (una sola vegada per aquest hold) i el compte enrere reflecteix la nova `expires_at`.
6. **Given** seients en hold i usuari **autenticat**, **When** inicia el pagament a la passarel·la externa i la comanda passa a **Pending Payment**, **Then** el hold roman actiu a Redis fins confirmació de pagament o fins expiració del TTL; si el pagament no es confirma a temps, els seients s’alliberen.

---

### User Story 2 - Entrada amb codi escanejable verificable (Priority: P2)

Per cada seient comprat, el comprador rep **una credencial digital independent** (fins a sis credencials per compra), cadascuna amb un codi escanejable generat només pel servidor; cada codi es pot mostrar en pantalles de diferents mides sense perdre llegibilitat per als lectors habituals.

**Why this priority**: L’accés a l’esdeveniment depèn d’una credencial que no es pugui falsificar des del client.

**Independent Test**: Es pot verificar amb una compra de diversos seients: comprovar que hi ha tantes credencials com seients, que només el servidor pot produir-les vàlides i que cada patró es renderitza en format vectorial.

**Acceptance Scenarios**:

1. **Given** una compra confirmada d’un o més seients, **When** l’usuari obre les entrades, **Then** veu un codi escanejable per **cada** seient, adequat per a validació sense exposar secrets de generació al navegador o app.
2. **Given** dues credencials de la mateixa comanda o de compres diferents, **When** es comparen, **Then** cadascuna és única i vinculada a **un sol** seient.

---

### User Story 3 - Validació a l’accés i marca visual a l’app (Priority: P3)

El personal de porta o un dispositiu mòbil amb **rol de validador** (autenticat i autoritzat) escaneja el codi; el servidor registra l’entrada vàlida una sola vegada; l’**Assistent** **no** pot registrar ell mateix l’entrada com a usada sense aquest escaneig. Un cop validada **una** credencial, l’**Assistent** veu **aquesta** entrada marcada clarament (p. ex. una «X» o estat «usat»); les altres credencials del mateix grup romanen no usades fins que es validin; el canvi apareix a l’historial de compres o entrades.

**Why this priority**: Tanca el cicle de vida del bitllet i evita reutilitzacions; l’experiència de l’usuari confirma l’estat sense dubte.

**Independent Test**: Escaneig d’una entrada vàlida mostra èxit al lector; el mateix codi no torna a ser acceptat; l’app de l’usuari reflecteix l’estat usat i l’historial s’actualitza.

**Acceptance Scenarios**:

1. **Given** una entrada no usada, **When** un **validador autoritzat** escaneja el codi amb connexió al servei, **Then** el sistema accepta la primera validació i la registra (una petició HTTP al backend **per credencial**; en grups, escaneigs successius ràpids amb una petició per seient).
2. **Given** una entrada ja validada, **When** es torna a escanejar, **Then** el sistema rebutja el segon ús amb un missatge clar per al validador.
3. **Given** una validació correcta d’una credencial, **When** l’**Assistent** obre aquesta entrada a l’app, **Then** veu una indicació visual inequívoca d’ús en **aquesta** credencial (p. ex. «X» o estat equivalent) mentre les altres del mateix grup segueixen sense marcar fins a la seva validació; l’historial reflecteix l’esdeveniment.
4. **Given** el dispositiu validador **sense** connexió al servidor, **When** es llegeix el codi, **Then** el sistema **no** registra l’entrada com a usada i el validador rep un missatge clar (p. ex. error de xarxa / cal connexió); l’entrada de l’**Assistent** **no** canvia a «usat».

---

### Edge Cases

- Dos usuaris intenten reservar el mateix seient simultàniament: només un pot confirmar el bloqueig (transacció amb bloqueig de fila a PostgreSQL); l’altre rep error **immediat** via Socket.IO: **«Aquest seient acaba de ser seleccionat per un altre usuari»**; Redis i PostgreSQL reflecteixen l’estat coherent en mil·lisegons.
- **Esgotament entre selecció i pagament**: si el seient deixa d’estar disponible abans de completar la compra, el servidor **denega** la transacció final amb **«Seient ja no disponible»** i allibera qualsevol hold residual.
- La durada del hold és la **configurada per a l’esdeveniment** (dins del rang 3–5 minuts): el sistema ha de mostrar sempre el temps efectiu d’aquesta política durant la sessió de compra (sense canvis «en calent» segons càrrega del sistema).
- Fallada o indisponibilitat temporal del catàleg **Top Picks**: **fallback** automàtic a consulta **PostgreSQL** (PostGIS per proximitat o rellevància / ordre manual al model), amb missatge clar si la degradació és visible a l’usuari; sense mapes ni zones inventades.
- Validació sense xarxa: **no** es pot completar el registre d’ús al recinte; el validador rep feedback explícit (cal connexió / reintenta). Latència alta: reintents o missatge d’error sense canvi d’estat «usat» fins a resposta vàlida del servidor.
- Intents de reutilització, captura de pantalla o codis caducats: el QR porta **UUID** vinculat a la fila d’entrada; després d’un escaneig vàlid, l’estat passa de **venuda** a **utilitzada** a PostgreSQL en temps real i el mateix codi **no** és vàlid per a un segon ús.
- L’**Assistent** intenta marcar l’entrada com a usada des de l’app sense un escaneig per validador autoritzat: el sistema **no** ha de registrar un ús vàlid al recinte; només la validació iniciada per personal o dispositiu amb rol de validador compta.
- Compra de diversos seients: només les credencials escanejades pel validador passen a «usades»; el grup pot tenir entrades parcialment validades (alguns seients usats, altres encara no).
- Expiració del hold: **sense pròrroga genèrica** (no es pot «demar temps» lliurement); **excepció**: **+2 minuts** només quan s’inicia **login o registre des del checkout** (vegeu Session 2026-04-12). Fora d’aquest cas, el comprador ha de tornar a seleccionar si expira (els mateixos seients poden estar ja reservats per un altre).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema HA de mostrar el mapa de seients utilitzant la imatge de referència (`snapshotImageUrl`) i les metadades de zones proporcionades per l’API Top Picks de Ticketmaster (o contracte equivalent), sense contradir el catàleg oficial quan la integració estigui activa. **Si el catàleg Top Picks no respon**, el sistema HA d’aplicar un **fallback** a dades servides des de **PostgreSQL** (consulta ordenada per **proximitat PostGIS** o **rellevància / ordre manual** al model), sense inventar zones fictícies.
- **FR-002**: El sistema HA de permetre seleccionar com a màxim sis seients per operació de reserva i HA d’aplicar un bloqueig atòmic del conjunt (totes les places del grup o cap) fins a confirmació de compra o expiració. Per a la **concurrència estricta** sobre el mateix seient, el backend HA d’usar **transaccions amb bloqueig de fila** a PostgreSQL: el primer usuari rep confirmació; el segon rep error immediat i notificació **Socket.IO** amb el text **«Aquest seient acaba de ser seleccionat per un altre usuari»**; l’estat a PostgreSQL i Redis HA de mantenir-se alineat en **mil·lisegons**. En la **confirmació final** de compra, si un seient ja no està disponible (font de veritat al servidor), el sistema HA de **denegar** la transacció amb el missatge **«Seient ja no disponible»** i alliberar qualsevol **hold** residual associat.
- **FR-003**: El temps de manteniment de la reserva (hold) HA de ser gestionat al servidor amb una durada **dins del rang 3 a 5 minuts** fixada **per esdeveniment** (o política d’organitzador equivalent), **estable** mentre dura la venda d’aquest esdeveniment; la **línia base de producte** fixa **4 minuts** (TTL inicial a **Redis**) dins d’aquest rang. **Pròrroga única**: quan el servidor **detecta** que un **usuari anònim** ha **iniciat login o registre des del checkout**, s’**incrementa el TTL en +120 segons** (una vegada per hold); el **total màxim efectiu** en aquesta combinació és **6 minuts** (4+2) abans d’expirar, dins dels límits de producte. Aquesta durada HA d’estar sempre visible al compte enrere (sense ajust «en calent» per càrrega). Fora d’aquesta excepció, en expirar el bloqueig cau i cal **nova** selecció de seients. Si el **compte enrere del client** es desvia més de **2 segons** respecte al servidor, el sistema HA de **resincronitzar** el temps mostrat (p. ex. via **Socket.IO** canal de lectura, **FR-014**).
- **FR-003a**: Durant el pagament a passarel·la externa, la comanda HA de poder estar en estat **Pending Payment**; el hold a **Redis** HA de romandre mentre duri el pagament dins del **TTL del hold**; si el pagament **no** es confirma abans d’aquest TTL, els seients s’alliberen automàticament.
- **FR-004**: L’estat de reserva temporal HA d’emmagatzemar-se de manera coherent amb el disseny de concurrència del projecte (una sola font de veritat al servidor; magatzem de bloquejos ràpid per a holds de curta durada segons constitució).
- **FR-005**: Les credencials d’entrada HA de ser emeses i signades pel backend (tokens verificables pel servidor); el client NO HA de poder generar credencials vàlides per compte propi. Per cada seient confirmat en una compra, el sistema HA d’emetre **exactament una** credencial escanejable independent (fins a sis per comanda), cadascuna vinculada a **un sol** seient. El **JWT** del QR tindrà **TTL de 15 minuts** des de la generació **o** vinculació explícita a la **sessió activa** de l’usuari (especificat al pla d’implementació).
- **FR-006**: El codi escanejable HA de generar-se en format vectorial (SVG) a partir de la càrrega útil acordada, seguint la pila de generació de codis fixada al pla tècnic, i HA d’incloure un **UUID** vinculat a la fila d’entrada a PostgreSQL, per garantir llegibilitat, traçabilitat i invalidació després del primer ús vàlid.
- **FR-007**: El flux de validació HA de comprovar la credencial contra el servidor **només quan l’actor és un validador autoritzat** (personal de porta o dispositiu amb rol de validador), HA de registrar la primera utilització vàlida i rebutjar reutilitzacions; els clients **assistent** sense aquest rol **no** poden completar el registre d’entrada al recinte. Cada escaneig HA de ser una **petició individual** al backend per credencial (grups: escaneigs successius ràpids, un per seient). En confirmar-se la validació, l’estat de l’entrada a PostgreSQL HA de passar de **venuda** a **utilitzada** en temps real. Sense **connexió exitosa** al servidor en el moment de la validació, el sistema **no** HA de marcar cap entrada com a usada ni emetre registre d’ús vàlid (sense cua offline que substitueixi aquesta regla).
- **FR-008**: Després de la validació, l’app de l’usuari HA de mostrar una marca visual clara sobre el bitllet (p. ex. «X» o overlay equivalent) i HA d’actualitzar la vista d’historial relacionada amb l’entrada (referència de disseny [22, Historial]).
- **FR-009 (Auth API — font de veritat)**: **Laravel 11** és l’**única font de veritat** per a negoci persistent i l’**únic emissor** de **JWT** d’API: **registre**, **login**, **refresh** (si escau), **logout**; **cada petició** a l’API protegida HA de ser **validada** pel middleware JWT al servidor. Endpoint **`/me`** (o equivalent) per dades bàsiques de perfil. Els **JWT de ticket** (QR) es verifiquen al validar-se a porta (**FR-007**).
- **FR-010 (Estat client — Pinia)**: El **Nuxt 3** HA d’usar **Pinia** com a magatzem global: store **`auth`** (JWT d’usuari, perfil, persistència segura del token) i store **`hold`** (o equivalent) per als **seients seleccionats** i l’estat del hold **abans de la confirmació** de compra; **middleware de ruta** HA de protegir **checkout**, **Tickets**, **Guardats**, **Social**, **Perfil** i qualsevol **consulta privada**. *Excepció documentada*: la **selecció inicial de seients** i el **mapa de venda** poden funcionar amb **sessió anònima** (identificador de hold) fins al **pagament**, que **sempre** exigeix JWT (**Session 2026-04-12**).
- **FR-011 (Restricció de pagament)**: **No** es pot completar el pagament ni crear comanda pagada sense **usuari autenticat**; el flux HA de redirigir a login/registre abans de la passarel·la.
- **FR-012 (Usuari — Comprador / Assistent)**: Amb **JWT vàlid**, l’usuari HA de poder: **cercar esdeveniments** amb filtres i **geolocalització** (**PostGIS**); **selecció de seients en temps real** (**Socket.IO**); **visualitzar tickets** (QR **SVG**); **gestionar favorits**; **social**: veure **activitat d’amics** i **enviar tickets** a altres usuaris amb **transferència de propietat validada pel servidor** (no només canvi de client).
- **FR-012a (Validador)**: Rol **operatiu** amb interfície **mòbil simplificada**; **escaneig** del QR a porta; el backend HA de **validar el JWT del ticket**, comprovar idempotència i marcar l’entrada com a **utilitzada** de forma **irreversible** a **PostgreSQL** (**FR-007**); sense aquest rol no es completa la validació.
- **FR-013 (Administrador)**: Accés a **panell de gestió global** (JWT + rol `admin`): **importació massiva** d’esdeveniments (**Ticketmaster API** / Discovery Feed segons pla); **dissenyador de plànols de seients** emmagatzemat com a **JSONB** (estructura del mapa editable per esdeveniment o recinte); **configuració de preus** i polítiques de venda; **Dashboard temps real** (**Socket.IO**: vendes, usuaris connectats); permisos **separats** del **Validador** (aquest últim no requereix el panell global).
- **FR-014 (Socket.IO — arquitectura híbrida)**: Per garantir que **tothom** vegi els canvis al mapa en temps real (inclosos usuaris **sense** JWT), el sistema HA d’implementar **canals de lectura pública** (p. ex. namespace o room per `eventId`) on es fan **broadcast** les actualitzacions d’**estat de seients** derivades del backend (disponible, reservat, etc.) **sense** exigir JWT al handshake d’aquesta subscripció. Les **escriptures** (crear/alliberar **hold**, confirmar **comanda**) continuen sent **només** via **API REST Laravel** amb **JWT** (o sessió anònima per hold segons disseny). Per a esdeveniments **privats** (p. ex. `ticket:validated` cap a l’**Usuari** titular), el **socket-server** HA d’usar **rooms autenticades** amb **JWT** al handshake o token de connexió validat amb la mateixa clau que Laravel. La retransmissió Socket **no** substitueix la font de veritat (constitució III).
- **FR-015 (Shell de navegació — Usuari)**: La interfície de l’**usuari (Comprador/Assistent)** HA d’usar **Header** (desktop) i **Footer fix** (mòbil) amb **exactament 6 seccions principals**: (1) **Home**, (2) **Buscador + Mapa** (un sol ítem de navegació que cobreix llista i mapa interactiu), (3) **Tickets**, (4) **Guardats**, (5) **Social**, (6) **Perfil**. Cap d’aquests apartats no es subdivideix en més entrades principals de primer nivell al shell.
- **FR-016 (Home / Inici)**: La pàgina d’inici HA de mostrar un **feed** amb seccions **Destacats** i **Triats per a tu**. **Gemini** (backend) retorna només **JSON** amb **IDs d’esdeveniments** (recomanació); **mai** inicia compres ni reserva seients. El **filtrat per proximitat** usa **PostGIS**. La **compra** i la **disponibilitat** es validen **sempre** a **Laravel + PostgreSQL** (**C1**, Session 2026-04-15).
- **FR-017 (Buscador — llista)**: Ha d’existir una vista de **cerca per text**, **filtres de classificació** (p. ex. música, esports), **dates** i **rang de preu**; el catàleg ve del **Discovery API de Ticketmaster** (sincronitzat / proxy des del **backend**); **botó flotant «Mapa»** obre la vista mapa; **icona de cor** desa l’esdeveniment a **Guardats**.
- **FR-018 (Buscador — mapa interactiu)**: Integració **Google Maps JavaScript API** amb **marcadors** basats en dades **PostGIS**; vista overlay o pantalla dedicada dins de la secció **Buscador + Mapa**; **«Com arribar»** (o equivalent) obre **modal de confirmació** abans de **redirecció externa** a l’app o web de Google Maps; clic en marcador → resum; clic al resum → detall d’esdeveniment.
- **FR-019 (Detall + mapa de seients — UI)**: **Imatge alta resolució**, descripció i **mapa de seients interactiu** (Top Picks); fins a **6** seients; **temporitzador** visible; **canvi de color dels seients en temps real** per a tots els clients quan un seient passa a reservat (Socket.IO); coherent amb **FR-002** i **FR-003**.
- **FR-020 (Entrades — llista)**: **Llistat de targetes agrupades per esdeveniment** (dades **PostgreSQL**); clic → detall del ticket; **«Enviar Tickets»** obre **modal** per triar **amic** social i iniciar **transferència**.
- **FR-021 (Detall del ticket — UI)**: Mostrar seient, recinte, hora, **QR SVG**; **regeneració / caducitat curta del token de visualització** per reduir risc de captura (alineat amb **FR-005** / **FR-006**); **«X»** gran si el ticket ja està **validat** (**FR-008**).
- **FR-022 (Social)**: **Llista d’amics** i **cercador per nom d’usuari**; les **invitacions** es persisteixen a **PostgreSQL** (`friend_invites`: `sender_id`, **`receiver_id` o `receiver_email`**, `status` pendent/acceptat/rebutjat); **enllaç d’invitació** d’amistat: si el destinatari està autenticat i accepta el flux, l’**acceptació** pot ser **automàtica** (regles al pla); **detall d’amic** amb **historial d’activitat** (passat/futur) **subjecte a privacitat** configurable per l’amic; **«Enviar Entrada»** des del perfil d’amic cap al mateix flux de **transferència**: el **servidor** invalida el **JWT/QR** antic i en genera un de nou (**SVG**) per al destinatari (**FR-005** / **FR-006**).
- **FR-023 (Guardats)**: Llistat d’esdeveniments marcats amb el **cor** per a **recompra** ràpida; persistència al servidor.
- **FR-024 (Perfil)**: Formulari de **nom, email, contrasenya**; **configuració de privacitat** amb opció de **desactivar la personalització Gemini** (recomanacions «Triats per a tu»).
- **FR-025 (Administrador — shell i pantalles)**: Interfície amb **Sidebar**; **Dashboard** temps real (usuaris connectats, reserves actives, **mini-mapa** del recinte amb estats de seient Lliure/Reservat/Venut, **Socket.IO**); **Gestió d’esdeveniments (CRUD)** amb **importació** massiva **Ticketmaster** (Discovery Feed 2.0 o equivalent), **disseny de plànol JSONB**, edició de preus/categories/aforament, **ocultació lògica**; **Informes i analítica**; **Gestió d’usuaris i tickets**; **Control d’accés (Staff / Validador)** vista **mòbil-first** amb **escàner de càmera** (web).

### Arquitectura de seguretat i estat (resum)

| Capa | Responsabilitat |
|------|-----------------|
| **Laravel 11** | Font de veritat; emissió i **validació** de **JWT** en cada petició protegida; regles de negoci i persistència a **PostgreSQL**. |
| **Redis** | Holds de reserva (línia base **4 min**; **+2 min** si login des del checkout sense sessió prèvia al pagament). |
| **Pinia (Nuxt 3)** | Estat global: **JWT** d’usuari i **seients seleccionats** / hold abans de confirmació; sense substituir el servidor. |
| **Socket.IO** | Lectura pública d’actualitzacions de mapa + rooms JWT per a esdeveniments privats (**FR-014**). |

### Interfície per rol (mapa de pantalles)

**Terminologia**: en tot el producte Speckit s’usen **Usuari**, **Validador** i **Administrador** com a noms de rol (Session 2026-04-15).

Les pantalles següents defineixen l’**àmbit de producte** del frontend Nuxt; els detalls visuals es concreten al disseny, però els **fluxos i tecnologies** són normatius.

#### Usuari (app de consum)

| Id | Pantalla | Contingut / tecnologia principal | Fluxos clau |
|----|-----------|-----------------------------------|-------------|
| A | **Home (Inici)** | Feed **Destacats** + **Triats per a tu**; **Gemini** + **PostGIS** | Clic esdeveniment → Detall |
| B | **Buscador (llista)** | Barra text, filtres (classificació, dates, preu); **Discovery API** (via backend) | Botó **Mapa** → overlay; **Cor** → Guardats |
| C | **Buscador (mapa — overlay)** | **Google Maps JS** + marcadors; coordenades **PostGIS**; geolocalització | **Com arribar** → Maps extern; resum → Detall |
| D | **Detall esdeveniment + mapa de seients** | Imatge TM, descripció, seients (Top Picks); **Socket.IO**, **Redis** | Selecció ≤6, temporitzador, colors temps real |
| E | **Entrades (llista)** | Targetes agrupades per esdeveniment; **PostgreSQL** | Targeta → Detall ticket; **Enviar Tickets** → modal amic |
| F | **Detall del ticket** | QR **SVG**; **JWT** ticket + **node-qrcode** | QR dinàmic; **X** si validat |
| G | **Social (llista i cerca)** | Amics + cercador username; enllaç amistat | Clic amic → Social detall |
| H | **Social detall (perfil d’amic)** | Nom, activitat (privacitat); conversa fora d’abast tècnic aquí | **Enviar Entrada** des d’inventari propi |
| I | **Guardats** | Esdeveniments amb cor | Accés ràpid a recompra |
| J | **Perfil** | Dades compte; **privacitat** (opt-out Gemini) | — |

#### Administrador

| Id | Pantalla | Contingut / tecnologia principal |
|----|-----------|-----------------------------------|
| A | **Dashboard (temps real)** | Gràfics vius: connectats, reserves actives; mini-mapa recinte (estats seient); **Socket.IO** |
| B | **Gestió d’esdeveniments (CRUD)** | Taula esdeveniments; **import Discovery Feed 2.0**; edició preus/categories/aforament; ocultació lògica |
| C | **Informes i analítica** | Recaptació, ocupació, evolució temporal de vendes |
| D | **Gestió d’usuaris i tickets** | Llistat usuaris; compres per usuari; monitoratge frau / bloquejos sospitosos |
| E | **Control d’accés (Staff view)** | UI mòbil; escàner càmera → validació **JWT** servidor → **X** a BD + temps real a l’usuari |

### Definicions de rols (3 rols producte)

Els **permisos d’aplicació** es modelen com a **tres rols** principals (subrols conceptuals **Comprador** / **Assistent** dins del mateix rol **Usuari**).

1. **Usuari (inclou Comprador i Assistent)**  
   - **Accés**: **JWT** (Laravel) obligatori per a **qualsevol acció de compra** (checkout, comanda, pagament) i per a **consulta privada** (tickets, favorits, perfil, social autenticat, dades de compte).  
   - **Funcions**: cerca amb filtres i **PostGIS**; seients en temps real (**Socket.IO**); tickets **SVG**; favorits; social (activitat amics, **transferències validades al servidor**).  
   - **Assistent**: titular d’una entrada després d’una transferència; mateixes regles d’app que el comprador per a visualització del ticket, sense permís de validació.

2. **Validador**  
   - **Accés**: JWT d’usuari amb rol **validator**; interfície **mòbil** orientada a escaneig.  
   - **Funcions**: llegir QR, enviar **JWT del ticket** a l’API; el servidor valida i estableix **utilitzat** de forma **irreversible** a PostgreSQL (**FR-007**, **FR-012a**).

3. **Administrador**  
   - **Accés**: JWT amb rol **admin**; **Sidebar**; panell **no** accessible als rols anteriors sense permís.  
   - **Funcions**: import massiu (**Ticketmaster API**), **plànols JSONB**, preus, **Dashboard** temps real (vendes, connectats), gestió d’usuaris/tickets segons **FR-013** / **FR-025**.

**Navegació (Usuari)**: **FR-015** — sis seccions: Home, Buscador+Mapa, Tickets, Guardats, Social, Perfil.

### Key Entities

- **Esdeveniment / sessió de venda**: Context on s’aplica el mapa i les regles de preu per zona; pot tenir **plànol de seients** en **JSONB** (disseny admin).
- **Zona**: Agrupació lògica de seients amb metadades del catàleg Top Picks.
- **Seient**: Unitat seleccionable amb identificador estable dins del mapa.
- **Reserva temporal (hold)**: Bloqueig de fins a sis seients amb caducitat (**4 min** línia base a Redis, **+2 min** si login des del checkout); propietat d’usuari o sessió anònima fins al pagament.
- **Entrada / credencial**: JWT de ticket verificable i QR **SVG** associat a **un** seient; transferència de propietat només via servidor.
- **Registre de validació**: Primera utilització vàlida **irreversible** a PostgreSQL (**venuda → utilitzada**); actor amb rol **Validador** (**FR-012a**, **FR-007**).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En proves controlades, dos compradors concurrents no poden completar la compra del mateix seient per al mateix esdeveniment quan només en queda un disponible (el segon rep un resultat coherent d’indisponibilitat).
- **SC-002**: El comprador veu en tot moment el temps restant de reserva mentre el bloqueig és actiu; en el **95%** dels casos de prova el temps mostrat coincideix amb el del servidor amb una tolerància màxima de **2 segons**; si se supera, el client es **resincronitza** (p. ex. via Socket.IO) abans d’expirar el hold.
- **SC-003**: El 100% dels intents de segon ús d’una entrada ja validada són rebutjats en condicions de xarxa normals.
- **SC-004**: Després d’una validació exitosa, el 95% dels usuaris veuen l’estat «usat» o la marca visual equivalent a l’entrada en menys de 5 segons des de la confirmació al servidor.
- **SC-005**: Amb connectivitat estable, el temps total per validar successivament **totes** les credencials d’una compra de sis seients (sis escaneigs, un per credencial) es manté dins d’un límit acordat a proves (p. ex. inferior a **60 segons** de pont a pont en condicions normals sense cua extrema), sense errors de servidor atribuïbles al producte.

## Assumptions

- Existeix acord o llicència per utilitzar l’API Top Picks de Ticketmaster per a `snapshotImageUrl` i metadades de zones; els identificadors d’esdeveniment i mapa es mapegen al model intern al pla tècnic.
- El rang 3–5 minuts és el límit de producte; la **línia base** de **4 minuts** s’utilitza com a valor oficial per defecte dins del rang; la **durada concreta** per esdeveniment la fixen organitzador o administració (dins del rang), sense ajust automàtic per càrrega en temps real durant el hold.
- **Pròrroga única** des del checkout: **+2 min** sobre el bloqueig inicial de **4 min** (total màxim efectiu **6 min** en aquesta política); en altres casos, expirat el compte enrere la nova reserva és un flux nou (selecció atòmica de nou).
- La constitució del projecte defineix Redis per a holds de curta durada i Laravel com a font de veritat; aquesta funcionalitat hi queda alineada.
- La generació SVG usa la llibreria **node-qrcode v1.5** en el servei o pas de build definit al pla (p. ex. worker Node), amb els mateixos secrets que el backend utilitza per als JWT del payload del QR, sense exposar claus al client. El **TTL del JWT** del QR és de **15 minuts** des de la generació o queda **lligat a la sessió activa** de l’usuari (elecció documentada al pla).
- **Gemini** (1.5 Flash) no substitueix el catàleg de seients ni el mapa Top Picks; només s’usa d’acord amb la constitució per funcions **assistencials** fora d’aquest flux si escau.
- La referència [22, Historial] es tradueix en criteris visuals concrets al disseny (mida de la «X», contrast, accessibilitat) durant la fase de planificació UI.
