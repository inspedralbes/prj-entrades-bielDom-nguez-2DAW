# Agent de Sockets (Client Real-Time)

Aquest document defineix la gestió de la comunicació bidireccional des del Frontend Nuxt 4 per al projecte **TR3 TicketMaster**. Treballa en sintonia amb l'Agent de Sockets del Backend.

## 1. Objectiu de l'Agent
Mantenir una connexió de baixa latència per gestionar el flux de la cua virtual (The Gatekeeper) i la selecció de seients real-time al mapa de l'esdeveniment.

## 2. Configuració del Client
- **Llibreria**: `socket.io-client` 4.8.3.
- **Implementació**: Ús d'un plugin SSR-safe (`plugins/socket.client.js`) que injecti la instància `$socket` globalment.
- **Autenticació**: El handshaking del socket requereix el token JWT de l'usuari:
  - `auth: { token: "Bearer " + tokenJWT }`.

## 3. Gestió de la Cua (The Gatekeeper)
Els esdeveniments de la cua s'han de gestionar preferiblement en un composable (`useQueue.js`):
- `queue:status`: Rep la posició actual i el temps d'espera estimat.
- `queue:entry_allowed`: Senyal que permet a l'usuari saltar de la vista de cua al Mapa de seients.

## 4. Mapa de Seients (Interacció en viu)
Les actualitzacions del mapa han de ser instantànies:
- `seat:update`: Es rep quan qualsevol usuari reserva o allibera un seient. L'Agent ha de desencadenar la mutació corresponent a Pinia (`AgentPinia.md`).
- `seat:click`: Envia la petició de reserva d'un seient concret al servidor de Node.js.

## 5. Exemple de Composable Modern (DICE style)

```javascript
// composables/useTicketSocket.js
export function useTicketSocket() {
    const { $socket: socket } = useNuxtApp();
    const seatsStore = useSeatsStore();

    function setupListeners() {
        if (!socket) return;

        // A. Escolta de canvis en els seients
        socket.on('seat:update', (updatedSeat) => {
            const { id, status } = updatedSeat;
            seatsStore.actualitzarEstatSeient(id, status);
        });

        // B. Alerta de l'administrador
        socket.on('panic:mode', () => {
            alert("La venda s'ha aturat temporalment.");
            navigateTo('/');
        });
    }

    function reservarSeient(seientId) {
        socket.emit('seat:reserve', { id: seientId });
    }

    return { setupListeners, reservarSeient };
}
```

### Skills i Bones Pràctiques
Per a la integració amb Nuxt, consultar la skill:
- **`nuxt`**: Per a la implementació correcta de plugins i composables de socket.

## ✅ Regla GET/CUD
- **GET**: Info d'esdeveniments i dades persistents via API Laravel.
- **CUD**: Les accions ràpides (estat de la cua, reserves de seients) passen per Socket.IO cap al Bridge de Redis.