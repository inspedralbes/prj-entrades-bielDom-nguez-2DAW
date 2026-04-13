## Why

El deute P-T042-044-LOGIN de `docs/tasksPendents.md` indica que `pages/login.vue` és un esquelet sense formulari funcional, i no existeix pàgina `/checkout`. Cal implementar login, registre, persistència JWT i middleware auth per completar el flux d'usuari.

## What Changes

- Implementar formulari login connectat a `POST /api/auth/login`
- Implementar formulari registre connectat a `POST /api/auth/register`
- Integrar persistència JWT amb Pinia (store existent o nou)
- Crear middleware auth per a rutes protegides (/tickets, /social, /saved, /profile)
- Crear pàgina `/checkout` amb middleware auth
- DoD: Cypress login → ruta protegida; sense token → redirect login

## Capabilities

### New Capabilities
- `auth-frontend-ui`: Formularis login/registre funcionals al frontend
- `auth-middleware`: Middleware per proteger rutes

### Modified Capabilities
- (none)

## Impact

- `frontend-nuxt/pages/login.vue` - Formularis funcionals
- `frontend-nuxt/pages/checkout.vue` - Nova pàgina
- `frontend-nuxt/middleware/auth.ts` - Middleware de protecció
- `frontend-nuxt/stores/auth.ts` - Pinia store