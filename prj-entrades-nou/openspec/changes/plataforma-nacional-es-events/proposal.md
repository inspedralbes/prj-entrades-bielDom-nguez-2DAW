## Why

El catàleg actual mostra esdeveniments limitats al radi proper a l'usuari (~200 esdeveniments), centrat en la cerca local. L'objectiu és créixer cap a una plataforma nacional d'esdeveniments a Espanya amb catàleg complet de tot el territori, sincronització completa de Ticketmaster, i noves pantalles per a detall, selecció de quantitat i gestió d'entrades.

## What Changes

- **Nou catàleg per defecte**: tota Espanya (10 categories: party, dj, social, comedy, film, theatre, art, sport, talk, concerts), no museus ni oferta cultural genèrica
- **Sync Ticketmaster ES complet**: paginació completa, persistir preu, URL i payload complet; eliminar limitació "propers" i ~200
- **Filtre proximitat a inici**: botó superior (km) que activa/desactiva filtre; reset en navegar fora; persistit en refresh (localStorage)
- **/search i /search/map unificats**: mateixos filtres (dia, ubicació text, text, categories); mapa mostra mateix subconjunt que llista
- **Nova pantalla /events/:id**: detall amb guardar, info, hora, ubicació, mapa Google pin groc, modal mapa mitjà, "Open Google Maps", footer fixe: preu | Get Tickets
- **Nova pantalla /events/:id/seats**: input number 1-6 (no dropdown), total en viu, sense selecció de seients individuals
- **Nova pantalla /tickets**: swipe horitzontal de tiquets per esdeveniment, info sota, footer enrere + hora
- **Backend nou**: GET /api/events/{id} público si no existeix, preus reals des de Ticketmaster, map_lat/map_lng des de PostGIS

## Capabilities

### New Capabilities
- `catalogo-nacional`: Catàleg per defecte de tota Espanya amb les 10 categories especificades
- `sync-ticketmaster-es`: Sincronització completa de Ticketmaster per a Espanya amb paginació i persistència de preu/URL
- `filtro-proximidad-inicio`: Botó de proximitat a la pàgina d'inici amb reset en tornar i persistència en refresh
- `filtros-unificados`: Filtres unificats entre /search i /search/map (dia, ubicació, text, categories)
- `pantalla-detalle-event`: Nova pantalla de detall d'esdeveniment amb mapa, modal i footer
- `pantalla-seleccion-cantidad`: Pantalla de selecció de quantitat (1-6) sense selecció de seients
- `pantalla-tickets-usuario`: Pantalla de tiquets amb swipe horitzontal i info d'esdeveniment

### Modified Capabilities
- (none)

## Impact

- **Frontend**: Nova pàgina `/events/:id`, nova pàgina `/events/:id/seats`, modificació `/` per mostrar catàleg nacional, nova estructura `/tickets`
- **Backend**: Nou endpoint `GET /api/events/{id}`, extensió de sincronització Ticketmaster, consulta PostGIS per map_lat/map_lng
- **Base de dades**: Persistència de preu i URL per esdeveniments Ticketmaster
- **APIs**: Extensió de filtres per a cerca i mapa