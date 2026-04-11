# Model de dades: Mapa de seients, bloquejos, entrades i validació

Alineat amb [spec.md](./spec.md) (actualització checklist: Pending Payment, venuda/utilitzada, UUID QR, concurrència PostgreSQL + Redis, fallback Top Picks).

## Entitats principals

### Event (Esdeveniment)

| Camp | Tipus | Notes |
|------|--------|--------|
| id | UUID / bigserial | PK |
| external_tm_id | string nullable | ID Ticketmaster si aplica |
| name | string | |
| hold_ttl_seconds | int | Entre **180 i 300** (3–5 min); **línia base de producte 240** (4 min) per defecte dins del rang |
| venue_id | FK → Venue | |
| starts_at | timestamptz | |
| hidden_at | timestamptz nullable | **Ocultació lògica** (admin); no visible al catàleg públic però conservat per historial |
| category | string nullable | Classificació (música, esports, …) per filtres del buscador |
| seat_layout | jsonb nullable | **Dissenyador de plànol** (admin): geometria/zones/seients serialitzats; complementa Top Picks quan escaigui (**FR-013**) |

### Venue

| Camp | Tipus | Notes |
|------|--------|--------|
| id | PK | |
| name | string | |
| location | geography(Point,4326) nullable | Per **marcadors** al mapa de cerca i **distància** PostGIS |
| postgis metadata | opcional | Fallback de mapa: consultes per **proximitat** quan Top Picks no està disponible |

### Zone

| Camp | Tipus | Notes |
|------|--------|--------|
| id | PK | |
| event_id | FK | |
| external_zone_key | string nullable | Clau TM / Top Picks |
| label | string | Etiqueta mostrada al mapa |
| sort_order | int | |

### Seat

| Camp | Tipus | Notes |
|------|--------|--------|
| id | PK | |
| event_id | FK | |
| zone_id | FK | |
| external_seat_key | string | Identificador estable al mapa (TM / intern) |
| status | enum | `available`, `held`, `sold`, `blocked`, etc. (semàntica al servei) |

**Concurrència (especificació)**: en confirmar hold o venda, usar **transacció amb `SELECT … FOR UPDATE`** (o equivalent) sobre la fila del **seient** (o taula de bloqueig associada) perquè només un comprador guanyi la cursa; el segon rep error i es notifica per Socket.IO (vege [plan.md](./plan.md)).

### Hold (reserva temporal)

Estat principalment a **Redis**; opcionalment taula d’auditoria `hold_audit` amb `hold_id`, `event_id`, `expires_at`, `seat_ids[]`, `anonymous_session_id` (o cookie opaca), flag **`login_grace_applied`** (bool) per permetre només **una** extensió de **+120 s** al TTL.

- **Regla**: fins a **6** `seat_id` per hold; **sense pròrroga genèrica**; **excepció**: una vegada per hold, **+120 s** al TTL quan l’usuari **inicia login/registre des del checkout** (vegeu [spec.md](./spec.md) FR-003); alliberament en expiració, en **denegació final** («Seient ja no disponible») o després de pagament confirmat / cancel·lació.
- **Anònim vs autenticat**: es pot crear i mantenir un hold **sense** `user_id` (vinculació per sessió anònima fins al pas de login); la comanda i el pagament requereixen **usuari autenticat** (FK `user_id` a `orders`).
- **Pending Payment**: mentre la comanda està en aquest estat, el hold **roman** a Redis fins al **TTL**; si el pagament no es confirma abans, alliberament automàtic dels seients.

### Order / OrderLine

| Camp | Tipus | Notes |
|------|--------|--------|
| order | id, user_id (Comprador, obligatori en comandes reals), event_id | El flux hold→login pot crear la comanda només després d’autenticació |
| order.state | enum | Inclou **`pending_payment`** (passarel·la iniciada, hold actiu), més `paid`, `cancelled`, `failed`, etc. segons negoci |
| order_line | order_id, seat_id, unit_price | Una línia per seient |

**Flux resum**: hold creat → usuari inicia pagament → `pending_payment` → webhook o callback confirma → `paid` (o fallida / timeout → alliberar seients i tancar comanda).

### Ticket (entrada / credencial)

| Camp | Tipus | Notes |
|------|--------|--------|
| id | UUID | PK; pot coincidir o enllaçar amb claim `jti` del JWT |
| public_uuid | UUID únic | Embegut al QR / payload visible; vinculació traçable a la fila (**FR spec**) |
| order_line_id | FK | **Un ticket per seient** |
| status | enum | **`venuda`** (o `issued` / `paid`) fins validació; **`utilitzada`** després del primer ús vàlid |
| qr_payload_ref | string | Hash o referència JWT |
| jwt_expires_at | timestamptz | TTL **15 min** des de generació o regla de sessió (spec) |
| used_at | timestamptz nullable | Omplert en validació |
| validator_id | FK → User nullable | Rol **Validador** |

### User (PostgreSQL / Laravel)

Taula **`users`** (migració Laravel estàndard estesa): identificador estable, email únic, hash de contrasenya, timestamps; rols mitjançant **`spatie/laravel-permission`**: **Usuari** (per defecte), **Validador**, **Administrador**. L’**Assistent** és un rol conceptual (titular del bitllet); pot ser el mateix `user_id` que el comprador o un destinatari d’una **transferència**.

### friend_invites (invitacions d’amistat)

| Camp | Tipus | Notes |
|------|--------|--------|
| id | UUID | PK |
| sender_id | FK → users | Usuari que envia la invitació |
| receiver_id | FK → users nullable | Destinatari ja registrat (mutuament exclusiu amb `receiver_email` segons regles d’aplicació) |
| receiver_email | string nullable | Correu per convidar **usuaris nous** (quan encara no tenen compte) |
| status | enum | `pending`, `accepted`, `rejected` |
| invite_token | string nullable | Token segur per a enllaç d’acceptació (opcional segons flux) |
| created_at / updated_at | timestamptz | |

**Regles**: o bé `receiver_id` (usuari existent) o bé `receiver_email` (convidat nou), amb validació d’integritat al backend. Les respostes **acceptat** / **rebutjat** actualitzen `status`.

### Transferència de tickets (després d’amistat acceptada)

Taula **`ticket_transfers`** (o columnes a `tickets`): vinculació `ticket_id`, `from_user_id`, `to_user_id`, estat. **Lògica**: només es permet la transferència quan existeix relació d’amistat vàlida (p. ex. invitació **acceptada** entre els usuaris implicats). El **servidor** (Laravel) és la **font de veritat**: **invalida** el JWT/QR antic del titular anterior i **genera** credencial nova (JWT ticket + **SVG**) per al destinatari.

**Funcions socials (abast mínim)**: camp **`username`** únic (cerca social); **activitat** visible segons **privacitat** (flags per usuari).

### Preferències i guardats

| Entitat | Camps (idea) | Notes |
|---------|----------------|-------|
| **user_settings** | `user_id`, `gemini_personalization_enabled` (bool, default true) | **FR-024**: opt-out recomanacions IA |
| **saved_events** (o `event_favorites`) | `user_id`, `event_id`, `created_at` | **Guardats** / icona cor al buscador (**FR-023**) |

### Sincronització Ticketmaster (admin)

Taula o log **`tm_discovery_sync`** (opcional): cursor Feed 2.0, darrera execució, errors; els esdeveniments importats es mapen a `events` + `venues` amb `external_tm_id`.

### Validation (registre d’ús)

Implementació mínima: columnes `used_at`, `validator_id` a `tickets`. Opcional: taula `validation_events` (`id`, `ticket_id`, `validator_id`, `occurred_at`, `device_info`).

## Transicions d’estat (ticket)

1. Es genera després de pagament confirmat: estat **venuda** (entrada vàlida però encara no usada al recinte); JWT + SVG disponibles; TTL JWT segons spec.  
2. **utilitzada**: només després de **POST** de validació amb rol Validador i connexió OK; transició atòmica **venuda → utilitzada**; mateix QR invàlid per segon ús.  
3. Reintents: rebutjat si `status = utilitzada` o `used_at` no nul.

## Transicions d’estat (comanda)

- Cap a **`pending_payment`**: hold encara vàlid a Redis; espera passarel·la.  
- **paid**: pagament confirmat; tickets creats en estat **venuda**.  
- **cancel·lació / timeout de pagament**: alliberar seients (Redis + `Seat.status`); sense tickets vàlids o revocats segons regles.

## Redis (hold)

- Patró de clau: `hold:{eventId}:{holdUuid}` amb TTL = `hold_ttl_seconds` (per defecte **240** si l’esdeveniment usa la línia base); **`PEXPIRE`** o increment controlat de TTL per aplicar **+120 s** una vegada (`login_grace_applied` a hash Redis o camp en auditoria).  
- Membres: ids de seients; verificació d’intersecció abans de crear.  
- Sincronització amb PostgreSQL: després d’operacions de negoci (hold creat / alliberat / venut), l’estat autoritatiu dels seients és la **base de dades**; Redis reflecteix el TTL del hold de sessió.

## Fallback Top Picks (model)

Quan no hi ha `snapshotImageUrl` TM: llegir **zones / seients** des de PostgreSQL amb ordre per **PostGIS** (`ST_Distance`, etc.) o **sort_order** / taules de criteri manual; cap zona inventada.
