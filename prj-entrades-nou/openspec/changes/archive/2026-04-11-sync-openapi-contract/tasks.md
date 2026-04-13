## 1. Analyze current state

- [x] 1.1 Comparar endpoints existents a openapi.yaml amb api.php
- [x] 1.2 Identificar endpoints que falten al contracte
- [x] 1.3 Revisar esquemes existents vs models reals

## 2. Add missing Phase 7 endpoints

- [x] 2.1 Afegir /feed/featured (públic)
- [x] 2.2 Afegir /feed/for-you (autenticat)
- [x] 2.3 Afegir /search/events (públic)
- [x] 2.4 Afegir /saved-events (autenticat)
- [x] 2.5 Afegir /user/profile (GET, PATCH - autenticat)
- [x] 2.6 Afegir /user/settings (PATCH - autenticat)

## 3. Fix existing endpoints

- [x] 3.1 Afegir /holds/{holdId}/time (GET)
- [x] 3.2 Afegir /orders/{order}/confirm-payment (POST)
- [x] 3.3 Corregir path parameters (eventId vs eventId)
- [x] 3.4 Afegir missing security (alguns endpoints sense Bearer)

## 4. Improve schemas and error handling

- [x] 4.1 Afegir exemples a tots els schemas de resposta
- [x] 4.2 Afegir codis d'error 4xx a tots els endpoints
- [x] 4.3 Actualitzar schema versions si cal

## 5. Validate and finalize

- [x] 5.1 Validar YAML OpenAPI 3.1 (swagger-cli o similar)
- [x] 5.2 Verificar que totes les rutes de api.php estan documentades
- [x] 5.3 Incrementar versió del document si canvis significatius
- [x] 5.4 Verificar coherència backend vs OpenAPI
- [x] 5.5 Afegir exemples als nous schemas
- [x] 5.6 Afegir tag Health