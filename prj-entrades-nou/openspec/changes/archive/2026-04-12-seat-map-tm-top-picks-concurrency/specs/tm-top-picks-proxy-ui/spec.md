# Delta spec — `tm-top-picks-proxy-ui`

## Requirements

### R-MAP-01 — Proxy sense claus al client

- El navegador **no** ha de cridar dominis públics de Ticketmaster amb clau API.  
- Totes les crides a Top Picks es fan des de **Laravel** amb credencials només a servidor (`TICKETMASTER_API_KEY` o equivalent).

### R-MAP-02 — Dades del mapa per a la UI

- L’API ha de retornar com a mínim:
  - `snapshotImageUrl` (URL HTTPS vàlida per a `<img>` o descàrrega proxy opcional).
  - Llista de **zones** amb:
    - identificador estable (`zone_id` o id TM mapejat),
    - geometria per SVG (p. ex. punts del polígon en coordenades **normalitzades 0–1** respecte la imatge, o format acordat explícitament al contracte),
    - **disponibilitat** numèrica per zona; valor **0** significa *sold out* per a aquesta zona en el model de negoci.

### R-MAP-03 — Frontend Nuxt 3

- Mostrar la **imatge completa** del recinte com a capa de fons.  
- Superposar **SVG** interactiu (una forma per zona) alineat amb la imatge (mateix aspect ratio / viewBox).  
- Al **clic** en una zona amb disponibilitat > 0: canviar estat de vista a **zoom** i carregar la **rejilla de seients** de la zona mitjançant endpoint intern (no Ticketmaster directe).  
- Si disponibilitat **0**: la zona es renderitza en **vermell** (o token de tema del projecte), amb **cap interacció de clic** que obri la rejilla (equivalent a desactivar selecció).

### R-MAP-04 — Errors

- Si Ticketmaster falla o no hi ha dades: resposta HTTP amb codi i missatge user-safe; la UI mostra estat d’error sense revel·lar secrets.

## Acceptance criteria

- [ ] Cap petició del bundle Nuxt inclou cap header/query amb clau TM.  
- [ ] Es pot seleccionar una zona vàlida i passar a vista de rejilla; zona sold out no és seleccionable.  
- [ ] Contracte OpenAPI documenta el schema JSON del mapa.
