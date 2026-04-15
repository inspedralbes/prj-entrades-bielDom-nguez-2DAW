//================================ CONFIG NUXT (Speckit 001 — JS sense TypeScript)
import { defineNuxtConfig } from 'nuxt/config';

export default defineNuxtConfig({
  css: ['~/assets/css/app.css'],
  app: {
    head: {
      link: [
        {
          rel: 'icon',
          type: 'image/png',
          href: '/favicon.png',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Epilogue:wght@400;500;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
        },
        /* Pes 400: ha de coincidir amb font-weight del CSS; l’URL amb wght@600 només registrava 600 i els icones es veien com a text */
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
        },
      ],
    },
  },
  compatibilityDate: '2024-11-01',
  /* Necessari amb Nuxt 3.21+: el schema resol experimental.treeshakeClientOnly abans que `future`
   * tingui valor per defecte; sense això `get("future")` és undefined al build (GH Actions). */
  future: {
    compatibilityVersion: 3,
  },
  // Evita error Vite "Failed to resolve import #app-manifest" (Nuxt 3.20+ / primer arranque sense .nuxt).
  // Veure https://github.com/nuxt/nuxt/issues/33606 — es pot reactivar quan no calgui el workaround.
  experimental: {
    appManifest: false,
  },
  // DevTools: en Windows alguns projectes mostren "Unable to add filesystem: <illegal path>"
  devtools: { enabled: false },
  modules: ['@pinia/nuxt'],
  // Docker Desktop (Windows): el bind mount sovint no notifica canvis; el polling evita servir codi antic.
  vite: {
    server: {
      watch: {
        usePolling: true,
        interval: 400,
      },
    },
  },
  runtimeConfig: {
    // Només servidor (middleware SSR, server routes): dins Docker «localhost:8000» no és backend-api.
    apiInternalUrl: process.env.NUXT_API_INTERNAL_URL || '',
    public: {
      apiUrl: process.env.NUXT_PUBLIC_API_URL || 'http://localhost:8000',
      socketUrl: process.env.NUXT_PUBLIC_SOCKET_URL || 'http://localhost:3001',
      googleMapsKey: process.env.NUXT_PUBLIC_GOOGLE_MAPS_KEY || '',
    },
  },
  // Ruta antiga «adminlogs» → pàgina actual (evita 404 si hi ha enllaços guardats).
  routeRules: {
    '/admin/adminlogs': { redirect: '/admin/logs' },
    '/admin/perfil': { redirect: '/admin/profile' },
    '/checkout': { redirect: '/tickets' },
  },
});
