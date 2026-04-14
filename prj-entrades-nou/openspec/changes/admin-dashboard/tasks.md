# Tasques — `admin-dashboard`



Ordre suggerit: BD `admin_logs` → auditoria → summary/socket → GraphQL → frontend.



**Especificacions**: `specs/admin-dashboard-metrics-ui/spec.md`, `specs/admin-audit-logs/spec.md`  

**Disseny**: `design.md`



## 1. Base de dades i model

- [x] 1.1 Migració Laravel: taula **`admin_logs`** (`admin_user_id`, `action`, `entity_type`, `entity_id` nullable, `summary`, **`ip_address`** nullable string, `timestamps`); índexs `created_at`, `admin_user_id`.

- [x] 1.2 Model Eloquent `AdminLog` + relació amb `User` (per exposar **nom** a l’API).

- [x] 1.3 Decidir i documentar al codi el recompte de **tiquets venuts històric** (quines files `tickets` compten).



## 2. Auditoria (escriptura)



- [x] 2.1 Crear `AdminAuditLogService` amb `record(..., ?string $ipAddress)`; passar `Request::ip()` des dels punts d’entrada admin.

- [x] 2.2 Instrumentar **controladors o serveis admin** existents (prioritat: esdeveniments, comandes, usuaris admin) per cridar el servei després de CUD reeixit.

- [x] 2.3 Tests: una operació admin coberta crea fila a `admin_logs`.



## 3. API REST — resum i logs



- [x] 3.1 Ampliar **`GET /api/admin/summary`** amb: `orders_paid_today`, `tickets_sold_total`, `recent_admin_logs` (**5** ítems, mateix format que `AdminLogResource`: nom admin, data/hora, IP, acció).

- [x] 3.2 Implementar **`GET /api/admin/logs`** amb paginació (`page`, `per_page` per defecte **10**), només `admin`; **Resource** amb camps **llegibles** per la UI: `admin_name`, data/hora com a **strings formats** o camps separats `date` / `time` (evitar que el frontend hagi de “parsejar JSON visible”), `ip_address`, `summary` com a **text pla** (prosa). **Cap query GraphQL** de lectura de logs.

- [x] 3.3 Tests feature: 403 sense rol admin; paginació 10; resposta inclou IP, nom i resum en text natural.



## 4. Snapshot Socket.IO



- [x] 4.1 Incloure al `buildFullDashboardPayload()` els nous camps numèrics (inclosos `orders_paid_today`, `tickets_sold_total`); valorar si `recent_admin_logs` va al socket o només es refresca amb GET després d’accions (segons `design.md`).

- [x] 4.2 `PresenceController::ping` emet snapshot complet; observers de `Order` per KPIs; debounce opcional.



## 5. GraphQL — gràfics 30 dies



- [x] 5.1 Lighthouse (o equivalent) a **`POST /api/graphql`**; queries amb `days` amb **màxim 30** (o fixar 30 al resolver del dashboard).

- [x] 5.2 `adminDashboardRevenueByDay` → punts diaris per **línies**.

- [x] 5.3 `adminDashboardOrdersPaidByDay` → punts diaris per **barres**.

- [x] 5.4 Tests GraphQL amb rol admin vs denegat.



## 6. Frontend Nuxt



- [x] 6.1 Client GraphQL + **`chart.js`**: component **línies** (ingressos 30 dies), component **barres** (comandes pagades 30 dies).

- [x] 6.2 Targetes: ingressos avui, comandes pagades avui, usuaris en línia, tiquets històrics; dades des de summary + socket.

- [x] 6.3 Widget **logs**: **llista** (`<ul>` / ítems) amb **5** entrades: nom admin, dia, hora, IP, acció com a **text**; **clic** obre vista amb **llista** completa (mateix format); **no** `JSON.stringify` ni renderitzar objectes sencers; dades només **REST**; **10 per pàgina**; **no** GraphQL per logs.

- [x] 6.4 Eliminar `<pre>` de depuració; KPIs i `sync_alerts` només com a text/valors formats; **prohibit** mostrar respostes API com a JSON llegible per l’usuari.



## 7. Verificació



- [x] 7.1 Proves manuals: dos admins veuen KPIs en viu; gràfics amb 30 punts; logs 5 + modal amb paginació 10.

- [x] 7.2 `npm run lint` i tests backend.

