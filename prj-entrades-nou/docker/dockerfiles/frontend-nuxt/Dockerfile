# Imatge dev — Node 20 + dependències Nuxt (sense Cypress: no cal al contenidor i el seu postinstall
# intenta baixar binaris des de download.cypress.io, cosa que sovint falla dins del build Docker).
# Espeig public.ecr.aws (mateixa imatge que library/node; menys pulls des de docker.io).
FROM public.ecr.aws/docker/library/node:20-bookworm-slim

WORKDIR /app

COPY frontend-nuxt/package.json frontend-nuxt/package-lock.json ./
# --ignore-scripts: no executar encara `nuxt prepare` (cal tot el codi sota /app).
RUN npm ci --omit=dev --ignore-scripts

COPY frontend-nuxt/ ./

# postinstall del package.json = nuxt prepare (genera .nuxt).
RUN npm run postinstall

EXPOSE 3000
