# Delta spec — `seat-hold-concurrency`

## Requirements

### R-HOLD-01 — Transacció atòmica PostgreSQL

- En intentar **reservar** (hold) un seient, Laravel ha d’executar la lògica dins d’una transacció que inclogui **`SELECT … FOR UPDATE`** sobre la fila del seient (o conjunt mínim que garanteixi exclusió mútua segons esquema actual `seats`).

### R-HOLD-02 — Redis amb TTL 240 s

- Després de confirmar el canvi vàlid a PostgreSQL, s’ha d’escriure a **Redis** una representació del hold amb **TTL de 240 segons** (4 minuts).  
- Si el TTL expira sense completar compra, el sistema ha d’**alliberar** el hold de forma coherent amb el disseny existent (job, TTL callback, o lectura lazy en següent reserva — documentar la via triada).

### R-HOLD-03 — Límit de selecció (6 seients)

- El mateix usuari (o sessió de compra definida al domini) **no** pot tenir més de **6** seients seleccionats/hold simultanis per al mateix esdeveniment (o àmbit definit al pla d’implementació).  
- **Backend**: rebutjar amb 422 si es supera el límit.  
- **Frontend**: impedir afegir més de 6 abans d’enviar la petició.

### R-HOLD-04 — Conflicte de cursa

- Si dos clients intenten el mateix seient i un perd la transacció, el que perd ha de rebre una resposta clara (HTTP + codi estable) i ha de poder rebre també el missatge per **Socket.IO** (especificat a `seat-realtime-socket`).

### R-HOLD-05 — Estat venut (sold)

- En completar el pagament, el seient passa a estat **sold** (o equivalent `sold` al model) de forma **irreversible** només via PostgreSQL; Redis reflecteix alliberament de hold segons correspongui.

## Acceptance criteria

- [ ] Test automàtic o prova manual documentada que demostra bloqueig de fila i un sol guanyador.  
- [ ] TTL Redis configurable per env però per defecte **240**.  
- [ ] Validació 6 seients als dos costats (FE + BE).
