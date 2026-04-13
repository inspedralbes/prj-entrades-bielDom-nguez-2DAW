# Delta spec — `seat-realtime-socket`

## Requirements

### R-RT-01 — Room per esdeveniment

- Tots els clients connectats al **mapa del mateix esdeveniment** han d’estar a una **room** Socket.IO identificada per `eventId` (convenció existent del repo: p. ex. `event:{eventId}` al canal públic, o la que ja implementi `socket-server`).

### R-RT-02 — Flux Laravel → Redis → Node

- Després que Laravel validi un canvi d’estat de seient rellevant (hold, alliberament, venut), ha de **publicar** a Redis (Pub/Sub) un missatge en el format que consumeixi el `socket-server`.  
- El **Node** ha de rebre i fer **broadcast** als sockets subscrits a la room d’aquest `eventId`.

### R-RT-03 — Actualització visual

- Els clients han d’actualitzar sense recarregar:
  - color / estat del seient (p. ex. disponible → reservat / held),
  - opcionalment **comptador** de zona si el payload ho inclou.

### R-RT-04 — Missatge de conflicte

- Quan un usuari perd la cursa per un seient, ha de rebre un esdeveniment amb missatge en català:
  - **«Aquest seient acaba de ser seleccionat per un altre usuari»**  
  (clau estable al payload per internacionalització futura si cal).

### R-RT-05 — Post-pagament

- Quan el seient passa a **venut** (`sold`), el broadcast ha de reflectir l’estat **final** (p. ex. color vermell permanent) per a tots els clients de la room.

## Acceptance criteria

- [ ] Dos navegadors al mateix `eventId` veuen actualització en temps real després d’una reserva simulada.  
- [ ] El payload no inclou dades sensibles (claus, tokens complets de TM).  
- [ ] Es documenten noms d’esdeveniments Socket i canal Redis al `design.md` o README tècnic del canvi.
