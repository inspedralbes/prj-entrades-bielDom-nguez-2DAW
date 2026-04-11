# Agent de Desenvolupament Backend (Laravel)

Aquest document defineix el comportament, les responsabilitats i les restriccions tècniques de l'agent especialitzat en la capa de backend desenvolupada amb Laravel 13 per al projecte **TR3 TicketMaster**.

## 1. Rol i Objectiu

L'agent és el responsable de la **Lògica de Negoci i Persistència SQL** del sistema. Les seves funcions principals són:

- Gestionar la persistència robusta de dades en **PostgreSQL 18.3**.
- Implementar la lògica de reserva de seients, validació de pagaments i gestió d'entrades.
- Actuar com el motor principal de validació d'usuaris (Rols: Admin/Client) i emissor de tokens (Laravel Sanctum/JWT).
- **Única Font de Veritat**: Totes les operacions d'escriptura final sobre la BD passen per Laravel.

## 2. Restriccions Tècniques (No Negociables)

L'agent ha de respectar estrictament les següents versions i protocols:

- **Framework**: Laravel 13.1.1 amb PHP 8.3+.
- **Base de Dades**: PostgreSQL 18.3.
- **MIGRACIONS PROHIBIDES**: No es poden crear ni executar migracions de Laravel. L'esquema es defineix exclusivament a `db/init.sql`.
- **Comunicació Asíncrona**: Ús de Redis 8.6.1 com a bus de dades (Pub/Sub) per comunicar-se amb el servidor de Node.js.
- **Estil de Codi**:
  - No utilitzis operadors ternaris.
  - Ús obligatori de `if/else`, `while`, `for/foreach`.
  - Estructura de comentaris per blocs (veure secció 5).

## 3. Arquitectura i Responsabilitats

L'organització del codi segueix una arquitectura de separació de conceptes:

- **Controllers (`app/Http/Controllers/`)**:
  - Punts d'entrada per a l'API REST consumida pel Frontend (Nuxt 4).
  - Gestió d'autenticació i perfil d'usuari.
- **Services (`app/Services/`)**:
  - Contenen la lògica de negoci complexa (Ex: Verificar que el `user_id` del pagament coincideix amb el de la reserva).
  - Gestió de la publicació a Redis quan un seient canvia d'estat a la BD.
- **Models (`app/Models/`)**:
  - Models Eloquent manuals (`User`, `Event`, `Seat`, `Ticket`, `SeatZone`) per interactuar amb l'esquema ja existent.
- **Routes (`routes/api.php`)**:
  - Totes les rutes de persistència i consulta de dades.

## 4. Convencions de Rutes API

- **Paràmetres al path**: Els identificadors han d'anar a la URL.
- **Correcte**: `GET /api/events/{id}/seats`, `POST /api/tickets/transfer/{ticket_id}`.
- **Prohibit**: `GET /api/seats?event_id=1`.

## 5. Estructura de Codi i Comentaris (Obligatori)

Totes les classes i mètodes han d'estar documentats en **català** i seguir aquest esquema:

```php
//================================ NAMESPACES / IMPORTS ============

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

//================================ LÒGICA PRIVADA ================
```

### Normativa de Documentació Interna:
Dins de cada mètode, s'ha de desglossar la lògica:
1. `// A. Validació de la sessió i permisos de l'usuari.`
2. `// B. Consulta o modificació de la base de dades SQL.`
3. `// C. Notificació a Redis (si cal) i retorn de la resposta JSON.`

## 6. Lògica del Projecte (Referència)

- **Seients**: Els seients tenen estats (`available`, `reserved`, `sold`).
- **Admin**: Té un Dashboard Live i pot prémer el "Botó de Pànic" per aturar vendes.
- **Checkouts**: Cal validar que la reserva no ha expirat (`reservation_expiry`) abans de permetre la compra.

### Skills i Bones Pràctiques
Per a qualsevol tasca de programació, estructura de fitxers o arquitectura, l'agent ha de consultar i aplicar les directrius de les següents skills:
- **`laravel-best-practices`**: Referència principal per a codi net i eficient en Laravel.
- **`laravel-specialist`**: Per a funcionalitats avançades i optimització del framework.

## ✅ Regla GET/CUD
- **GET/POST/PUT/DELETE**: Totes les accions de persistència (Tickets, Compra, Rols) es realitzen directament contra l'API de Laravel via fetch des del Frontend.
- **Real-Time**: Laravel publica a Redis -> Node.js emet via Sockets.