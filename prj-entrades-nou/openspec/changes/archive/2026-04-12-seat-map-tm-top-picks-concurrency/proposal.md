## Why

Cal una experiència de **mapa d’asientos** professional per a avaluació docent (“precisió al mil·límetre”): imatge de recinte i zones (Ticketmaster Top Picks) **sense exposar claus al navegador**, **concurrencia atòmica** a PostgreSQL, **holds** a Redis amb TTL, i **sincronització en temps real** via Redis Pub/Sub + Socket.IO per room d’`eventId`.

## What Changes

1. **Mapa (proxy + UI)**  
   - Backend com a **únic client** de Ticketmaster Top Picks: retorna `snapshotImageUrl`, metadades de zones (polígons clicables) i disponibilitat per zona (p. ex. 0 = sold out).  
   - Nuxt: imatge de fons, capa **SVG** interactiva, zoom a rejilla per zona, zones **sold out** en vermell sense clic.

2. **Concurrencia i holds**  
   - Reserva de seient: transacció amb `SELECT … FOR UPDATE` sobre la fila del seient.  
   - Després de validar PG, escriure hold a **Redis** amb **TTL 240 s** (4 min); expiració allibera el hold sense compra.  
   - Límit **màxim 6 seients** seleccionables (validació FE + BE).

3. **Temps real**  
   - Room Socket.IO per **`event:{eventId}`** (o convenció equivalent ja existent).  
   - Flux: API Laravel confirma canvi → Pub/Sub Redis → Node reemet a la room → clients actualitzen color (disponible / reservat / venut).  
   - **Conflicte**: si dos usuaris competeixen, el que perd la transacció rep missatge per socket: *«Aquest seient acaba de ser seleccionat per un altre usuari»*.  
   - Post-pagament: estat **sold** irreversible a PG + emissió definitiva (p. ex. vermell) a tots.

## Capabilities

### New Capabilities

- `tm-top-picks-seat-map-proxy`: Proxy Laravel cap a Top Picks; resposta amb URL d’imatge i geometria/avail de zones sense claus al client.
- `seat-map-svg-zoom-ui`: Vista Nuxt amb imatge + SVG + zoom a rejilla i bloqueig visual de zones esgotades.
- `seat-hold-pg-redis`: Hold amb `FOR UPDATE` + Redis TTL 240 s; límit 6 seients.
- `seat-map-realtime-broadcast`: Broadcast Socket.IO per `eventId` després de Redis; conflictes i estat `sold`.

### Modified Capabilities

- Rutes API existents de seients / holds / comanda (segons `specs/001-seat-map-entry-validation` i codi actual).  
- `socket-server`: subscripció Redis i esdeveniments de domini del mapa (alineat amb FR-014).

## Impact

- **Backend**: nous o estesos endpoints de mapa Top Picks, reforç transaccional a reserva de seient, configuració TTL Redis, publicació Redis després de canvis d’estat.  
- **Frontend**: nova o substituïda pàgina de mapa per esdeveniment (components SVG, estat de zoom, desactivació sold out).  
- **Node**: emissió a room d’esdeveniment i missatges de conflicte.  
- **Contractes**: delta OpenAPI dins el canvi o actualització de `contracts/openapi.yaml`.  
- **Proves**: tests de feature API (concurrencia simulada), proves de socket on sigui viable.

## User Stories (traçabilitat)

- **US-SM1**: Com a usuari, vull veure el mapa del recinte i fer zoom a una zona sense que el navegador tingui la clau de Ticketmaster.  
- **US-SM2**: Com a usuari, vull que les zones sense disponibilitat es vegin clares i no siguin clicables.  
- **US-SM3**: Com a usuari, vull que la meva reserva de seient sigui justa davant d’altres (transacció + missatge si perdo la cursa).  
- **US-SM4**: Com a usuari, vull veure en temps real quan un altre reserva o compra un seient abans de recarregar.
