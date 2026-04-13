# Agent de Tailwind (Estètica DICE i Disseny Minimalista)

Aquest document defineix les regles d'estil i disseny visual per al projecte **TR3 TicketMaster**. L'objectiu és crear una interfície d'alta gama inspirada en l'app **DICE**: fons negres absoluts, tipografia impactant i accents de color neó.

## 1. Objectiu de l'Agent
Garantir la coherència visual en tot el flux de compra (Landing -> Cua -> Mapa -> Ticket) i implementar una UI que transmeti exclusivitat i rapidesa.

## 2. Configuració de Tema (Tailwind 4)
- **Framework**: Tailwind CSS 4.2.2.
- **Colors Clau**:
    - **Negre Absolut**: `#000000` (Fons principal).
    - **Rosa Neó**: `#FF0055` (Botons d'acció principal, "Call to Action").
    - **Zinc/Gris**: Escala de grisos molt foscos per a targetes i contenidors secundaris.
- **Fonts**: Sans-serif de tall modern i minimalista (ex: Inter, Outfit).

## 3. Guia de l'Estètica DICE
La interfície s'ha de basar en el contrast extrem i el minimalisme:
- **Fons**: Sempre negre. Evitar gradients suaus o fons blancs.
- **Tipografia**: Títols en `uppercase` i `font-black`. Mides grans per a elements de crida a l'acció.
- **Botons**: El botó principal de compra ha de ser un bloc gran de color `#FF0055` amb text negre.

## 4. Regles d'Ús de Classes
- **Directives al HTML**: Classes directament als elements per a màxima claredat en el desenvolupament de components Nuxt 4.
- **Ordre de Classes**:
    1. Disseny (flex, grid, layout).
    2. Espaiat (p, m).
    3. Tipografia (text-4xl, font-bold).
    4. Colors i Visuals (bg-black, text-[#FF0055]).
    5. Estats (hover, active, disabled).

## 5. Visualització del Mapa de Seients (Real-Time)
Els seients del mapa han de reflectir el seu estat de forma visualment clara:
- **Available**: Vora fina blanca o gris clar, fons transparent.
- **Selected**: Fons Rosa Neó (`bg-[#FF0055]`).
- **Reserved** (altre usuari): Fons groc o taronja amb efecte `animate-pulse`.
- **Sold**: Gris fosc opac, sense interacció (`pointer-events-none`).

## 6. Exemple de Component DICE (Botó i Card)

```html
<template>
  <div class="bg-black text-white min-h-screen p-6">
    <!-- Header Minimalista -->
    <header class="mb-12">
        <h1 class="text-5xl font-black uppercase tracking-tighter">TR3 Master</h1>
    </header>

    <!-- Card d'Esdeveniment -->
    <div class="group relative overflow-hidden rounded-none border-b border-zinc-800 pb-8 hover:border-[#FF0055] transition-colors">
      <div class="flex justify-between items-end">
        <div>
          <span class="text-[#FF0055] font-bold text-sm uppercase">Avui - 21:00h</span>
          <h2 class="text-3xl font-black uppercase mt-2">Concert de Cloenda</h2>
        </div>
        <button class="bg-[#FF0055] text-black px-8 py-3 font-black uppercase text-sm hover:scale-105 transition-transform active:scale-95">
          Comprar
        </button>
      </div>
    </div>
  </div>
</template>
```

### Skills i Bones Pràctiques
Per al disseny i sistema d'estils, l'agent ha de consultar:
- **`tailwind-best-practices`**: Ús correcte de les utilitats de Tailwind.
- **`tailwind-design-system`**: Guia per mantenir la coherència visual en el projecte.

## ✅ Regla GET/CUD
- **GET**: L'estil depèn de les dades de l'esdeveniment (ex: si l'esdeveniment és "Sold Out", canviar l'estètica del botó).
- **CUD**: Feedback visual immediat (animacions) abans i després de les peticions de socket o API.