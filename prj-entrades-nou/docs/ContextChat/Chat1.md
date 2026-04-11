# Contexto del chat 1 — Feature `001-seat-map-entry-validation`

Este documento resume **toda la conversación** con el asistente para que un **nuevo agente** pueda continuar con el mismo criterio. Proyecto: **prj-entrades-nou** (Speckit / ticketing).

---

## 1. Alcance del feature

Funcionalidad documentada bajo:

`specs/001-seat-map-entry-validation/`

Archivos de referencia habituales:

- `spec.md` — requisitos funcionales (FR-xxx), arquitectura híbrida Socket, auth, social, etc.
- `plan.md` — plan técnico (Laravel 11, Nuxt 3, Redis, Socket.IO, Gemini solo JSON, etc.)
- `data-model.md` — tablas PostgreSQL y reglas de negocio.
- `tasks.md` — lista de tareas ejecutables (T001, …).
- `contracts/openapi.yaml` — contrato API (versión actualizada en el chat).
- `quickstart.md`, `research.md`, `checklists/ticketing.md`.

Constitución global: `.specify/memory/constitution.md`.

---

## 2. Decisiones de diseño acordadas (resumen técnico)

### 2.1 Tabla `friend_invites` (PostgreSQL) — cierre gap G1 / OpenAPI

- **Campos**: `id` (UUID), `sender_id` (FK → `users`), **`receiver_id`** (usuarios existentes) **o** **`receiver_email`** (invitar a usuarios nuevos), `status` (`pending` | `accepted` | `rejected`), opcional `invite_token` para enlace.
- **Regla**: destinatario existente **o** email; validación de integridad en backend (mutuamente excluyentes según reglas de aplicación).
- **Transferencia de tickets**: solo con relación de amistad válida (p. ej. invitación **aceptada**). El **servidor (Laravel)** es fuente de verdad: **invalida** JWT/QR antiguo del titular y **emite credencial nueva** (JWT ticket + **SVG**) para el destinatario.

### 2.2 Socket.IO híbrido — inconsistencia I1 / tarea T013

- **Lectura pública**: cualquier cliente puede suscribirse a rooms/namespace por `eventId` y recibir broadcast del **estado de asientos** (disponible / reservado / vendido) **sin JWT**.
- **Escritura**: **hold** y **compra** solo vía **API REST Laravel** (no escritura arbitraria por mensajes Socket).
- **`io.use()`**: validar **JWT en el handshake** antes de entrar a **rooms privadas** (`user:{id}`, notificaciones de transacción, **Validador**, **Administrador**).

### 2.3 Tiempo de reserva (Redis) — ambigüedad I3 / T017

- **TTL inicial**: **240 s** (4 min) al seleccionar asiento (alineado con política de producto en spec).
- **Prórroga única**: si el servidor detecta **login/registro desde checkout**, **`PEXPIRE`** (o equivalente) en Redis **+120 s** una vez por hold (flag tipo `login_grace_applied`).
- **Total máximo efectivo** en esa política: **360 s** (4+2 minutos).

### 2.4 Nuxt 3 — seguridad / T044

- **Guards de navegación** con **Pinia** (`stores/auth`): antes de **`/tickets`**, **`/social`**, **`/checkout`**, y también **Guardados** y **Perfil**; sin JWT válido → redirección a **login** (o ruta equivalente). Alinear prefijos con `pages/` reales del proyecto.

### 2.5 Pruebas de carga — T053 / U1

- **Stress del socket-server** (k6, Artillery u otra herramienta): muchas conexiones a canales **públicos** por `eventId` + subconjunto con **JWT** en rooms privadas.
- Objetivo alineado con **SC-002** / **SC-004** del spec (p. ej. mantener el **95 %** de peticiones bajo umbrales donde aplique). **Complementario** a Cypress; no sustituye pruebas funcionales.

---

## 3. Cambios documentales realizados en el repositorio (este chat)

### 3.1 `data-model.md`

- Sección **`friend_invites`** con tabla y notas.
- **`ticket_transfers`**: lógica de transferencia con invalidación de credencial anterior y nuevo SVG en servidor.
- Ajuste de redacción en `users` / roles (Spatie) donde correspondía.

### 3.2 `spec.md`

- **FR-022** ampliado: persistencia `friend_invites`, transferencia con invalidación QR/JWT y nuevo SVG (referencia a FR-005/FR-006).

### 3.3 `tasks.md`

- T007: migración incluye **`friend_invites`** y **`ticket_transfers`**.
- T013: texto explícito de Socket híbrido (lectura pública / API para hold-compra / `io.use()` en rooms privadas).
- T017: **PEXPIRE +120 s**, TTL 240 → hasta 360 s.
- T044: rutas explícitas de guards.
- T053: enlace a stress socket y objetivos de rendimiento.
- T037 / T050: alineación con OpenAPI y `friend_invites` + transferencias.

### 3.4 `contracts/openapi.yaml`

- Versión incrementada (p. ej. **0.3.1** en el momento del cambio).
- Esquemas **`FriendInvite`**, **`FriendInviteCreateRequest`** (uno de `receiver_id` o `receiver_email`), **`FriendInvitePatchRequest`** (`accept` / `reject`).
- Rutas: **`GET` / `POST`** `/api/social/friend-invites`, **`PATCH`** `/api/social/friend-invites/{inviteId}`.
- Respuesta de **transferencia** de ticket: texto sobre invalidación y nuevo SVG.

---

## 4. Segunda petición del usuario: workflow Git + Cypress en `tasks.md`

El usuario pidió que **por cada tarea** se documentara:

1. **Una rama (branch) dedicada**.
2. **Testing**:
   - **Frontend**: flujos de pantalla e información (que funcione la UI).
   - **Backend**: pruebas con **Cypress** (`cy.request` contra la API Laravel), alineado con el Speckit/OpenAPI.

### 4.1 Contenido añadido a `tasks.md`

- Bloque **`Tests`**: obligatorio por tarea — rama + Cypress API + Cypress FE si hay UI.
- Sección **«Workflow Git i proves»** (catalán en el archivo original):
  - Patrón de rama: **`001-seat-map-tNNN-<slug-curt>`** (ej. `001-seat-map-t016-post-holds`).
  - Opción de usar el skill **speckit-git-feature** (`../../.cursor/skills/speckit-git-feature/SKILL.md` desde la carpeta del spec).
  - **DoD**: PR con Cypress verde o justificación si la tarea es solo docs/estructura vacía.
  - Carpetas sugeridas: `e2e/api/`, `e2e/flows/` (concretar en el primer PR que añada Cypress).

- **Cada línea de tarea (T001–T052, T053)** ampliada con:
  - **`Branca:`** nombre sugerido.
  - **`Cypress:`** qué probar (API, FE, smoke, stub, k6 complementario para T053, etc.).

- **Notas** finales: Cypress + Socket (plugin o tests Node aparte); ramas por tarea y merge a la rama del feature principal.

### 4.2 Limitaciones del entorno (mencionadas en conversación)

- En algún momento **Git no detectado** al ejecutar scripts de prerequisitos en Windows; validación de rama puede fallar hasta que el repo esté bien configurado en el entorno local.

---

## 5. Idioma y convenciones

- El usuario pidió respuestas en **español** en reglas; parte de la documentación Speckit del proyecto está en **catalán** (`tasks.md`, `spec.md`, etc.). Un nuevo agente debe **respetar el idioma del archivo** que edite y las reglas del usuario para mensajes.

---

## 6. Qué puede hacer a continuación un nuevo agente

1. **Implementar** siguiendo `tasks.md` con el workflow rama + Cypress por tarea.
2. **Verificar** que `openapi.yaml`, `data-model.md` y `spec.md` siguen alineados tras cambios de código.
3. **Completar** T037 cuando el contrato cubra todos los campos de `friend_invites` y endpoints sociales.
4. Inicializar **Cypress** en el monorepo si aún no existe (el usuario puede pedirlo en `quickstart.md` o `plan.md`).

---

## 7. Metadatos de este archivo

| Campo | Valor |
|-------|--------|
| Archivo | `docs/ContextChat/Chat1.md` |
| Propósito | Contexto único de la conversa para handoff a otro agente |
| Feature | `001-seat-map-entry-validation` |
| Fecha referencia usuario | 2026-04-11 (según entorno de la sesión) |

---

*Fin del contexto Chat1.*
