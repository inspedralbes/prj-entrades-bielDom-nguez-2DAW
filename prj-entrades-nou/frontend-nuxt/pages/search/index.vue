<template>
  <div class="search">
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

    <ul v-else class="search__list">
      <li v-for="ev in events" :key="ev.id" class="search__item">
        <NuxtLink :to="`/events/${ev.id}`" class="search__rowlink">
          <div
            class="search__thumb"
            :class="{ 'search__thumb--empty': !imageSrc(ev) }"
          >
            <img
              v-if="imageSrc(ev)"
              class="search__thumb-img"
              :src="imageSrc(ev)"
              :alt="imageAlt(ev)"
              loading="lazy"
              decoding="async"
              width="120"
              height="120"
            />
          </div>
          <div class="search__main">
            <span class="search__title">{{ ev.name }}</span>
            <p class="search__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</p>
          </div>
        </NuxtLink>
        <button
          v-if="auth.token"
          type="button"
          class="search__heart"
          :aria-pressed="savedIds.has(ev.id)"
          @click.stop.prevent="toggleSaved(ev.id)"
        >
          {{ savedIds.has(ev.id) ? '♥' : '♡' }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useApi } from '~/composables/useApi';
import { useEventImage } from '~/composables/useEventImage';

const emit = defineEmits(['city-selected']);

definePageMeta({
  layout: 'default',
});

const auth = useAuthStore();
const { fetchApi } = useApi();
const { getJson, postJson, deleteJson } = useAuthorizedApi();
const { imageSrc, imageAlt } = useEventImage();

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

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', { dateStyle: 'medium', timeStyle: 'short' });
  } catch {
    return iso;
  }
}

async function loadSaved () {
  if (!auth.token) {
    return;
  }
  try {
    const data = await getJson('/api/saved-events');
    const ids = new Set((data.events || []).map((e) => e.id));
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
  padding: 0 1rem 2rem;
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
  background: #ff0055;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-size: 0.75rem;
  font-weight: 700;
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
  background: #ff0055;
  border: none;
  border-radius: 6px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.search__btn:disabled {
  opacity: 0.6;
}
.search__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.search__item {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 0.65rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid #222;
}
.search__rowlink {
  flex: 1;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  min-width: 0;
  text-decoration: none;
  color: inherit;
}
.search__rowlink:hover .search__title {
  color: #ff0055;
}
.search__thumb {
  flex: 0 0 5.5rem;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: 8px;
  overflow: hidden;
  background: #222;
  position: relative;
}
.search__thumb--empty {
  background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
}
.search__thumb--empty::after {
  content: '—';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  color: #555;
}
.search__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.search__main {
  flex: 1;
  min-width: 0;
}
.search__title {
  display: block;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
}
.search__meta {
  margin: 0.3rem 0 0;
  font-size: 0.85rem;
  color: #888;
}
.search__heart {
  flex-shrink: 0;
  background: transparent;
  border: none;
  font-size: 1.35rem;
  cursor: pointer;
  line-height: 1;
  color: #ff0055;
}
.search__muted {
  color: #888;
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
