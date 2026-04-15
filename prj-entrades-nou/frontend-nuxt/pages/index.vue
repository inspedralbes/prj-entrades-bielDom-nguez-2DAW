<template>
  <div class="home user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        {{ homeTitle }}
      </h1>
      <p v-if="homeLeadIsFeatured" class="user-page-lead">
        Esdeveniments recomanats segons el teu perfil i activitat.
      </p>
      <p v-else class="user-page-lead">
        Explora el catàleg: concerts, teatre i més prop teu.
      </p>
    </header>

    <p v-if="error" class="home__err home__pad">{{ error }}</p>
    <p v-else-if="loading" class="home__muted home__pad">Carregant…</p>

    <template v-else>
      <section class="home__section" aria-labelledby="h-featured">
        <h2 id="h-featured" class="home__sr-only">
          {{ homeListSr }}
        </h2>
        <ul v-if="featured.length" class="home__cards">
          <li v-for="ev in featured" :key="ev.id" class="home__card-item">
            <EventCardTr3
              :event="ev"
              link-from="home"
              :heart-filled="savedEventsStore.isSaved(ev.id)"
              @favorite-click="onFavoriteClick(ev.id)"
            />
          </li>
        </ul>
        <p v-else class="home__muted home__pad">Sense esdeveniments.</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useSavedEventsStore } from '~/stores/savedEvents';
import EventCardTr3 from '~/components/EventCardTr3.vue';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase.js';

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const auth = useAuthStore();
const savedEventsStore = useSavedEventsStore();

const loading = ref(true);
const error = ref('');
const featured = ref([]);
/* Fins al client (post-init), mateix text que SSR: evita mismatch d’hidratació si la cookie JWT arriba només al navegador. */
const homeUiReady = ref(false);

const homeTitle = computed(() => {
  if (!homeUiReady.value) {
    return 'Esdeveniments';
  }
  if (auth.token) {
    return 'Destacats';
  }
  return 'Esdeveniments';
});

const homeLeadIsFeatured = computed(() => {
  if (!homeUiReady.value) {
    return false;
  }
  if (auth.token) {
    return true;
  }
  return false;
});

const homeListSr = computed(() => {
  if (!homeUiReady.value) {
    return 'Llista d’esdeveniments';
  }
  if (auth.token) {
    return 'Llista de destacats';
  }
  return 'Llista d’esdeveniments';
});

async function loadSaved () {
  await savedEventsStore.fetchFromServer();
}

function onFavoriteClick (eventId) {
  if (!auth.token) {
    void navigateTo('/login');
    return;
  }
  void savedEventsStore.toggleFavorite(eventId).catch(() => {});
}

/**
 * Convidat: /api/search/events (mateix catàleg que Cerca).
 * Amb sessió: /api/feed/featured (destacats).
 */
async function fetchFeatured () {
  const base = resolvePublicApiBaseUrl(config.public.apiUrl).replace(/\/$/, '');
  const url = auth.token
    ? `${base}/api/feed/featured`
    : `${base}/api/search/events`;
  const f = await $fetch(url);
  featured.value = f.events || [];
}

onMounted(async () => {
  auth.init();
  homeUiReady.value = true;
  loading.value = true;
  error.value = '';
  try {
    await fetchFeatured();
    await loadSaved();
  } catch (e) {
    error.value = 'No s\'ha pogut carregar el feed.';
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.home {
  margin: 0 auto;
}

@media (min-width: 768px) {
  .home {
    max-width: 42rem;
  }
}

.home__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* El padding horitzontal ve de `.user-page`; evita desalinear el hero respecte les targetes */
.home__pad {
  padding: 0;
}

.home__section {
  margin-bottom: 2rem;
}

.home__cards {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.home__card-item {
  margin: 0;
}

.home__muted {
  color: var(--fg-muted);
}

.home__err {
  color: var(--error);
}
</style>


