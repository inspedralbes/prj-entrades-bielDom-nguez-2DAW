<template>
  <div class="map-page">
    <header class="map-page__head">
      <NuxtLink to="/search" class="map-page__back">← Llistat</NuxtLink>
      <h1 class="map-page__title">Mapa</h1>
    </header>

    <p v-if="mapError" class="map-page__err">{{ mapError }}</p>
    <p v-else-if="loading" class="map-page__muted">Carregant mapa…</p>

    <div ref="mapEl" class="map-page__canvas" role="application" aria-label="Mapa de cerca" />

    <p v-if="selected" class="map-page__panel">
      <strong>{{ selected.name }}</strong><br>
      <button type="button" class="map-page__btn" @click="openDirections">Com arribar</button>
      <NuxtLink :to="`/events/${selected.id}`" class="map-page__link">Detall</NuxtLink>
    </p>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useApi } from '~/composables/useApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';
import { useRoute } from 'vue-router';

definePageMeta({
  layout: 'default',
});

const route = useRoute();
const config = useRuntimeConfig();
const { fetchApi } = useApi();
const { load } = useGoogleMapsLoader();

const mapEl = ref(null);
const loading = ref(true);
const mapError = ref('');
const events = ref([]);
const selected = ref(null);

let map;
const markers = [];

let infoWindow = null;

function clearMarkers () {
  for (let i = 0; i < markers.length; i++) {
    markers[i].setMap(null);
  }
  markers.length = 0;
}

function buildSearchEventsUrl () {
  const params = new URLSearchParams();
  const q = route.query;
  if (q.q) {
    params.set('q', String(q.q));
  }
  if (q.category) {
    params.set('category', String(q.category));
  }
  if (q.date_from) {
    params.set('date_from', String(q.date_from));
  }
  const qs = params.toString();
  if (qs === '') {
    return '/api/search/events';
  }
  return `/api/search/events?${qs}`;
}

async function loadEvents () {
  const url = buildSearchEventsUrl();
  const data = await fetchApi(url);
  events.value = data.events || [];
}

function openDirections () {
  if (!selected.value) {
    return;
  }
  const lat = selected.value.map_lat;
  const lng = selected.value.map_lng;
  if (lat == null || lng == null) {
    return;
  }
  const la = Number(lat);
  const ln = Number(lng);
  const url = `https://www.google.com/maps/dir/?api=1&destination=${la},${ln}`;
  window.open(url, '_blank', 'noopener');
}

function fitMapToMarkers () {
  if (!map) {
    return;
  }
  if (markers.length === 0) {
    return;
  }

  const latQ = route.query.lat;
  const lngQ = route.query.lng;
  if (latQ && lngQ) {
    const la = parseFloat(String(latQ));
    const ln = parseFloat(String(lngQ));
    if (!isNaN(la) && !isNaN(ln)) {
      map.setCenter({ lat: la, lng: ln });
      map.setZoom(12);
      return;
    }
  }

  const bounds = new window.google.maps.LatLngBounds();
  for (let i = 0; i < markers.length; i++) {
    bounds.extend(markers[i].getPosition());
  }
  if (bounds.isEmpty()) {
    return;
  }
  map.fitBounds(bounds, 48);
}

async function renderMarkers () {
  clearMarkers();

  for (let i = 0; i < events.value.length; i++) {
    const ev = events.value[i];
    const rawLat = ev.map_lat;
    const rawLng = ev.map_lng;
    if (rawLat == null || rawLng == null) {
      continue;
    }
    const lat = Number(rawLat);
    const lng = Number(rawLng);
    if (Number.isNaN(lat) || Number.isNaN(lng)) {
      continue;
    }
    const pos = { lat, lng };
    const marker = new window.google.maps.Marker({
      position: pos,
      map,
      title: ev.name,
    });
    marker.addListener('click', () => {
      selected.value = ev;
      const venueName = ev.venue && ev.venue.name ? ev.venue.name : '';
      infoWindow.setContent(`
        <div style="padding:8px;max-width:200px;">
          <strong style="font-size:14px;">${ev.name}</strong>
          <p style="margin:4px 0;font-size:12px;color:#666;">${venueName}</p>
          <a href="/events/${ev.id}" style="color:#f7e628;font-weight:700;">Veure</a>
        </div>
      `);
      infoWindow.setPosition(pos);
      infoWindow.open(map);
      map.panTo(pos);
    });
    markers.push(marker);
  }

  fitMapToMarkers();
}

async function initMap () {
  const key = config.public.googleMapsKey;
  await load(key);

  const latParam = parseFloat(String(route.query.lat || ''));
  const lngParam = parseFloat(String(route.query.lng || ''));
  let center = { lat: 40.4168, lng: -3.7038 };
  let zoom = 6;
  if (!isNaN(latParam) && !isNaN(lngParam)) {
    center = { lat: latParam, lng: lngParam };
    zoom = 12;
  }

  map = new window.google.maps.Map(mapEl.value, {
    center,
    zoom,
    mapId: undefined,
  });

  infoWindow = new window.google.maps.InfoWindow();

  await renderMarkers();
}

onMounted(async () => {
  loading.value = true;
  mapError.value = '';
  selected.value = null;
  try {
    await loadEvents();
    await initMap();
  } catch (e) {
    mapError.value = e?.message || 'No s\'ha pogut inicialitzar el mapa (revisa la clau Maps).';
    console.error(e);
  } finally {
    loading.value = false;
  }
});

watch(
  () => route.query,
  async () => {
    if (!map) {
      return;
    }
    loading.value = true;
    mapError.value = '';
    try {
      await loadEvents();
      selected.value = null;
      await renderMarkers();
    } catch (e) {
      mapError.value = e?.message || 'Error en recarregar esdeveniments.';
      console.error(e);
    } finally {
      loading.value = false;
    }
  },
  { deep: true },
);

onUnmounted(() => {
  clearMarkers();
  if (infoWindow) {
    infoWindow.close();
  }
  map = null;
});
</script>

<style scoped>
.map-page {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 120px);
  padding: 0 1rem 1rem;
}
.map-page__head {
  display: flex;
  align-items: baseline;
  gap: 1rem;
  margin-bottom: 0.75rem;
}
.map-page__back {
  color: var(--accent);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 700;
}
.map-page__title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.35rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--accent);
}
.map-page__canvas {
  width: 100%;
  flex: 1;
  min-height: 360px;
  border-radius: 8px;
  border: 1px solid #2a2a2a;
}
.map-page__panel {
  margin-top: 0.75rem;
  padding: 0.75rem 1rem;
  background: #161616;
  border-radius: 8px;
  border: 1px solid #2a2a2a;
  color: #ddd;
}
.map-page__btn {
  margin-top: 0.5rem;
  margin-right: 0.75rem;
  padding: 0.35rem 0.75rem;
  background: #333;
  border: 1px solid #555;
  color: #fff;
  border-radius: 6px;
  cursor: pointer;
}
.map-page__link {
  color: var(--accent);
  font-weight: 700;
  margin-left: 0.25rem;
}
.map-page__muted {
  color: #888;
}
.map-page__err {
  color: #ff6b6b;
}
</style>
