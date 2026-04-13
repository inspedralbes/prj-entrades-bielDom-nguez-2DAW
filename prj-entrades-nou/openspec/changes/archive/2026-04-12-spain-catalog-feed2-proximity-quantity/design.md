## Context

El projecte actual disposa d'un catàleg d'esdeveniments que mostra esdeveniments propers a l'usuari (Barcelona per defecte) utilitzant el sistema de reserves amb mapa de seients. El nou requeriment demanda:

1. **Expandir el catàleg a Espanya**: Utilitzar el Ticketmaster Discovery Feed 2.0 per importar esdeveniments de tot el territori espanyol, filtrats per categories de lleure massiu (Party, DJ, Social, Comedy, Film, Theatre, Art, Sport, Talk, Concerts), excloent museus i esdeveniments petits.

2. **Filtratge dual**: Permitir vista global (tots els esdeveniments d'Espanya) i filtres de proximitat amb selector de radi en kilòmetres.

3. **Compra per quantitat**: Substituir el mapa de seients per un input de quantitat (1-6 entrades) amb càlcul dinàmic de preu total.

4. **Cercador de ciutats**: Dropdown amb autocompletat per localitzar ciutats i desplaçar el mapa.

El backend actual (Laravel) utilitza PostGIS per a consultes de proximitat. El frontend és Vue.js. La base de dades és PostgreSQL amb PostGIS.

## Goals / Non-Goals

**Goals:**
- Implementar la integració amb TM Discovery Feed 2.0 per a esdeveniments d'Espanya
- Afegir filtre de proximitat amb selector de radi (km) a la Home
- Crear el mapa d'Espanya complet amb tots els esdeveniments importats
- Implementar dropdown de ciutats amb autocompletat
- Substituir el flux de compra per seients per un flux basat en quantitat (1-6 entrades)
- Generar múltiples tickets a partir d'una compra per quantitat
- Implementar Socket.IO per a notificacions de stock baix

**Non-Goals:**
- Eliminar el sistema de mapa de seients per a altres tipus d'esdeveniments (coexistiran els dos modes)
- Implementar procés de pagament (ja existent)
- Modificar el sistema d'autenticació d'usuaris
- Canviar la interfície de gestió d'esdeveniments per administradors

## Decisions

### D1: Model de dades per a Feed 2.0

**Decisió:** Extendre la taula `events` existent amb nous camps per a URL externa de Ticketmaster i taxonomia de categoria simplificada.

**Alternatives considerades:**
- Nova taula `tm_events`: Risc de duplicació de dades i inconsisistència
- Extendre `events`: Manté la font de veritat única

**Rationale:** Mantenir la coherència amb l'arquitectura actual on `events` és la font principal. Afegir camps: `tm_url` (VARCHAR), `tm_category` (ENUM), `is_large_event` (BOOLEAN).

### D2: Implementació del filtre de proximitat

**Decisió:** Utilitzar consultes PostGIS amb ST_DWithin per al filtratge per radi. El selector de radi s'implementarà com un component UI separat de la cerca principal.

**Alternatives considerades:**
- Client-side filtering: Menys càrrega al servidor però menys precís
- Server-side filtering amb PostGIS: Més precís, aprofita la infraestructura existent

**Rationale:** PostGIS ja està configurat i funcional. ST_DWithin és eficient per a consultes de proximitat.

### D3: Estat del filtre de proximitat

**Decisió:** L'estat del filtre (actiu/inactiu, radi seleccionat) es manté al URL params o localStorage. Es persistirà en refresh de la pàgina Home, però es resetejarà en navegar a altres seccions.

**Alternatives considerades:**
- URL params: Més compartible, SEO friendly
- localStorage: Més ràpid, menys dependència del servidor

**Rationalle:** URL params permet compartir enllaços amb filtres especifics.

### D4: Flux de compra per quantitat

**Decisió:** Afegir camp `quantity` a la taula `orders`. Quan `quantity` està poblat, no cal `seat_id`. Els tickets es generaran automàticament basant-se en la quantitat.

**Alternatives considerades:**
- Nova taula `order_items`: Més complexitat
- Camp quantity a orders: Més senzill, coherent amb el model actual

**Rationale:** Manté la simplicitat del model existent. El camp `quantity` permet distingir entre compres de seients (quantity=NULL, seat_id=set) i compres per quantitat (quantity>0, seat_id=NULL).

### D5: Autocompletat de ciutats

**Decisió:** API endpoint dedicat (`GET /api/cities/search?q=`) que retorni ciutats amb coordinate. El frontend farà servir debounce per a evitar crides excessives.

**Alternatives considerades:**
- Google Places API: Cost addicional, overkill pel cas d'ús
- Taula pròpia de ciutats: Més control, més manteniment

**Rationale:** Ticketmaster API ja retorna informació de ciutats als esdeveniments. Podem extreure i caché aquesta informació.

### D6: Notificacions de stock baix (Socket.IO)

**Decisió:** Quan el stock d'un esdeveniment baixa d'un llindar (configurable, ex: 10 entrades), el backend emetrà un esdeveniment global `low-stock` amb l'ID de l'esdeveniment i les unitats restants.

**Alternatives considerades:**
- Polling des del frontend: Menys eficient
- Webhooks a tercers: No aplicable

**Rationale:** Socket.IO ja està integrat al projecte per a altres funcionalitats.

## Risks / Trade-offs

### R1: Volum de dades a Espanya

**Risc:** El nombre d'esdeveniments importats d'Espanya pot ser molt gran, afectant el rendiment.

**Mitigació:** Implementar paginació a les consultes, lazy loading al mapa, caché a les consultes de proximitat més frequents.

### R2: Coordinació frontend/backend

**Risc:** Canvis simultanis al frontend (selector de quantitat, mapa) i backend (nova API, canvis al model) poden desincronitzar-se.

**Mitigació:** Contractes d'API definits a `openapi.yaml` abans de la implementació. Tests d'integració E2E.

### R3: Migració de dades

**Risc:** Canviar el model d'orders per permetre null a `seat_id` quan hi ha `quantity`.

**Mitigació:** Nova migració de base de dades amb valors per defecte. Script de migració per a dades existents.

### R4: Coexistència de modes de compra

**Risc:** Mantenir dos modes de compra (seients vs quantitat) pot complicar el codi i els tests.

**Mitigació:** Factoritzar la lògia de compra en un servei que gestioni ambdós casos. Tests específics per a cada mode.

### R5: Performance del mapa

**Risc:** Renderitzar milers de marcadors al mapa de Leaflet pot ser lent.

**Mitigació:** Utilitzar clusterització de marcadors (Leaflet.markercluster), carregar només els marcadors visibles al viewport.

## Migration Plan

1. **Fase 1 - Backend:**
   - Migració de base de dades (nous camps a events, quantity a orders)
   - Nou endpoint per a Feed 2.0 (sincronització)
   - Nou endpoint de cerca de ciutats
   - Actualitzar consultes per suportar filtratge per radi

2. **Fase 2 - Frontend:**
   - Component de selector de quantitat
   - Component de filtre de proximitat
   - Integració amb el mapa (clusterització)
   - Dropdown de ciutats

3. **Fase 3 - Integració:**
   - Actualitzar OpenAPI contract
   - Tests E2E del nou flux
   - Validar rendiment

4. **Fase 4 - Desplegament:**
   - Deploy progressiu (feature flag)
   - Rollback plan: Desactivar feature flag

## Open Questions

- **Q1:** Com es gestionarà el stock d'entrades des de Ticketmaster? És real-time o cal actualitzar periòdicament?
- **Q2:** Quin llindar de stock baix es farà servir per a les notificacions Socket.IO? (Proposta: 10 entrades)
- **Q3:** El filtre de proximitat s'aplicarà només a la Home o també a la cerca? (Segons FR: independent)
- **Q4:** Com es gestionarà la persistència del selector de radi? URL params o localStorage?