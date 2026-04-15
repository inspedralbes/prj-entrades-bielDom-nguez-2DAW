## Why

El panell d’administració ha de convertir-se en el **centre de control** de la plataforma: salut operativa en viu, catàleg d’esdeveniments (import Ticketmaster Discovery i edició manual), **monitoratge per esdeveniment** amb el **mateix mapa de seients** i mètriques que veu l’usuari, gestió d’usuaris i compres, i **informes** d’analítica. Sense això, l’equip no pot supervisar vendes, holds, sincronització TM ni ocupació amb la precisió que demana el negoci.

La visualització **temps real** ha d’aprofitar **Socket.IO**, **Redis** i **Pinia** de forma coherent amb **Laravel + PostgreSQL** com a font de veritat (alineació FR-014 / constitució del monorepo).

## What Changes

- **Dashboard global (pàgina A)**: comptador d’usuaris connectats **en viu** (Socket.IO); **ingressos del dia** (suma des de 00:00, TZ definida al disseny); comptador de comandes **`pending_payment`**; **alertes** de fallades o anomalies en la importació / sync Discovery Ticketmaster.
- **CRUD esdeveniments (pàgina B)**: cercador integrat Discovery API + acció **Importar** cap a PostgreSQL (nom, data, imatge, recinte…); creació i edició manual; **preu** administrable; **eliminació lògica** (`hidden_at`) sense esborrar historial.
- **Monitoratge esdeveniment (secció temps real)**: mini-mapa amb **els mateixos estats** que la vista usuari (lliure / hold / venut), actualitzat per socket; comptadors venuts / restants / llista de **holds Redis** amb TTL; **recaptació total** de l’esdeveniment.
- **Usuaris i tiquets (pàgina D)**: CRUD usuaris (crear amb rol, eliminar definitiu segons política FK); **historial** de comandes i tiquets per usuari (incloent validació i transferències si el model ho té).
- **Informes (pàgina C)**: gràfic de línies (evolució temporal / pics); gràfic circular **ocupació** (venuts vs aforament).
- **Rols**: la consola admin queda definida per a **`admin`**; **no** s’inclouen fluxos de **validador** dins d’aquest panell (el producte pot mantenir `POST /validation/scan` fora d’aquest abast UI).

## Capabilities

### New Capabilities

- `admin-dashboard-global`: Mètriques globals, vendes dia, pending_payment, alertes sync TM, presència usuaris (socket).
- `admin-events-crud-discovery`: Cerca Discovery + import, CRUD manual, preu, soft-hide `hidden_at`.
- `admin-event-realtime-monitor`: Mapa seients + comptadors + holds + finances per esdeveniment (socket + Pinia).
- `admin-users-tickets-crud`: Gestió usuaris i historial transaccional complet.
- `admin-reports-analytics`: Línies temporal i ocupació circular.

### Modified Capabilities

- **API admin** (`routes/api.php` prefix `admin`): estendre `AdminController` / nous serveis i recursos; possible extensió **OpenAPI** (`specs/001-seat-map-entry-validation/contracts/openapi.yaml` o delta dedicada).
- **socket-server**: esdeveniments / rooms per presència global i reutilització del canal de mapa per vista admin (o namespace dedicat amb mateixa càrrega de dades).
- **frontend-nuxt** `pages/admin/*`, `layouts/admin.vue`, stores Pinia: completar vistes existents (`index`, `events`, `users`, `reports`) i afegir ruta de **detall/monitor** per esdeveniment si cal.

## Impact

- **Backend (Laravel)**: nous endpoints o ampliació de `summary`, consultes agregades, logs d’error de sync, polítiques d’accés `role:admin`.
- **Base de dades**: possible taula o camps per **alertes** / **audit sync**; confirmar `hidden_at`, estats de comanda, tickets (ja existents).
- **Redis / jobs**: reutilitzar holds existents; possible **comptador de presència** (clau Redis + broadcast).
- **socket-server**: handlers i subscripció Redis alineats amb el que ja publica Laravel.
- **Frontend**: components de mapa reutilitzats del flux usuari; gràfics (p. ex. Chart.js / Apex segons estàndard del repo).
- **Documentació**: `openspec/specs/admin-platform-suite/spec.md` com a font de requisits; aquest change com a pla d’execució.

## Flux d’entrega (branques, proves, merge)

Cada **pàgina / bloc** de la consola admin es lliura amb el cicle definit a **`WORKFLOW.md`** (mateixa carpeta d’aquest change):

1. Branca nova des de **`dev`** amb el prefix recomanat (`feature/admin-platform-…`).
2. Implementació **completa** del bloc (sense funcionalitats a mitges).
3. **Verificació al 100%**: prova manual al **navegador** (frontend admin) + **tests backend** (i socket si aplica) tot passant.
4. **Commit**, **push**, **merge** a `dev`.
5. Marcar com **`[x]`** al `tasks.md` totes les sub-tasques del bloc tancat; passar al següent bloc amb una **nova branca des de `dev`**.

No es fusiona a `dev` amb tests fallant ni es donen per fetes tasques sense el codi i les proves corresponents. Detall i mapatge A–E ↔ seccions del `tasks.md`: vegeu **`WORKFLOW.md`**.
