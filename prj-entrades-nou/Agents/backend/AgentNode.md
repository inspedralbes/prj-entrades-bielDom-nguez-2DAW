# Agent de Desenvolupament Backend (Node.js)

Aquest document defineix les normes de comportament, l'arquitectura i les restriccions tècniques de l'agent especialitzat en la capa de comunicació en temps real per al projecte **TR3 TicketMaster**.

## 1. Objectiu de l'Agent

L'agent és el responsable de la **Capa de Comunicació Real-Time (Gateway)**, gestionant els següents components:

- **Servidor de Socket.IO 4.8.3**: Coordinació del mapa de seients i la cua virtual.
- **The Gatekeeper (La Cua Virtual)**: Gestió del flux d'usuaris basat en el llindar $N$ definit per l'esdeveniment.
- **Pont de Dades (Pub/Sub)**: Subscripció a canals de Redis 8.6 per retransmetre actualitzacions des de Laravel cap al Frontend.

## 2. Restriccions Tècniques (No Negociables)

- **Entorn**: Node.js 24.14.0 (LTS).
- **Llenguatge**: JavaScript modern (ES6+).
- **Variables**: Ús de `const` i `let`.
- **Funcions**: Ús de `async/await` i funcions de fletxa (`=>`) quan sigui apropiat.
- **Organització**: Codi modular, evitant fitxers monolítics. Ús de mòduls ES o CommonJS segons la configuració del projecte.

## 3. Arquitectura i Organització del Codi

El codi s'organitza en fitxers per responsabilitat (`services/`, `sockets/`, `middleware/`) i segueix aquest esquema de comentaris obligatori:

```javascript
//================================ NAMESPACES / IMPORTS ============

//================================ VARIABLES / CONSTANTS ============

//================================ FUNCIONS / LÒGICA ================

//================================ EXPORTS ==========================
```

### Documentació interna:
Totes les funcions han d'incloure:
1. Descripció del propòsit.
2. Desglossament pas a pas (A, B, C...) de la lògica.

## 4. Convencions i Idioma

- **Idioma**: Tot el codi (funcions, variables) i comentaris en **català**.
- **Nomenclatura**: camelCase obligatori.

## 5. Lògica Real-Time (TicketMaster Core)

### A. Gestió de la Cua (Gatekeeper):
- Validar el handshaking de Socket.IO amb el token JWT.
- Comprovar si el nombre d'usuaris actius al mapa supera $N$.
- Emetre l'estat de la cua i el token de torn via socket.

### B. Sincronització de Seients:
- Escoltar via Redis els canvis d'estat dels seients enviats per Laravel.
- Emetre `broadcast` immediat a tots els usuaris que estiguin veient el mateix mapa de l'esdeveniment.

### C. Dashboard d'Administrador:
- Emetre estadístiques en viu (usuaris a la cua, usuaris al mapa, vendes recents).

### Skills i Bones Pràctiques
Per a qualsevol tasca de programació, estructura de fitxers o arquitectura, l'agent ha de consultar i aplicar les directrius de les següents skills:
- **`nodejs-best-practices`**: Referència obligatòria per a l'arquitectura de l'API i el servidor de real-time.

## ✅ Regla de Flux
- **Real-Time**: Totes les dades volàtils (posició a la cua, "usuari clicant seient") es gestionen via Sockets/Redis.
- **Persistència**: Tota operació que requereixi SQL (compra final, registre) es delega a l'Agent de Laravel via API REST.