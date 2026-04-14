<template>
  <div class="home">
    <p v-if="error" class="home__err home__pad">{{ error }}</p>
    <p v-else-if="loading" class="home__muted home__pad">Carregant…</p>

    <template v-else>
      <section class="home__section" aria-labelledby="h-featured">
        <h2 id="h-featured" class="home__h2 home__pad">{{ auth.token ? 'Destacats' : 'Esdeveniments' }}</h2>
        <ul v-if="featured.length" class="home__cards">
          <li v-for="ev in featured" :key="ev.id" class="home__card-item">
            <EventCardTr3
              :event="ev"
              :show-heart="!!auth.token"
              :heart-filled="isSaved(ev.id)"
              @favorite-click="toggleSaved(ev.id)"
            />
          </li>
        </ul>
        <p v-else class="home__muted home__pad">Sense esdeveniments.</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import EventCardTr3 from '~/components/EventCardTr3.vue';

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const auth = useAuthStore();
const { getJson, postJson, deleteJson } = useAuthorizedApi();

const loading = ref(true);
const error = ref('');
const featured = ref([]);
const savedIds = ref(new Set());

function isSaved (eventId) {
  return savedIds.value.has(eventId);
}

async function loadSaved () {
  if (!auth.token) {
    savedIds.value = new Set();
    return;
  }
  try {
    const data = await getJson('/api/saved-events');
    const list = data.events || [];
    const next = new Set();
    for (let i = 0; i < list.length; i++) {
      next.add(list[i].id);
    }
    savedIds.value = next;
  } catch {
    savedIds.value = new Set();
  }
}

async function toggleSaved (eventId) {
  if (!auth.token) {
    return;
  }
  try {
    if (savedIds.value.has(eventId)) {
      await deleteJson(`/api/saved-events/${encodeURIComponent(eventId)}`);
      const next = new Set(savedIds.value);
      next.delete(eventId);
      savedIds.value = next;
    } else {
      await postJson('/api/saved-events', { event_id: eventId });
      const next = new Set(savedIds.value);
      next.add(eventId);
      savedIds.value = next;
    }
  } catch (e) {
    console.error(e);
  }
}

/**
 * Convidat: /api/search/events (mateix catàleg que Cerca).
 * Amb sessió: /api/feed/featured (destacats).
 */
async function fetchFeatured () {
  const base = (config.public.apiUrl || '').replace(/\/$/, '');
  const url = auth.token
    ? `${base}/api/feed/featured`
    : `${base}/api/search/events`;
  const f = await $fetch(url);
  featured.value = f.events || [];
}

onMounted(async () => {
  auth.init();
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
  padding: 0 0 2rem;
  margin: 0 auto;
}

@media (min-width: 768px) {
  .home {
    max-width: 42rem;
    padding: 0 1rem 2rem;
  }
}

.home__pad {
  padding: 0 1rem;
}

@media (min-width: 768px) {
  .home__pad {
    padding: 0;
  }
}

.home__h2 {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--accent);
  font-size: 0.95rem;
  margin: 0 0 0.75rem;
}

.home__section {
  margin-bottom: 2rem;
}

.home__cards {
  list-style: none;
  padding: 0 1rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

@media (min-width: 768px) {
  .home__cards {
    padding: 0;
  }
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
