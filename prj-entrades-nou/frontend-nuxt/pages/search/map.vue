<template>
  <MapTr3Layout :list-search-href="listSearchHref">
    <template #status>
      <p v-if="mapError" class="map-tr3__err">{{ mapError }}</p>
      <p v-else-if="loading && !mapReady" class="map-tr3__muted map-tr3__loading-msg">Carregant mapa…</p>
      <p v-else-if="!loading && !mapError && !hasGeoEvents" class="map-tr3__empty-msg">
        Cap esdeveniment amb ubicació al mapa.
      </p>
    </template>
    <template #map>
      <div ref="mapEl" class="map-tr3__gmaps" role="application" aria-label="Mapa de cerca" />
    </template>
  </MapTr3Layout>
</template>

<script setup>
import '~/assets/css/map-tr3-google-overrides.css';
import MapTr3Layout from '~/components/search/MapTr3Layout.vue';
import { useTr3SearchMapPage } from '~/composables/useTr3SearchMapPage';

definePageMeta({
  layout: 'default',
});

const { mapEl, loading, mapReady, mapError, listSearchHref, hasGeoEvents } = useTr3SearchMapPage();
</script>

<style scoped>
/* Contingut dels slots es compila a aquesta pàgina: estats + contenidor del mapa. */
.map-tr3__loading-msg {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 8;
  margin: 0;
}

.map-tr3__empty-msg {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 8;
  margin: 0;
  padding: 0.65rem 1rem;
  max-width: 18rem;
  text-align: center;
  font-size: 0.9rem;
  color: #ccc7ac;
  background: rgba(19, 19, 19, 0.82);
  border: 1px solid rgba(74, 71, 51, 0.35);
  border-radius: 0.65rem;
  pointer-events: none;
}

.map-tr3__err {
  position: absolute;
  top: 5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 12;
  color: #ffb4ab;
  padding: 0.5rem 0.75rem;
  background: rgba(147, 0, 10, 0.35);
  border-radius: 0.5rem;
  max-width: 90%;
  text-align: center;
}

.map-tr3__muted {
  color: #959178;
}

.map-tr3__gmaps {
  width: 100%;
  height: 100%;
  min-height: 0;
}
</style>
