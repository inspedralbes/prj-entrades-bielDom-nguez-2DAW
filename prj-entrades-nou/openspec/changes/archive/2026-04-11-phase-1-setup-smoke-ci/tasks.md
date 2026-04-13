## 1. Research and setup

- [x] 1.1 Verificar estructura existent de .github/workflows/
- [x] 1.2 Revisar docker/dev/docker-compose.yml serveis i healthchecks
- [x] 1.3 Verificar que GET /api/health existeix al backend

## 2. Create GitHub Actions workflow

- [x] 2.1 Crear .github/workflows/smoke.yml
- [x] 2.2 Afegir step docker compose up --build
- [x] 2.3 Afegir step wait-for-health amb script
- [x] 2.4 Afegir smoke test: curl GET /api/health

## 3. Add Cypress (opcional)

- [x] 3.1 Comprovar si frontend-nuxt/cypress.config.* existeix
- [x] 3.2 Afegir step npm run cypress:run si existeix

## 4. Add T001 verification (opcional)

- [x] 4.1 Afegir step verificar monorepo paths existents

## 5. Test and document

- [x] 5.1 Executar workflow locally (docker compose equivalent)
- [x] 5.2 Verificar que workflow funciona
- [x] 5.3 Documentar ús a docker/dev/README.md