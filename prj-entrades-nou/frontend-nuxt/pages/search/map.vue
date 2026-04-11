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
      <NuxtLink :to="`/events/${selected.id}/seats`" class="map-page__link">Detall</NuxtLink>
    </p>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useApi } from '~/composables/useApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const { fetchApi } = useApi();
const { load } = useGoogleMapsLoader();

const mapEl = ref(null);
const loading = ref(true);
const mapError = ref('');
const events = ref([]);
const selected = ref(null);

let map;
let markers = [];

function clearMarkers () {
  for (const m of markers) {
    m.setMap(null);
  }
  markers = [];
}

async function loadEvents () {
  const data = await fetchApi('/api/search/events');
  events.value = data.events || [];
}

function openDirections () {
  if (!selected.value) {
    return;
  }
  const { map_lat: lat, map_lng: lng } = selected.value;
  const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  window.open(url, '_blank', 'noopener');
}

onMounted(async () => {
  loading.value = true;
  mapError.value = '';
  selected.value = null;
  try {
    await loadEvents();
    const key = config.public.googleMapsKey;
    await load(key);
    const center = { lat: 41.3874, lng: 2.1686 };
    map = new window.google.maps.Map(mapEl.value, {
      center,
      zoom: 13,
      mapId: undefined,
    });
    clearMarkers();
    for (const ev of events.value) {
      if (ev.map_lat == null || ev.map_lng == null) {
        continue;
      }
      const pos = { lat: ev.map_lat, lng: ev.map_lng };
      const marker = new window.google.maps.Marker({
        position: pos,
        map,
        title: ev.name,
      });
      marker.addListener('click', () => {
        selected.value = ev;
        map.panTo(pos);
      });
      markers.push(marker);
    }
    if (events.value.length && events.value[0].map_lat != null) {
      map.setCenter({ lat: events.value[0].map_lat, lng: events.value[0].map_lng });
    }
  } catch (e) {
    mapError.value = e?.message || 'No s’ha pogut inicialitzar el mapa (revisa la clau Maps).';
    console.error(e);
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  clearMarkers();
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
  color: #888;
  text-decoration: none;
  font-size: 0.9rem;
}
.map-page__title {
  margin: 0;
  font-size: 1.2rem;
  color: #ff0055;
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
  color: #ff0055;
  margin-left: 0.25rem;
}
.map-page__muted {
  color: #888;
}
.map-page__err {
  color: #ff6b6b;
}
</style>
