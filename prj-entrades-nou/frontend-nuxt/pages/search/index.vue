<template>
  <div class="search user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        Cerca
      </h1>
      <p class="user-page-lead">
        Filtra per ciutat, text i categoria; obre el mapa per veure resultats geolocalitzats.
      </p>
    </header>

    <NuxtLink :to="mapSearchHref" class="search__fab" title="Mapa">
      <span class="search__fab-icon" aria-hidden="true">🗺</span>
      <span>MAPA</span>
    </NuxtLink>

    <form class="search__form" @submit.prevent="runSearch">
      <UserSearchInput
        v-model="searchQuery"
        input-id="search-main-input"
        sr-label="Cerca per artista o ciutat"
        placeholder="Cerca esdeveniment, ciutat, poble…"
        @input="onSearchInput"
        @clear="onClearSearch"
        @focus="onSearchFocus"
        @blur="onSearchBlur"
      >
        <div
          v-if="showSuggestPanel"
          class="search-suggest"
          role="listbox"
          aria-label="Suggeriments de cerca"
          @mousedown.prevent="onSuggestMouseDown"
        >
          <p v-if="suggestLoading" class="search-suggest__state">
            Cercant…
          </p>
          <template v-else>
            <template v-for="row in suggestRows" :key="row.key">
              <button
                v-if="row.kind === 'event'"
                type="button"
                class="search-suggest__row search-suggest__row--event"
                role="option"
                @click="goToEvent(row.event)"
              >
                <div
                  class="search-suggest__thumb"
                  :class="{ 'search-suggest__thumb--empty': !eventThumb(row.event) }"
                >
                  <img
                    v-if="eventThumb(row.event)"
                    class="search-suggest__img"
                    :src="eventThumb(row.event)"
                    :alt="eventImageAlt(row.event)"
                    loading="lazy"
                    decoding="async"
                  >
                </div>
                <div class="search-suggest__meta">
                  <span class="search-suggest__title">{{ row.event.name }}</span>
                  <span class="search-suggest__sub">{{ formatEventWhen(row.event.starts_at) }}</span>
                </div>
              </button>
              <button
                v-else-if="row.kind === 'place'"
                type="button"
                class="search-suggest__row search-suggest__row--place"
                role="option"
                @click="selectGooglePlace(row)"
              >
                <span class="material-symbols-rounded search-suggest__pin" aria-hidden="true">location_on</span>
                <span class="search-suggest__place-label">{{ row.label }}</span>
              </button>
              <button
                v-else-if="row.kind === 'city'"
                type="button"
                class="search-suggest__row search-suggest__row--place"
                role="option"
                @click="selectVenueCity(row)"
              >
                <span class="material-symbols-rounded search-suggest__pin" aria-hidden="true">location_on</span>
                <span class="search-suggest__place-label">{{ row.name }}</span>
              </button>
            </template>
            <p v-if="!suggestRows.length && searchQuery.trim().length >= 2" class="search-suggest__state">
              Cap suggeriment.
            </p>
          </template>
        </div>
      </UserSearchInput>

      <div class="search-categories" role="group" aria-label="Filtres de cerca">
        <button
          v-for="cat in categoryOptions"
          :key="cat.value"
          type="button"
          class="search-chip"
          :class="{ 'search-chip--active': category === cat.value }"
          @click="setCategory(cat.value)"
        >
          {{ cat.label }}
        </button>
      </div>
    </form>

    <p v-if="error" class="search__err">{{ error }}</p>
    <p v-else-if="loading" class="search__muted">Carregant…</p>

    <ul v-else class="search__cards">
      <li v-for="ev in events" :key="ev.id" class="search__card-item">
        <EventCardTr3
          :event="ev"
          link-from="search"
          :show-heart="!!auth.token"
          :heart-filled="savedEventsStore.isSaved(ev.id)"
          @favorite-click="onFavoriteClick(ev.id)"
        />
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useSavedEventsStore } from '~/stores/savedEvents';
import { useApi } from '~/composables/useApi';
import { useEventImage } from '~/composables/useEventImage';
import EventCardTr3 from '~/components/EventCardTr3.vue';
import UserSearchInput from '~/components/UserSearchInput.vue';

const emit = defineEmits(['city-selected']);

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const auth = useAuthStore();
const savedEventsStore = useSavedEventsStore();
const { fetchApi } = useApi();
const { imageSrc, imageAlt } = useEventImage();

const q = ref('');
const category = ref('');
const loading = ref(false);
const error = ref('');
const events = ref([]);

const mapSearchHref = computed(() => {
  const params = new URLSearchParams();
  if (q.value) {
    params.set('q', q.value);
  }
  if (category.value) {
    params.set('category', category.value);
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

const searchQuery = ref('');
const selectedCity = ref(null);
let cityDebounce = null;
let suggestDebounce = null;

const suggestRows = ref([]);
const suggestLoading = ref(false);
const suggestPanelVisible = ref(true);
let suggestBlurTimer = null;

const categoryOptions = [
  { value: '', label: 'Tots' },
  { value: 'music', label: 'Música' },
  { value: 'sports', label: 'Esports' },
  { value: 'theatre', label: 'Teatre' },
];

const showSuggestPanel = computed(() => {
  if (!suggestPanelVisible.value) {
    return false;
  }
  if (searchQuery.value.trim().length < 2) {
    return false;
  }
  return true;
});

function eventThumb (ev) {
  return imageSrc(ev);
}

function eventImageAlt (ev) {
  return imageAlt(ev);
}

function formatEventWhen (iso) {
  if (!iso) {
    return '—';
  }
  try {
    const d = new Date(iso);
    const datePart = d.toLocaleDateString('ca-ES', { day: 'numeric', month: 'short' });
    const timePart = d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
    return `${datePart} · ${timePart}`;
  } catch {
    return '—';
  }
}

function setCategory (nextCategory) {
  category.value = nextCategory;
  runSearch();
}

function onSearchFocus () {
  if (suggestBlurTimer !== null) {
    clearTimeout(suggestBlurTimer);
    suggestBlurTimer = null;
  }
  suggestPanelVisible.value = true;
}

function onSearchBlur () {
  suggestBlurTimer = setTimeout(() => {
    suggestPanelVisible.value = false;
    suggestBlurTimer = null;
  }, 180);
}

function onSuggestMouseDown () {
  if (suggestBlurTimer !== null) {
    clearTimeout(suggestBlurTimer);
    suggestBlurTimer = null;
  }
}

function onClearSearch () {
  q.value = '';
  selectedCity.value = null;
  suggestRows.value = [];
  suggestLoading.value = false;
  void runSearch();
}

function onSearchInput () {
  q.value = searchQuery.value;
  selectedCity.value = null;
  if (cityDebounce) {
    clearTimeout(cityDebounce);
  }
  if (suggestDebounce) {
    clearTimeout(suggestDebounce);
  }
  if (searchQuery.value.trim().length < 2) {
    suggestRows.value = [];
    suggestLoading.value = false;
    return;
  }
  suggestLoading.value = true;
  suggestDebounce = setTimeout(() => {
    void loadSuggestions();
  }, 280);
}

function buildSuggestRows (eventList, placeList, cityList) {
  const rows = [];
  const evn = eventList.length;
  for (let i = 0; i < evn && i < 6; i++) {
    const ev = eventList[i];
    rows.push({
      kind: 'event',
      key: 'e-' + String(ev.id),
      event: ev,
    });
  }
  const pln = placeList.length;
  for (let j = 0; j < pln && j < 7; j++) {
    const p = placeList[j];
    rows.push({
      kind: 'place',
      key: 'p-' + String(p.place_id),
      placeId: p.place_id,
      label: p.label,
    });
  }
  const seen = {};
  for (let s = 0; s < pln; s++) {
    const pl = placeList[s];
    seen[String(pl.label).toLowerCase()] = true;
  }
  const cn = cityList.length;
  for (let c = 0; c < cn && c < 4; c++) {
    const city = cityList[c];
    const nm = city.name;
    if (seen[String(nm).toLowerCase()] === true) {
      continue;
    }
    rows.push({
      kind: 'city',
      key: 'c-' + String(nm) + '-' + String(c),
      name: nm,
      lat: city.lat,
      lng: city.lng,
    });
  }
  return rows;
}

async function loadSuggestions () {
  const t = searchQuery.value.trim();
  if (t.length < 2) {
    suggestRows.value = [];
    suggestLoading.value = false;
    return;
  }
  suggestLoading.value = true;
  try {
    const params = new URLSearchParams();
    params.set('q', t);
    params.set('limit', '8');
    if (category.value) {
      params.set('category', category.value);
    }
    const evPath = `/api/search/events?${params.toString()}`;
    const citiesPath = `/api/cities/search?q=${encodeURIComponent(t)}`;
    const placesPath = `/api/places/autocomplete?q=${encodeURIComponent(t)}`;

    const pair = await Promise.all([
      fetchApi(evPath),
      fetchApi(citiesPath),
    ]);
    const evData = pair[0];
    const citiesData = pair[1];
    let placesData = { places: [] };
    try {
      placesData = await fetchApi(placesPath);
    } catch {
      placesData = { places: [] };
    }

    const eventList = evData.events || [];
    const cityList = citiesData.cities || [];
    const placeList = placesData.places || [];

    suggestRows.value = buildSuggestRows(eventList, placeList, cityList);
  } catch {
    suggestRows.value = [];
  } finally {
    suggestLoading.value = false;
  }
}

async function goToEvent (ev) {
  suggestRows.value = [];
  suggestPanelVisible.value = false;
  await navigateTo({ path: `/events/${ev.id}`, query: { from: 'search' } });
}

async function selectGooglePlace (row) {
  try {
    const data = await fetchApi(`/api/places/details?place_id=${encodeURIComponent(row.placeId)}`);
    const lat = data.lat;
    const lng = data.lng;
    if (lat == null || lng == null) {
      return;
    }
    let label = row.label;
    if (data.formatted_address && String(data.formatted_address).trim() !== '') {
      label = String(data.formatted_address);
    }
    selectedCity.value = {
      name: label,
      lat,
      lng,
    };
    searchQuery.value = label;
    q.value = label;
    suggestRows.value = [];
    suggestPanelVisible.value = false;
    await runSearch();
    if (selectedCity.value) {
      emit('city-selected', selectedCity.value);
    }
  } catch (e) {
    console.error(e);
  }
}

function selectVenueCity (row) {
  selectedCity.value = {
    name: row.name,
    lat: row.lat,
    lng: row.lng,
  };
  searchQuery.value = row.name;
  q.value = row.name;
  suggestRows.value = [];
  suggestPanelVisible.value = false;
  void runSearch();
  if (selectedCity.value) {
    emit('city-selected', selectedCity.value);
  }
}

async function loadSaved () {
  await savedEventsStore.fetchFromServer();
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

function onFavoriteClick (eventId) {
  if (!auth.token) {
    void navigateTo('/login');
    return;
  }
  void savedEventsStore.toggleFavorite(eventId).catch(() => {});
}

onMounted(async () => {
  searchQuery.value = q.value;
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
  bottom: calc(var(--footer-stack) + 1rem);
  z-index: 50;
  min-width: 9rem;
  height: 3.25rem;
  border-radius: 9999px;
  background: #f7e628;
  color: #111;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  text-decoration: none;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.05rem;
  font-weight: 900;
  letter-spacing: 0.04em;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(0, 0, 0, 0.15);
}
.search__fab-icon {
  font-size: 1.15rem;
  line-height: 1;
}
@media (min-width: 900px) {
  .search__fab {
    bottom: 1.5rem;
  }
}
.search__form {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  margin-bottom: 1.25rem;
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
.search-categories {
  display: flex;
  flex-wrap: nowrap;
  justify-content: flex-start;
  align-items: center;
  gap: 0.65rem;
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.search-categories::-webkit-scrollbar {
  width: 0;
  height: 0;
  display: none;
  background: transparent;
}
.search-chip {
  flex-shrink: 0;
  border: 1px solid #2c2c2c;
  background: #222;
  color: #b8b8b8;
  border-radius: 9999px;
  padding: 0.58rem 1.25rem;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}
.search-chip--active {
  background: #f7e628;
  color: #6e6600;
  border-color: #f7e628;
}

.search-suggest {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 6px;
  z-index: 120;
  max-height: min(70vh, 22rem);
  overflow-x: hidden;
  overflow-y: auto;
  border-radius: 12px;
  border: 1px solid rgba(255, 238, 50, 0.18);
  background: #141414;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
}

.search-suggest__state {
  margin: 0;
  padding: 0.85rem 1rem;
  font-size: 0.85rem;
  color: #999;
}

.search-suggest__row {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 0.75rem;
  padding: 0.65rem 0.75rem;
  border: none;
  border-bottom: 1px solid #222;
  background: transparent;
  color: #e5e2e1;
  text-align: left;
  cursor: pointer;
  font: inherit;
  box-sizing: border-box;
}

.search-suggest__row:last-child {
  border-bottom: none;
}

.search-suggest__row:hover {
  background: rgba(247, 230, 40, 0.08);
}

.search-suggest__row--event {
  gap: 0.65rem;
}

.search-suggest__thumb {
  flex-shrink: 0;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 10px;
  overflow: hidden;
  background: #2a2a2a;
}

.search-suggest__thumb--empty {
  background: linear-gradient(145deg, #333, #1a1a1a);
}

.search-suggest__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.search-suggest__meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.search-suggest__title {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
  font-size: 0.92rem;
  line-height: 1.2;
  color: #fff;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.search-suggest__sub {
  font-size: 0.78rem;
  color: #a8a49a;
}

.search-suggest__row--place {
  padding: 0.7rem 0.85rem;
}

.search-suggest__pin {
  flex-shrink: 0;
  font-size: 1.45rem;
  color: #f7e628;
  line-height: 1;
}

.search-suggest__place-label {
  font-size: 0.88rem;
  line-height: 1.3;
  color: #e5e2e1;
}
</style>
