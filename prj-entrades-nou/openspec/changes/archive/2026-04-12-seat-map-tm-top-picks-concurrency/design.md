## Context

El projecte ja té **Laravel 11**, **PostgreSQL**, **Redis**, **Socket.IO** i patrons de **hold** de seients (vegeu `specs/001-seat-map-entry-validation` i serveis `SeatHoldService`, etc.). Aquest canvi **amplia** el mapa visual amb dades **Ticketmaster Top Picks** (proxy al backend) i endurix la narrativa de **concurrencia** i **broadcast** per a avaluació acadèmica.

## Goals

- Claus API **mai** al navegador; només el backend crida Ticketmaster.  
- **SoT** d’estat de seient: PostgreSQL; Redis només per **volatilitat** (hold / mirall de cua segons disseny actual).  
- **TTL hold Redis: 240 s** (4 minuts), alineable amb requisit docent.  
- **Màxim 6 seients** en selecció (coherent amb compra per quantitat existent).  
- **Room per `eventId`** per a tots els clients al mapa del mateix esdeveniment.

## No-goals (fase inicial)

- No garantir paritat pixel-perfect amb la web de Ticketmaster si l’API canvia format.  
- No implementar passarel·la de pagament real (es pot seguir amb flux stub existent).

## Arquitectura

### 1. Top Picks (proxy)

```
[Nuxt] --GET /api/events/{id}/seat-map (o ruta acordada)--> [Laravel]
  --> Ticketmaster Top Picks HTTP (clau només a .env servidor)
  <-- snapshotImageUrl + zones[] { id, polygon|coords, availability, ... }
```

- Opcional: **cau** curt a Redis o a taula `venues`/`events` si cal reduir crides TM (decisió a implementació).  
- Resposta **estable** (JSON) independent del format brut de TM (adaptador al `Service`).

### 2. Reserva de seient (PG + Redis)

Dins una **transacció**:

1. `SELECT … FROM seats WHERE id = ? FOR UPDATE` (o taula equivalent).  
2. Validar estat (disponible vs venut vs held vàlid).  
3. Actualitzar fila (held, `held_until`, `current_hold_id`, etc. segons esquema).  
4. `COMMIT`.  
5. Fora de la transacció o en el mateix flux ordenat: **SET** clau Redis amb TTL **240** i payload mínim (hold id, seat id, user/session).

Si la transacció falla per **concurrència**, el client que perd rep resposta HTTP adequada **i** es pot emetre esdeveniment socket de conflicte.

### 3. Temps real

```
[Laravel] --PUBLISH redis channel (p. ex. seat_updates)--> [Redis]
  --> [socket-server subscriber] --emit--> room `event:{eventId}` (namespace públic ja existent o ampliació)
```

- Esdeveniments mínims suggerits: `seat:held`, `seat:released`, `seat:sold`, `seat:contention_lost` (o un sol esdeveniment amb `type` al payload).  
- El missatge de conflicte en català: *«Aquest seient acaba de ser seleccionat per un altre usuari»* (clau estable al frontend).

### 4. Frontend (Nuxt 3)

- Capa **SVG** superposada amb `viewBox` alineat amb la imatge (coordenades normalitzades 0–1 o pixels segons contracte API).  
- Estat local: **zona seleccionada** → zoom / càrrega de rejilla de seients (dades des d’endpoint d’API, no TM directe).  
- **Sold out**: `availability === 0` (o camp acordat) → classe CSS vermella + `pointer-events: none` + sense navegació a rejilla.

## Decisions obertes (resoldre abans o durant tasks)

- **Identificador TM** per Top Picks per esdeveniment (attraction id, event id TM, o snapshot guardat al sync).  
- Reutilització de **`SeatHoldService`** vs nou servei dedicat només per TTL 240 s.  
- Unificació amb **quantitat màxima 6** ja present a `orders/quantity`.

## Riscos

- **Rate limits** Ticketmaster: cal caching i errors 429 documentats.  
- **Drift** entre imatge i polígons si TM actualitza snapshot sense actualitzar zones al mateix instant.

## Referències al codi

- `app/Services/Hold/SeatHoldService.php`  
- `socket-server` rooms `event:*` i Redis subscriber  
- `specs/001-seat-map-entry-validation/contracts/openapi.yaml`
