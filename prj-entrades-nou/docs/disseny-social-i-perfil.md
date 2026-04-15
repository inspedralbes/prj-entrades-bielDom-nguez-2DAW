# Diseño funcional detallado: Social y Perfil

Este documento describe, con detalle funcional y de interfaz, qué hace actualmente la página `Social` y la página `Perfil` del frontend Nuxt.

## Página Social (`/social`)

## Objetivo de la pantalla

La página Social centraliza tres áreas:
- descubrimiento de usuarios (buscador),
- gestión de invitaciones de amistad,
- listado de amistades con contador de notificaciones no leídas por amigo.

También incluye una ayuda contextual para el envío de entradas entre amigos.

## Estructura visual principal

- **Cabecera de página**
  - Título: `Social`.
  - Subtítulo: `Cerca usuaris, invitacions i amistats.`
- **Bloque 1: Buscar usuarios**
- **Bloque 2: Invitaciones**
- **Bloque 3: Amigos**
- **Mensaje final de ayuda** con enlace a `Les meves entrades`.

## Bloque 1: Buscar usuarios

### Funcionalidades

- Permite buscar por nombre o nombre de usuario.
- El input usa búsqueda incremental con debounce (~320 ms).
- Solo dispara búsqueda si el texto tiene 2 o más caracteres.
- Muestra resultados en dropdown bajo el input.
- Cada resultado abre el perfil público del usuario seleccionado.
- Cierra el dropdown al hacer clic en un resultado.

### Estados de interfaz

- **Hint de validación mínima**: muestra “Escriu almenys 2 caràcters.” si hay texto pero menos de 2 caracteres.
- **Estado cargando**: muestra `Cercant…` mientras consulta API.
- **Estado error**: muestra `Error de cerca.` si falla la API de búsqueda.
- **Dropdown visible/oculto** según interacción del usuario.

### Botones y elementos interactivos

- **Input `Cerca`**
  - Placeholder: `Ex.: Maria o @maria`.
  - Evento: `@input`.
  - Acción: prepara y lanza búsqueda.
- **Resultado del dropdown (link por usuario)**
  - Navega a `/users/:id`.
  - Acción adicional: cierra dropdown.

### API implicada

- `GET /api/social/discover/search?q=...`

## Bloque 2: Invitaciones

### Funcionalidades

- Carga invitaciones en dirección global (`direction=all`).
- Renderiza cada invitación con texto descriptivo contextual:
  - solicitud enviada por el propio usuario,
  - solicitud recibida,
  - invitación aceptada,
  - invitación rechazada.
- Muestra acciones de respuesta solo cuando:
  - la invitación está en estado `pending`,
  - y el usuario autenticado es el receptor.

### Estados de interfaz

- **Cargando**: `Carregant…`.
- **Sin invitaciones**: `No tens invitacions recents.`
- **Con invitaciones**: lista de tarjetas.

### Botones

- **`Acceptar`**
  - Visible únicamente en invitaciones pendientes recibidas.
  - Acción: responde `accept` al backend.
- **`Rebutjar`**
  - Visible en el mismo caso que `Acceptar`.
  - Acción: responde `reject` al backend.

### Acciones internas tras responder

- Refresca datos de la página (amistades + invitaciones).
- Lanza evento global `app:social-invites-updated` para sincronización de otras vistas.

### API implicada

- `GET /api/social/friend-invites?direction=all`
- `PATCH /api/social/friend-invites/:inviteId` con `{ action: 'accept' | 'reject' }`

## Bloque 3: Amigos

### Funcionalidades

- Carga y muestra listado de amistades aceptadas.
- Cada amigo enlaza a su perfil (`/users/:id`).
- Calcula y muestra contador de notificaciones no leídas por amigo.
- El contador solo cuenta notificaciones de tipo:
  - `event_shared`
  - `ticket_shared`
- El cálculo detecta “otra parte” de la notificación según dirección `sent/received`.

### Estados de interfaz

- **Cargando**: `Carregant…`.
- **Sin amigos**: `Encara no tens amics acceptats.`
- **Con amigos**: filas con nombre, usuario y badge opcional de notificaciones.

### Botones y enlaces

- **Fila de amigo (link)**
  - Navega a `/users/:id`.
- **Enlace de ayuda final**
  - Link a `/tickets` dentro del texto explicativo.

### API implicada

- `GET /api/social/friends`
- `GET /api/notifications?limit=100`

## Comportamiento en tiempo real (Social)

- Se inicializa socket privado de entradas/notificaciones.
- Escucha evento global `app:socket-notification`:
  - recarga notificaciones,
  - emite `app:notifications-updated`.
- Escucha `app:social-invites-updated`:
  - recarga contenido Social.

## Diseño visual (Social)

- Layout centrado (ancho máximo ~36rem).
- Separación por bloques (`social__block`).
- Estilo oscuro con acentos amarillos en acciones.
- Dropdown superpuesto con sombra y hover resaltado.
- Botones de invitación en formato “pill”.
- Badge de notificaciones redondo con color de acento.

---

## Página Perfil (`/profile`)

## Objetivo de la pantalla

Permitir gestión completa del perfil autenticado:
- actualizar nombre y email,
- cambiar contraseña,
- cerrar sesión.

La ruta `/profile` actúa como contenedor y delega la lógica al componente `UserProfileEditor`.

## Estructura visual principal

- **Cabecera de página**
  - Título: `El meu perfil`.
  - Subtítulo: `Dades del compte, contrasenya i sessió.`
- **Componente editor**
  - Formulario de datos básicos.
  - Sección de cambio de contraseña.
  - Botón de guardado.
  - Mensajes de error/éxito.
  - Acción de cierre de sesión.

## Formulario de perfil (datos básicos)

### Campos

- **`Nom`** (obligatorio).
- **`Correu electrònic`** (obligatorio, tipo email).

### Estados

- **Cargando perfil**: `Carregant…`.
- **Error global carga**: `No s'ha pogut carregar el perfil.`
- **Listo para edición**: render del formulario completo.

### API implicada

- `GET /api/user/profile`

## Sección cambio de contraseña

### Campos

- `Contrasenya actual`
- `Nova contrasenya`
- `Confirma la nova contrasenya`

### Funcionalidades de UX

- Indicador de ayuda: dejar los tres campos vacíos si no se quiere cambiar contraseña.
- Icono “ojo” en `Contrasenya actual` para mostrar/ocultar texto:
  - cambia tipo `password` ↔ `text`,
  - actualiza `aria-label` dinámico:
    - `Mostrar la contrasenya`
    - `Amagar la contrasenya`.

### Validaciones locales antes de enviar

Si el usuario intenta cambiar contraseña:
- exige contraseña actual,
- exige nueva contraseña,
- exige longitud mínima de 8,
- exige confirmación igual a nueva contraseña.

Si no quiere cambiar contraseña, se puede guardar solo nombre/email.

## Guardado de cambios

### Botón principal

- **`Desar els canvis`**
  - Estado loading: cambia a `Desant…`.
  - Se deshabilita durante guardado.

### Resultado de guardado

- **Éxito**:
  - mensaje `Canvis desats.`
  - limpia campos de contraseña.
  - resetea visibilidad de contraseña actual.
  - sincroniza sesión en store (`auth.setSession`) con datos actualizados.
- **Error validación backend (422)**:
  - mapea errores por campo (`name`, `email`, `current_password`, `password`).
- **Error genérico**:
  - `No s'han pogut desar els canvis. Torna a intentar.`

### API implicada

- `PATCH /api/user/profile`

## Cerrar sesión

### Botón

- **`Tancar la sessió`**
  - Limpia sesión local (`auth.clearSession`).
  - Redirige a `/login`.

## Manejo de errores y mensajes (Perfil)

- Error global superior.
- Errores específicos por campo.
- Mensaje de éxito tras guardado.
- Estado de deshabilitado en botones cuando aplica.

## Diseño visual (Perfil)

- Layout estrecho centrado (ancho máximo ~28rem).
- Campos en tema oscuro, foco amarillo.
- Etiquetas en uppercase con tracking amplio.
- Secciones separadas por líneas sutiles.
- Botón principal amarillo tipo pill.
- Botón secundario (logout) con estilo ghost.

---

## Resumen funcional rápido

- **Social**: buscar usuarios, gestionar invitaciones, consultar amistades y contador de notificaciones por amigo.
- **Perfil**: editar cuenta, cambiar contraseña con validaciones robustas y cerrar sesión.
- **Ambas**: protegidas por middleware `auth` en su ruta principal.
