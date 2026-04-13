## Context

El projecte actual és una aplicació Vue.js/Laravel per a gestió d'esdeveniments amb sincronització parcial de Ticketmaster (aproximadament 200 esdeveniments propers a la localització de l'usuari). L'objectiu és escalar-ho a una plataforma nacional per a Espanya completa.

**Estat actual:**
- Frontend: Nuxt.js amb pàgines existents per a inici, cerca, mapa, checkout
- Backend: Laravel API amb endpoints per a cerca, sincronització Ticketmaster, compres
- Base de dades: PostgreSQL amb PostGIS per a cerca geoespacial
- Sincronització: Script que consumeix Ticketmaster API però limitat a events propers

**Restriccions tècniques existents:**
- API de Google Maps integrada
- Gemini (Google) per a recomanacions
- PostgreSQL amb PostGIS per a geolocalització
- Stack: Nuxt 3 + Laravel + PHP 8.x

**Stakeholders:**
- Equip de desenvolupament (frontend + backend)
- Product Owner (definicions de funcionalitat)
- Usuaris finals (consumidors d'entrades)

## Goals / Non-Goals

**Goals:**
- Crear un catàleg nacional complet d'esdeveniments a Espanya (10 categories: party, dj, social, comedy, film, theatre, art, sport, talk, concerts)
- Implementar sincronització completa de Ticketmaster ES amb paginació i persistència de preu/URL
- Afegir filtre de proximitat opt-in a la pàgina d'inici amb persistència
- Unificar filtres entre /search i /search/map
- Crear pantalla de detall d'esdeveniment (/events/:id) amb mapa, modal i footer
- Crear pantalla de selecció de quantitat (/events/:id/seats) amb input number 1-6
- Crear pantalla de tiquets (/tickets) amb swipe horitzontal
- Exposar endpoint GET /api/events/{id} per a detall d'esdeveniment

**Non-Goals:**
- Selecció individual de seients (marcat com a fase futura)
- Integració de pagament completa (placeholder actualment)
- Altres països beyond Espanya
- Museus i oferta cultural genèrica fora de les categories especificades

## Decisions

### D1: Catàleg per defecte = tota Espanya (no радиус)

**Decision:** Canviar el comportament per defecte de la cerca per mostrar tota Espanya en lloc de només esdeveniments propers.

**Rationale:** L'objectiu és una plataforma nacional, no local. La proximitat passa a ser un filtre opt-in, no el predeterminat.

**Alternative considered:** Mantenir cerca local + opció de "cerca nacional" - descartat perquèvae en contra de l'objectiu de plataforma nacional.

### D2: Sincronització Ticketmaster amb paginació completa

**Decision:** Modificar el script de sync per recórrer totes les pàgines de Ticketmaster per a Espanya (no només les properes).

**Rationale:** El catàleg nacional requereix tots els esdeveniments de les categories especificades, no només un subconjunt.

**Alternative considered:** Sincronització incremental sota demanda - descartat per volum de dades a terme.

### D3: Persistència del filtre de proximitat amb localStorage

**Decision:** Utilitzar localStorage per mantenir l'estat del filtre de proximitat entre refreshos, però reset en canvi de ruta.

**Rationale:** Comportament requerit: "en navegar fora i tornar, filtre off; amb F5, filtre persistit".

**Alternative considered:** localStorage + URL query params - complicat de sincronitzar.

### D4: Google Maps pin groc per a detall d'esdeveniment

**Decision:** Utilitzar marcador personalitzat de color groc (no el blau per defecte) a la pantalla de detall.

**Rationale:** Diferenciació visual respecte al mapa de cerca i requisit explícit del document de pla.

### D5: Input number (1-6) sense dropdown per a selecció de quantitat

**Decision:** Utilitzar input tipus number amb rang 1-6 per selecció de tiquets.

**Rationale:** Requisit explícit: "input number 1–6 (no dropdown)".

### D6: Swipe horitzontal per a tiquets

**Decision:** Implementar scroll horitzontal (swiper) per navegar entre tiquets a la pantalla /tickets.

**Rationale:** Requisit explícit del document de pla.

### D7: Endpoint GET /api/events/{id} público

**Decision:** Crear endpoint REST pública per obtenir detall d'un esdeveniment.

**Rationale:** La pantalla de detall (/events/:id) necessita obtenir les dades des del backend.

## Risks / Trade-offs

**R1: volum de dades Ticketmaster** → Mitigació: Implementar paginació progressiva, possiblement job queue per a sync en background

**R2: Rendiment amb catàleg gran** → Mitigació: Implementar lazy loading, caché, possibly paginació a la cerca

**R3: Cost API Google Maps** → Mitigació: Utilitzar marcador estàtic (no interactive) a la llista, només interactive al modal

**R4: Geolocalització PostGIS** → Mitigació: Assegurar que les coordenades dels venues de Ticketmaster es persistixen correctament

## Migration Plan

1. **Fase 1**: Crear estructura de carpetes per a nous components Vue
2. **Fase 2**: Implementar sincronització Ticketmaster amb paginació (backend)
3. **Fase 3**: Crear endpoint GET /api/events/{id} (backend)
4. **Fase 4**: Implementar pàgina /events/:id (frontend)
5. **Fase 5**: Implementar pàgina /events/:id/seats (frontend)
6. **Fase 6**: Modificar / per catàleg nacional + botó proximitat
7. **Fase 7**: Unificar filtres a /search i /search/map
8. **Fase 8**: Implementar /tickets amb swipe

**Rollback:** Branching amb feature flags per a cada canvi; possibilitat de revert individual sense afectar tot el change.

## Open Questions

- Quina estratègia de caché implementar per a les cerques del catàleg?
- Com gestionar la latència de la sincronització de Ticketmaster (batch vs real-time)?
- Cal suport multi-idioma per a les dades de Ticketmaster o només castellà/anglès?
- Com fer el match exacte de venues de Ticketmaster amb la taula de venues a la base de dades?