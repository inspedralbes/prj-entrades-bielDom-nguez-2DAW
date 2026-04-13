## 1. Login form

- [x] 1.1 Implementar formulari login a pages/login.vue
- [x] 1.2 Connectar a POST /api/auth/login
- [x] 1.3 Gestionar errors d credencials

## 2. Register form

- [x] 2.1 Afegir formulari registre a pages/login.vue
- [x] 2.2 Connectar a POST /api/auth/register
- [x] 2.3 Auto-login despres de registre

## 3. Token persistence

- [x] 3.1 Utilitzar useCookie per persistir token
- [x] 3.2 Actualitzar stores/auth.js

## 4. Middleware

- [x] 4.1 Verificar middleware/auth.js funciona
- [x] 4.2 Aplicar a rutes /tickets, /social, /saved, /profile (ja aplicat via definePageMeta)

## 5. Checkout page

- [x] 5.1 Crear pages/checkout.vue
- [x] 5.2 Aplicar middleware auth

## 6. Test and document

- [x] 6.1 Cypress: login -> ruta protegida
- [x] 6.2 Cypress: sense token -> redirect login