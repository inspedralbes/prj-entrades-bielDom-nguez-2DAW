# Agent de Pinia (Gestió d'Estat i Optimistic UI)

Aquest document defineix com s'ha de gestionar l'estat global de l'aplicació mitjançant **Pinia 3** per al projecte **TR3 TicketMaster**. L'objectiu és una sinkronització perfecta entre dades persistents (API) i volàtils (Sockets).

## 1. Objectiu de l'Agent
Centralitzar les dades crítiques (Usuari, Esdeveniment, Seients, Cua) i gestionar la interfície d'usuari per garantir una latència percebuda de 0ms (UI Optimista).

## 2. Definició de Stores (Setup Stores Style)
Les stores s'han de definir a `stores/` utilitzant la sintaxi moderna de funcions:

```javascript
import { defineStore } from 'pinia';

export const useSeatsStore = defineStore('seats', () => {
    // ESTAT (refs i reactius)
    const llistaSeients = ref([]);
    const seientSeleccionat = ref(null);
    
    // ACCIONS (funcions)
    const actualitzarEstatSeient = (id, nouEstat) => { ... };
    
    return { llistaSeients, seientSeleccionat, actualitzarEstatSeient };
});
```

## 3. Patró Optimistic UI (Snapshot & Rollback)
Aquest és el patró clau per a la reserva de seients real-time:

1.  **Snapshot**: Crear una còpia de seguretat de l'estat d'un seient abans de la mutació.
2.  **Mutació Optimista**: Canviar l'estat visualment (ex: de `available` a `reserved`) de forma immediata al frontend.
3.  **Sincronització**: Emetre l'acció via Socket.IO al backend.
4.  **Rollback**: Si el servidor retorna un error (ex: seient ja reservat per un altre), restaurar el seient al seu estat inicial del Snapshot i mostrar feedback a l'usuari.

## 4. Estructura del Codi d'Acció (Exemple Seients)

```javascript
async function clicarSeient(idSeient) {
    const seient = llistaSeients.value.find(s => s.id === idSeient);
    if (!seient || seient.status !== 'available') return;

    // 1. SNAPSHOT
    const backupStatus = seient.status;

    // 2. MUTACIÓ OPTIMISTA
    seient.status = 'reserved';

    try {
        // 3. SINCRONITZACIÓ
        await socket.emit('seat:reserve', { id: idSeient });
    } catch (error) {
        // 4. ROLLBACK
        seient.status = backupStatus;
        showToast("Error en la reserva, intenta-ho de nou.");
    }
}
```

## 5. Stores Principals del Projecte
- **AuthStore**: Gestiona el `JWT`, les dades del compte i el rol (Admin/Client).
- **EventStore**: Conté la informació de l'esdeveniment actiu, mapa de seients i preus.
- **QueueStore**: Gestiona la posició de l'usuari a la cua virtual (The Gatekeeper) i si té permís per entrar al mapa.

### Skills i Bones Pràctiques
Per a la gestió d'estat i patrons d'Optimistic UI, l'agent ha de consultar:
- **`vue-pinia-best-practices`**: Guia essencial per a stores escalables i netes.

## ✅ Regla GET/CUD
- **GET**: L'estat inicial de Pinia es carrega via `fetch` contra l'API de Laravel (SSR o Client-side).
- **CUD**: Tota modificació de dades compartides (estat de seients) s'actualitza a Pinia i es propaga via Sockets per a la resta d'usuaris.