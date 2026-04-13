# Agent de Javascript (Normes de Sintaxi Moderna ES6+)

Aquest document defineix les regles de sintaxi i estil de codi Javascript que s'han d'aplicar transversalment a tot el projecte **TR3 TicketMaster** (Frontend Nuxt 4 i Backend Node.js 24).

## 1. Objectiu de l'Agent
Garantir un codi modern, eficient i altament llegible que aprofiti les capacitats de **Nuxt 4** i **Node.js 24**. L'enfocament és la robustesa tècnica i la claredat en la gestió de dades complexes (venda d'entrades, cues virtuals).

## 2. Regles de Sintaxi (ES6+ Professional)
Aquestes regles són obligatòries per mantenir la coherència:

- **Idioma**: Tot el codi i comentaris han de ser en **Català**.
- **Variables**:
    - Ús de `const` per defecte per a valors que no canvien.
    - Ús de `let` per a variables amb reassignació.
    - **PROHIBIT** l'ús de `var`.

- **Funcions**:
    - Ús preferent de funcions de fletxa (`=>`) per a lògica interna i callbacks.
    - Declaració `function` només per a definicions de components o mètodes principals de l'API si l'estil ho requereix.

- **Asincronia**:
    - Ús obligatori de `async/await`.
    - Gestió d'errors mitjançant blocs `try/catch` per garantir que el flux de l'usuari no s'aturi.

- **Objectes i Arrays**:
    - Ús de *Destructuring* per a una extracció neta de dades (ex: `const { id, status } = seat`).
    - Ús del *Spread Operator* (`...`) per a la clonació i merging d'estats.
    - Ús de mètodes d'ordre superior: `map`, `filter`, `reduce`, `find`.

- **Condicionals**:
    - Operador ternari permès per a assignacions simples.
    - Bloc `if/else` per a lògica complexa o control de flux.

## 3. Idioma i Nomenclatura
- **Idioma del Codi**: Noms de variables, funcions i objectes en **català** i **camelCase**.
- **Nomenclatura**: Noms descriptius (ex: `llistaSeientsReservats`, `gestionarCuaVirtual`).
- **Comentaris**: Documentació detallada en català, explicant el "per què" i no només el "què".
- **Estructura pas a pas**: Per a funcions complexes: `// A. Validar sessió...`, `// B. Verificar disponibilitat...`.

## 4. Exemple Correcte (Projecte TicketMaster)

```javascript
/**
 * Gestiona la reserva d'un seient al mapa real-time.
 * A. Verifica la sessió de l'usuari.
 * B. Extreu dades del seient mitjançant destructuring.
 * C. Realitza la mutació optimista a Pinia.
 */
async function reservarSeient(seientClicat) {
    const { id, preu, zona } = seientClicat;
    const usuariStore = useUserStore();

    if (!usuariStore.estaLoguejat) {
        return redirigirALogin();
    }

    try {
        // Mutació optimista
        seientsStore.marcarSeient(id, 'reserved');
        await socket.emit('seat:reserve', { id, user_id: usuariStore.id });
    } catch (error) {
        console.error("Error reservant el seient:", error);
        seientsStore.revertirReserva(id);
    }
}
```

## ✅ Regla GET/CUD
- **GET**: Consultes massives o dades persistents sempre via `fetch` contra l'API de Laravel (`api/v1/...`).
- **CUD**: Les accions de creació, actualització i esborrat es coordinen via Node.js -> Redis -> Laravel, amb feedback real-time via Sockets.