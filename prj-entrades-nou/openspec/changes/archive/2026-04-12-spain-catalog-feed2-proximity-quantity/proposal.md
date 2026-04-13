## Why

El catàleg actual només mostra esdeveniments propers a l'usuari (Barcelona per defecte). Els usuaris no poden descobrir esdevenimentsgrans a tot Espanya ni comprar entrades de forma ràpida sense seleccionar seients individuals al mapa.

## What Changes

- **Feed 2.0 Ticketmaster**: Importar esdeveniments de **tota Espanya** des del Discovery Feed 2.0 (categories: Party, DJ, Social, Comedy, Film, Theatre, Art, Sport, Talk, Concerts)
- **Filtratge dual (global/proximitat)**: Vista global per defecte a Home + botó per activar filtre de proximitat amb selector de radi (km)
- **Mapa d'Espanya complet**: Marcadors de tots els esdeveniments importats a la vista de cerca
- **Dropdown de ciutats**: Cercador amb autocompletat per localitzar ciutats (ex: "Madrid", "València")
- **Compra per quantitat**: Input number (1-6 entrades) en lloc de mapa de seients, preu dinàmic calculat al frontend
- **Generació de tickets múltiples**: Un cop comprat, generar tants tickets com la quantitat seleccionada

## Capabilities

### New Capabilities
- `spain-catalog-feed2`: Importació d'esdeveniments d'Espanya des del TM Discovery Feed 2.0 amb filtrat de categories (excloent museus i esdeveniments petits)
- `proximity-filter`: Filtratge d'esdeveniments per radi de proximitat utilitzant PostGIS, amb persistència en refresh de Home
- `quantity-purchase`: Sistema de compra basat en quantitat (1-6 entrades) amb càlcul dinàmic de preu total
- `city-dropdown-search`: Cercador de ciutats amb autocompletat i desplaçament del mapa a la localització seleccionada

### Modified Capabilities
- (Cap requerint canvi a nivell de especificació - els canvis són implementació nova)

## Impact

- **Backend (Laravel)**: Nova integració amb TM Feed 2.0, consultes PostGIS per proximitat, adaptació del model d'orders per a compres sense seat_id
- **Frontend**: Nova UI per al filtre de proximitat, dropdown de ciutats, selector de quantitat, actualització dinàmica de preu
- **Database**: Nous camps a `events` (URL externa TM, taxonomia de categoria), adaptació d'`orders` per quantity, `venues` amb location PostGIS
- **APIs**: Actualitzar `contracts/openapi.yaml` pel nou flux de compra
- **Realtime**: Socket.IO per a notificacions d'esdeveniments amb poques entrades disponibles