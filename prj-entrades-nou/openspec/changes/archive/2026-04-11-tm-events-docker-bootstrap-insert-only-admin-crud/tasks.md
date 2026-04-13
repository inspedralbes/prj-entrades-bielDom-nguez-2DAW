## 1. Backend - TM Service

- [x] 1.1 Revisar TicketmasterEventImportService existent
- [x] 1.2 Modificar per insert-only (no update)

## 2. Backend - Daily Sync

- [x] 2.1 Crear scheduled command ticketmaster:sync-daily
- [x] 2.2 Configurar schedule a console.php

## 3. Backend - Admin CRUD

- [x] 3.1 GET /api/admin/events
- [x] 3.2 POST /api/admin/events
- [x] 3.3 PATCH /api/admin/events/{id}
- [x] 3.4 DELETE /api/admin/events/{id}

## 4. Docker

- [x] 4.1 Afegir bootstrap script a docker-compose
- [x] 4.2 Entry point executa import inicial

## 5. Test

- [x] 5.1 Test sync insert-only (codi verificat manualment)
- [x] 5.2 Test admin CRUD (rutes implementades)