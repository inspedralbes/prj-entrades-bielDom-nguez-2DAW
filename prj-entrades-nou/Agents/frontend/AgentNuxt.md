# Agent de Nuxt (Framework i Estructura Nuxt 4)

Aquest document defineix com s'ha d'estructurar i desenvolupar l'aplicació web utilitzant **Nuxt 4** per al projecte **TR3 TicketMaster**. L'objectiu és una arquitectura modular, ràpida i orientada a l'estètica DICE.

## 1. Objectiu de l'Agent
Gestionar l'arquitectura del frontend, optimitzant el rendiment de les pàgines de real-time (Cua i Mapa) i assegurant una experiència d'usuari (UX) premium.

## 2. Estructura de Directoris (Nuxt 4 Standard)
L'aplicació ha de seguir la jerarquia organitzada de Nuxt 4:

- `pages/`: Vistes de l'aplicació (Landing, Cua, Mapa, Checkout, Entrades).
- `components/`: Components modulars d'estètica DICE (ex: `BaseNeonButton.vue`, `SeatMap.vue`).
- `layouts/`: Plantilles clares: `client.vue` (flux de compra) i `admin.vue` (dashboard).
- `stores/`: Pinia 3 per a la gestió d'estat (veure `AgentPinia.md`).
- `composables/`: Lògica reactiva compartida (ex: `useTicketSocket.js`, `useQueue.js`).
- `middleware/`: Protecció de rutes i el `gatekeeperGuard.js` (verifica si l'usuari pot entrar al mapa o ha d'anar a la cua).

## 3. Estil de Programació (Vue 3.5 + ES6+)
S'ha d'utilitzar la sintaxi moderna per mantenir la cohesió amb les eines més recents:

- **Script Setup**: Ús obligatori de `<script setup>`.
- **Reactivitat**: Ús eficient de `ref`, `computed` i `watch` (Vue 3.5 features permès).
- **Nuxt Plugins**: Plug-ins clients per a la injecció de `socket.io-client`.

## 4. Estètica DICE i UX
La interfície ha de seguir els principis definits a les especificacions:
- **Look & Feel**: Fons negre absolut (`bg-[#000000]`), tipografia sans-serif minimalista.
- **Accents**: Botons Rosa Neó (`#FF0055`) en format gran.
- **Flux**: Transicions suaus entre el Gatekeeper (Cua) i el Mapa de seients.

## 5. Integració de Serveis
- **Data Fetching**: Ús de `useFetch` o `$fetch` per a l'API de Laravel.
- **Real-Time**: Sincronització dels seients mitjançant el bus de dades que arriba via socket.

## 6. Exemple d'Estructura Vue 4 (DICE Style)

```html
<script setup>
const props = defineProps(['esdevenimentId']);
const seientsStore = useSeatsStore();

// A. Carregar dades inicials via SSR si és possible
const { data: event } = await useFetch(`/api/v1/events/${props.esdevenimentId}`);

// B. Iniciar socket per a estat real-time
onMounted(() => {
    iniciarSincronitzacioSeients(props.esdevenimentId);
});
</script>

<template>
    <main class="min-h-screen bg-black text-white p-8">
        <h1 class="text-4xl font-extrabold mb-12 uppercase">{{ event.title }}</h1>
        
        <div class="max-w-4xl mx-auto">
            <!-- Seat Map Component -->
            <SeatMap :seats="seientsStore.llistaSeients" />
        </div>
        
        <footer class="fixed bottom-0 left-0 w-full p-6 border-t border-zinc-900 bg-black">
            <button class="w-full py-4 bg-[#FF0055] text-black font-black uppercase text-xl rounded-full">
                Reservar Seleccionats
            </button>
        </footer>
    </main>
</template>
```

### Skills i Bones Pràctiques
Per a qualsevol tasca d'estructura Nuxt o Vue, l'agent ha de consultar:
- **`nuxt`**: Referència principal per a l'arquitectura i millors pràctiques del framework.

## ✅ Regla GET/CUD
- **GET**: Consultes a l'API de Laravel per a info d'esdeveniments, perfil d'usuari i historial.
- **CUD**: Accions crítiques (Reserva, Salt a la cua, Pagament) gestionades per la coordinació de Node/Laravel.