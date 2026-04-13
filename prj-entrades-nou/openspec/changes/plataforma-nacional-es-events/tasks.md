## 1. Backend - Sincronització Ticketmaster

- [x] 1.1 Modificar el script de sync per utilitzar Ticketmaster Discovery Feed 2.0
- [x] 1.2 Implementar paginació completa per a tots els esdeveniments d'Espanya
- [x] 1.3 Afegir filtre per categories (party, dj, social, comedy, film, theatre, art, sport, talk, concerts)
- [x] 1.4 Persistir camp de preu des de Ticketmaster
- [x] 1.5 Persistir URL de ticketing des de Ticketmaster
- [x] 1.6 Persistir coordenades PostGIS (map_lat, map_lng) dels venues
- [x] 1.7 Persistir horari d'inici de l'esdeveniment
- [x] 1.8 Persistir informació completa del venue (nom, adreça, ciutat)

## 2. Backend - API

- [x] 2.1 Crear endpoint GET /api/events/{id} per obtenir detall d'un esdeveniment
- [x] 2.2 Modificar SearchEventsController per retornar map_lat/map_lng des de PostGIS
- [x] 2.3 Implementar endpoint per filtre de proximitat (nearby) amb paràmetre km

## 3. Frontend - Home (/ )

- [x] 3.1 Modificar la crida API per mostrar catàleg nacional (tota Espanya) per defecte
- [x] 3.2 Implementar botó de proximitat a la part superior de la pàgina
- [x] 3.3 Afegir selector de radi (km) quan el filtre de proximitat està actiu
- [x] 3.4 Implementar persistència del filtre amb localStorage (manté en refresh)
- [x] 3.5 Implementar reseteig del filtre en canviar de ruta (navegar fora i tornar)
- [x] 3.6 Pre-seleccionar categories party, dj, concerts per defecte

## 4. Frontend - Cerca (/search) i Mapa (/search/map)

- [x] 4.1 Implementar filtres unificats: dia, ubicació text, text lliure, categories
- [x] 4.2 Crear component de calendari per selecció de dia
- [x] 4.3 Crear component de cerca d'ubicació amb suggeriments (dropdown)
- [x] 4.4 Unificar filtres entre /search i /search/map
- [x] 4.5 Assegurar que el mapa mostri el mateix subconjunt que la llista

## 5. Frontend - Pantalla de Detall (/events/:id)

- [x] 5.1 Crear nova pàgina /events/[eventId]/index.vue
- [x] 5.2 Implementar botó de guardar (save/favorite)
- [x] 5.3 Mostrar informació completa: nom, descripció, hora d'inici, ubicació
- [x] 5.4 Integrar Google Maps amb marcador de color groc
- [x] 5.5 Implementar modal per mostrar mapa enlargit
- [x] 5.6 Afegir botó "Open Google Maps" que obri URL externa
- [x] 5.7 Implementar footer fixe amb preu a l'esquerra i "Get Tickets" a la dreta
- [x] 5.8 Implementar crida a GET /api/events/{id} per obtenir dades

## 6. Frontend - Selecció de Quantitat (/events/:id/seats)

- [x] 6.1 Crear nova pàgina /events/[eventId]/seats.vue (simplificada - input quantitat)
- [x] 6.2 Implementar input de tipus number (1-6) per seleccionar quantitat
- [x] 6.3 Implementar càlcul de total en viu (live total)
- [x] 6.5 Implementar botó "Comprar" que navegui al checkout

## 7. Frontend - Tiquets (/tickets)

- [x] 7.1 Modificar /tickets per mostrar swipe horitzontal de tiquets
- [x] 7.2 Mostrar informació de l'esdeveniment a sota de cada tiquet
- [x] 7.3 Implementar footer fixe amb botó "Enrere" i hora de l'esdeveniment
- [x] 7.4 Crear pàgina /tickets/[ticketId] per mostrar detall amb QR (ja existent)

## 8. Verificació i Testing

- [ ] 8.1 Verificar que el catàleg mostra tots els esdeveniments d'Espanya
- [ ] 8.2 Verificar que el botó de proximitat funciona correctament
- [ ] 8.3 Verificar persistència en refresh i reseteig en navegar
- [ ] 8.4 Verificar que /search i /search/map tenen els mateixos filtres
- [ ] 8.5 Verificar que el mapa mostra el mateix subconjunt que la llista
- [ ] 8.6 Verificar que la pantalla de detall mostra mapa groc i modal
- [ ] 8.7 Verificar que input number funciona correctament (1-6)
- [ ] 8.8 Verificar que el total es calcula en viu
- [ ] 8.9 Verificar swipe horitzontal a /tickets