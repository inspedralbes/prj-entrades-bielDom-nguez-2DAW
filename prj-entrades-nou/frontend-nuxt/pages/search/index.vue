<template>
  <div class="search user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        Cerca
      </h1>
      <p class="user-page-lead">
        Filtra per ciutat, text, categoria i data; obre el mapa per veure resultats geolocalitzats.
      </p>
    </header>

    <NuxtLink :to="mapSearchHref" class="search__fab" title="Mapa">Mapa</NuxtLink>

    <form class="search__form" @submit.prevent="runSearch">
      <label class="search__label">
        Ciutat
        <div class="city-search">
          <input 
            v-model="cityQuery" 
            type="text" 
            class="search__input" 
            placeholder="Cerca ciutat... (ex: Madrid, Barcelona)"
            @input="onCityInput"
          />
          <ul v-if="cityResults.length" class="city-results">
            <li 
              v-for="city in cityResults" 
              :key="city.name"
              @click="selectCity(city)"
              class="city-result"
            >
              {{ city.name }}
            </li>
          </ul>
        </div>
      </label>
      <label class="search__label">
        Text
        <input v-model="q" type="search" class="search__input" placeholder="Nom de l'esdeveniment">
      </label>
      <label class="search__label">
        Categoria
        <input v-model="category" type="text" class="search__input" placeholder="opcional">
      </label>
      <label class="search__label">
        Data (des d’aquest dia)
        <input v-model="dateFrom" type="date" class="search__input">
      </label>
      <button type="submit" class="search__btn" :disabled="loading">
        Cercar
      </button>
    </form>

    <p v-if="error" class="search__err">{{ error }}</p>
    <p v-else-if="loading" class="search__muted">Carregant…</p>

    <ul v-else class="search__cards">
      <li v-for="ev in events" :key="ev.id" class="search__card-item">
        <EventCardTr3
          :event="ev"
          :show-heart="!!auth.token"
          :heart-filled="savedIds.has(ev.id)"
          @favorite-click="toggleSaved(ev.id)"
        />
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useApi } from '~/composables/useApi';
import EventCardTr3 from '~/components/EventCardTr3.vue';

const emit = defineEmits(['city-selected']);

definePageMeta({
  layout: 'default',
});

const auth = useAuthStore();
const { fetchApi } = useApi();
const { getJson, postJson, deleteJson } = useAuthorizedApi();

const q = ref('');
const category = ref('');
const dateFrom = ref('');
const loading = ref(false);
const error = ref('');
const events = ref([]);
const savedIds = ref(new Set());

const mapSearchHref = computed(() => {
  const params = new URLSearchParams();
  if (q.value) {
    params.set('q', q.value);
  }
  if (category.value) {
    params.set('category', category.value);
  }
  if (dateFrom.value) {
    params.set('date_from', dateFrom.value);
  }
  if (selectedCity.value) {
    params.set('lat', String(selectedCity.value.lat));
    params.set('lng', String(selectedCity.value.lng));
  }
  const qs = params.toString();
  if (qs === '') {
    return '/search/map';
  }
  return `/search/map?${qs}`;
});

const cityQuery = ref('');
const cityResults = ref([]);
const selectedCity = ref(null);
let cityDebounce = null;

function onCityInput() {
  if (cityDebounce) clearTimeout(cityDebounce);
  if (cityQuery.value.length < 2) {
    cityResults.value = [];
    return;
  }
  cityDebounce = setTimeout(async () => {
    try {
      const base = useRuntimeConfig().public.apiUrl || '';
      const res = await $fetch(`${base.replace(/\/$/, '')}/api/cities/search?q=${encodeURIComponent(cityQuery.value)}`);
      cityResults.value = res.cities || [];
    } catch {}
  }, 300);
}

function selectCity(city) {
  selectedCity.value = city;
  cityQuery.value = city.name;
  cityResults.value = [];
}

async function loadSaved () {
  if (!auth.token) {
    return;
  }
  try {
    const data = await getJson('/api/saved-events');
    const list = data.events || [];
    const ids = new Set();
    for (let i = 0; i < list.length; i++) {
      ids.add(list[i].id);
    }
    savedIds.value = ids;
  } catch {
    /* ignore */
  }
}

async function runSearch () {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (q.value) {
      params.set('q', q.value);
    }
    if (category.value) {
      params.set('category', category.value);
    }
    if (dateFrom.value) {
      params.set('date_from', dateFrom.value);
    }
    if (selectedCity.value) {
      params.set('lat', selectedCity.value.lat);
      params.set('lng', selectedCity.value.lng);
    }
    const qs = params.toString();
    const path = `/api/search/events${qs ? `?${qs}` : ''}`;
    const data = await fetchApi(path);
    events.value = data.events || [];

    if (selectedCity.value) {
      emit('city-selected', selectedCity.value);
    }
  } catch (e) {
    error.value = 'Error de cerca.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function toggleSaved (eventId) {
  if (!auth.token) {
    return;
  }
  try {
    if (savedIds.value.has(eventId)) {
      await deleteJson(`/api/saved-events/${eventId}`);
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

onMounted(async () => {
  await loadSaved();
  await runSearch();
});
</script>

<style scoped>
.search {
  position: relative;
  max-width: 42rem;
  margin: 0 auto;
}
.search__fab {
  position: fixed;
  right: 1rem;
  bottom: calc(56px + 1rem);
  z-index: 50;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 50%;
  background: var(--accent);
  color: var(--accent-on);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.7rem;
  font-weight: 800;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
}
@media (min-width: 900px) {
  .search__fab {
    bottom: 1.5rem;
  }
}
.search__form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
.search__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: #aaa;
}
.search__input {
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
}
.search__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}
.search__btn {
  align-self: flex-start;
  padding: 0.55rem 1.2rem;
  background: var(--accent);
  border: none;
  border-radius: 9999px;
  color: var(--accent-on);
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
  cursor: pointer;
}
.search__btn:disabled {
  opacity: 0.6;
}
.search__cards {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.search__card-item {
  margin: 0;
}

.search__muted {
  color: var(--fg-muted);
}
.search__err {
  color: #ff6b6b;
}
.city-search {
  position: relative;
}
.city-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #1a1a1a;
  border: 1px solid #333;
  border-radius: 6px;
  list-style: none;
  padding: 0;
  margin: 4px 0 0;
  z-index: 100;
  max-height: 200px;
  overflow-y: auto;
}
.city-result {
  padding: 0.65rem 0.85rem;
  cursor: pointer;
  border-bottom: 1px solid #222;
}
.city-result:last-child {
  border-bottom: none;
}
.city-result:hover {
  background: #2a2a2a;
}
</style>
