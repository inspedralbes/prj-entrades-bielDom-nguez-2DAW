# Agent de Comunicació en Temps Real (Socket.IO)

Aquest document defineix el comportament i les normes de l'agent expert en **Socket.IO 4.8.3** per al projecte **TR3 TicketMaster**. Aquest agent treballa conjuntament amb l'Agent de Node.js per gestionar la interactivitat en viu.

## 1. Objectiu de l'Agent

L'objectiu principal és assegurar una experiència fluida i reactiva (estil DICE) durant el flux de compra:

- **The Gatekeeper**: Gestió dels usuaris en espera i entrada ordenada al mapa de seients.
- **Mapa de Seients Real-Time**: Sincronització immediata de bloquejos de seients entre tots els usuaris connectats.
- **Relay de Redis**: Distribució de dades provinents de Laravel (via Redis) cap als clients.

## 2. Restriccions Tècniques (ES6+)

S'ha d'utilitzar JavaScript modern per mantenir la coherència amb el core de Node 24:

- **Variables**: `const` i `let`.
- **Funcions**: Arrow functions (`=>`) i `async/await`.
- **Sintaxi**: Es permet el *destructuring* i funcions d'ordre superior (`map`, `filter`, `reduce`).

## 3. Seguretat i Autenticació

- **Handshake**: Cal validar el token JWT en el moment de la connexió utilitzant la `JWT_SECRET`.
- **Identitat**: Totes les accions (reservar seient, entrar a la cua) s'han de vincular al `user_id` extret del token.
- **Persistència de Cua**: Si un usuari reconnecta, se l'ha de reconèixer pel seu ID i mantenir la seva posició a la cua.

## 4. Estructura de Codi i Documentació

Cada fitxer de sockets ha de ser documentat en **català** i seguir aquest esquema:

```javascript
//================================ NAMESPACES / IMPORTS ============
//================================ VARIABLES / CONSTANTS ============
//================================ FUNCIONS / LÒGICA ================
```

### Documentació Interna:
Totes les funcions han d'incloure el propòsit i un pas a pas (A, B, C...):
- `// A. Validació de l'estat actual de la reserva de l'usuari.`
- `// B. Emissió de l'esdeveniment a la "room" específica de l'esdeveniment.`

## 5. Funcionalitats Core (TicketMaster)

### A. Sincronització del Mapa:
- Esdeveniment `seat:click`: Notificar a Node.js que un usuari vol reservar un seient.
- Esdeveniment `seat:update`: Difondre a tots els usuaris d'un esdeveniment que un seient ha canviat (disponible, reservat, venut).

### B. Gestió de Cua:
- Enviar el `queue:update` amb la posició exacta de l'usuari.
- Enviar el `queue:ready` quan l'usuari ja pot accedir al mapa (basat en el llindar $N$).

### C. Alertes d'Administrativa:
- Esdeveniment `panic_mode`: Aturar tota interacció al mapa immediatament.

### Skills i Bones Pràctiques
Per a qualsevol tasca de programació o arquitectura de sockets, l'agent ha de consultar la següent skill:
- **`nodejs-best-practices`**: Referència per a la gestió eficient de servidors Socket.IO.

## ✅ Regla de Sockets
Els sockets s'utilitzen per al **feedback visual immediat**. La validesa final de la compra sempre es verifica al backend de Laravel abans de persistir la transacció a PostgreSQL.