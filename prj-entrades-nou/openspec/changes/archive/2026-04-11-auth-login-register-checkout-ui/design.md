## Context

`stores/auth.js` existeix amb state token/user i actions setSession/clearSession. `middleware/auth.js` existeix. `pages/login.vue` és esquelet buit. No existeix `pages/checkout.vue`.

## Goals / Non-Goals

**Goals:**
- Login form funcional (POST /api/auth/login)
- Register form funcional (POST /api/auth/register)
- Persistència token (useCookie o similar)
- Middleware auth per a rutes protegides
- Pàgina /checkout nova

**Non-Goals:**
- No modificar backend API (AuthController ja existeix)
- No implmentar logout (fora d'abast)

## Decisions

1. **API client**: Utilitzar `$fetch` de Nuxt 3 (useFetch) per crides API
2. **Persistència**: useCookie per a token JWT (client-side)
3. **Middleware**: Utilitzar middleware/auth.js existent o crear nou
4. **Checkout**: Nova pàgina sota /checkout.vue o /checkout/index.vue

## Risks / Trade-offs

- [Risk] Token expirat → Fer refresh o logout
- [Risk] Errors API → Mostrar missatges d'error al formulari