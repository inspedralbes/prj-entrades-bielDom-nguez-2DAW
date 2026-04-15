<template>
  <div class="map-tr3-page">
    <div class="map-tr3">
      <div class="map-tr3__float map-tr3__float--top">
        <NuxtLink :to="listSearchHref" class="map-tr3__back" aria-label="Tornar al llistat">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        </NuxtLink>
      </div>

      <div class="map-tr3__map-block">
        <p v-if="mapError" class="map-tr3__err">{{ mapError }}</p>
        <p v-else-if="loading && !mapReady" class="map-tr3__muted map-tr3__loading-msg">Carregant mapa…</p>
        <p v-else-if="!loading && !mapError && !hasGeoEvents" class="map-tr3__empty-msg">
          Cap esdeveniment amb ubicació al mapa.
        </p>
        <div class="map-tr3__map-stack">
          <div class="map-tr3__filter-layer">
            <div ref="mapEl" class="map-tr3__gmaps" role="application" aria-label="Mapa de cerca" />
          </div>
          <div class="map-tr3__vignette" aria-hidden="true" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '~/composables/useApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';
import { useEventImage } from '~/composables/useEventImage';
import { buildTr3EventMarkerDataUrl } from '~/utils/tr3MapMarkerIcon';
import { buildTr3GoogleMapOptions } from '~/utils/tr3MapOptions';

definePageMeta({
  layout: 'default',
});

const route = useRoute();
const config = useRuntimeConfig();
const { fetchApi } = useApi();
const { load } = useGoogleMapsLoader();
const { imageSrc, imageAlt } = useEventImage();

const mapEl = ref(null);

const loading = ref(true);
const mapReady = ref(false);
const mapError = ref('');
const events = ref([]);

let map = null;
const markers = [];
let infoWindow = null;

const listSearchHref = computed(() => {
  const params = new URLSearchParams();
  const rq = route.query;
  if (rq.q) {
    params.set('q', String(rq.q));
  }
  if (rq.category) {
    params.set('category', String(rq.category));
  }
  if (rq.lat) {
    params.set('lat', String(rq.lat));
  }
  if (rq.lng) {
    params.set('lng', String(rq.lng));
  }
  const qs = params.toString();
  if (qs === '') {
    return '/search';
  }
  return `/search?${qs}`;
});

const hasGeoEvents = computed(() => {
  const list = events.value;
  for (let i = 0; i < list.length; i++) {
    const ev = list[i];
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
    return true;
  }
  return false;
});

function buildSearchEventsPath () {
  const params = new URLSearchParams();
  const rq = route.query;
  if (rq.q) {
    params.set('q', String(rq.q));
  }
  if (rq.category) {
    params.set('category', String(rq.category));
  }
  if (rq.lat) {
    params.set('lat', String(rq.lat));
  }
  if (rq.lng) {
    params.set('lng', String(rq.lng));
  }
  const qs = params.toString();
  if (qs === '') {
    return '/api/search/events';
  }
  return `/api/search/events?${qs}`;
}

async function loadEvents () {
  const path = buildSearchEventsPath();
  const data = await fetchApi(path);
  events.value = data.events || [];
}

function escapeInfoHtml (raw) {
  if (typeof raw !== 'string') {
    return '';
  }
  let out = '';
  for (let i = 0; i < raw.length; i++) {
    const c = raw.charAt(i);
    if (c === '&') {
      out += '&amp;';
      continue;
    }
    if (c === '<') {
      out += '&lt;';
      continue;
    }
    if (c === '>') {
      out += '&gt;';
      continue;
    }
    if (c === '"') {
      out += '&quot;';
      continue;
    }
    out += c;
  }
  return out;
}

function formatPrice (ev) {
  const p = ev.price;
  if (p === null || p === undefined || p === '') {
    return '—';
  }
  const n = Number(p);
  if (Number.isNaN(n)) {
    return '—';
  }
  return `€${n.toFixed(2)}`;
}

/** Data i hora com a la fitxa d’esdeveniment (inici). */
function formatEventWhen (iso) {
  if (!iso) {
    return '—';
  }
  try {
    const d = new Date(iso);
    const datePart = d.toLocaleDateString('ca-ES', { dateStyle: 'medium' });
    const timePart = d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
    return `${datePart} · ${timePart}`;
  } catch {
    return '—';
  }
}

/** Mateix criteri que EventCardTr3 (kicker: categoria • lloc). */
function buildKickerEsc (ev) {
  let cat = 'ESDEVENIMENT';
  if (ev.category && String(ev.category).trim() !== '') {
    cat = String(ev.category).trim().toUpperCase();
  }
  let place = '—';
  if (ev.venue) {
    const city = ev.venue.city;
    if (city && String(city).trim() !== '') {
      place = String(city).trim().toUpperCase();
    } else if (ev.venue.name && String(ev.venue.name).trim() !== '') {
      place = String(ev.venue.name).trim().toUpperCase();
    }
  }
  const raw = `${cat} • ${place}`;
  return escapeInfoHtml(raw);
}

/**
 * HTML del InfoWindow: com EventCardTr3 (kicker+preu, títol, data); text a l’esquerra; només el CTA centrat.
 */
function buildInfoWindowHtml (ev) {
  const nameEsc = escapeInfoHtml(ev.name);
  const kickerEsc = buildKickerEsc(ev);
  const priceEsc = escapeInfoHtml(formatPrice(ev));
  const whenEsc = escapeInfoHtml(formatEventWhen(ev.starts_at));
  const imgUrl = imageSrc(ev);
  const closeBtn =
    `<button type="button" class="map-tr3-iw__close" title="Tancar" aria-label="Tancar">` +
    `<span class="material-symbols-outlined map-tr3-iw__close-ico" aria-hidden="true">close</span>` +
    `</button>`;
  let mediaInner = '';
  if (imgUrl) {
    const srcEsc = escapeInfoHtml(imgUrl);
    const altEsc = escapeInfoHtml(imageAlt(ev));
    mediaInner =
      `${closeBtn}` +
      `<img class="map-tr3-iw__img" src="${srcEsc}" alt="${altEsc}" loading="lazy" decoding="async" width="176" height="120"/>`;
  } else {
    mediaInner = `${closeBtn}<div class="map-tr3-iw__ph" aria-hidden="true">Sense imatge</div>`;
  }
  return (
    `<div class="map-tr3-iw">` +
    `<div class="map-tr3-iw__media-wrap">` +
    mediaInner +
    `</div>` +
    `<div class="map-tr3-iw__body">` +
    `<div class="map-tr3-iw__row-kicker">` +
    `<span class="map-tr3-iw__kicker">${kickerEsc}</span>` +
    `<span class="map-tr3-iw__price">${priceEsc}</span>` +
    `</div>` +
    `<h3 class="map-tr3-iw__title">${nameEsc}</h3>` +
    `<p class="map-tr3-iw__when">${whenEsc}</p>` +
    `<div class="map-tr3-iw__action-wrap">` +
    `<a class="map-tr3-iw__a" href="/events/${ev.id}?from=search">Veure detall</a>` +
    `</div>` +
    `</div></div>`
  );
}

function bindInfoWindowCloseOnce () {
  if (!infoWindow || !window.google || !window.google.maps) {
    return;
  }
  window.google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
    const btn = document.querySelector('.map-tr3-iw__close');
    if (!btn) {
      return;
    }
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      infoWindow.close();
    });
  });
}

function openInfoForEvent (ev, pos) {
  infoWindow.setContent(buildInfoWindowHtml(ev));
  infoWindow.setPosition(pos);
  infoWindow.open(map);
  bindInfoWindowCloseOnce();
}

function clearMarkers () {
  for (let i = 0; i < markers.length; i++) {
    markers[i].setMap(null);
  }
  markers.length = 0;
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
  if (infoWindow) {
    infoWindow.close();
  }

  const g = window.google;
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
    const url = buildTr3EventMarkerDataUrl(ev.name);
    const marker = new g.maps.Marker({
      position: pos,
      map,
      title: ev.name,
      icon: {
        url,
        scaledSize: new g.maps.Size(112, 52),
        anchor: new g.maps.Point(56, 50),
      },
    });
    marker.addListener('click', () => {
      openInfoForEvent(ev, pos);
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

  const opts = buildTr3GoogleMapOptions(center, zoom, { variant: 'searchMonochrome' });
  map = new window.google.maps.Map(mapEl.value, opts);

  infoWindow = new window.google.maps.InfoWindow();

  await renderMarkers();
  mapReady.value = true;
}

watch(
  () => route.query,
  async () => {
    loading.value = true;
    mapError.value = '';
    try {
      await loadEvents();
      if (map) {
        await renderMarkers();
        await nextTick();
        if (window.google && window.google.maps) {
          window.google.maps.event.trigger(map, 'resize');
        }
      }
    } catch (e) {
      mapError.value = e?.message || 'Error en recarregar esdeveniments.';
      console.error(e);
    } finally {
      loading.value = false;
    }
  },
  { deep: true },
);

function lockBodyScroll () {
  if (typeof document === 'undefined') {
    return;
  }
  document.documentElement.classList.add('tr3-map-page-scroll-lock');
}

function unlockBodyScroll () {
  if (typeof document === 'undefined') {
    return;
  }
  document.documentElement.classList.remove('tr3-map-page-scroll-lock');
}

onMounted(async () => {
  lockBodyScroll();
  loading.value = true;
  mapError.value = '';
  try {
    await loadEvents();
    await initMap();
    await nextTick();
    if (map && window.google && window.google.maps) {
      window.google.maps.event.trigger(map, 'resize');
    }
  } catch (e) {
    mapError.value = e?.message || 'No s\'ha pogut inicialitzar el mapa (revisa la clau Maps).';
    console.error(e);
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  unlockBodyScroll();
  clearMarkers();
  if (infoWindow) {
    infoWindow.close();
  }
  map = null;
  mapReady.value = false;
});
</script>

<style scoped>
.map-tr3-page {
  box-sizing: border-box;
  width: 100%;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.map-tr3 {
  position: relative;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  background: #0e0e0e;
  overflow: hidden;
}

.map-tr3__float--top {
  position: absolute;
  top: 0.75rem;
  left: 0;
  z-index: 40;
  padding: 0 0.75rem;
  pointer-events: none;
}

.map-tr3__back {
  pointer-events: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(42, 42, 42, 0.9);
  border: 1px solid rgba(74, 71, 51, 0.35);
  color: #f7e628;
  text-decoration: none;
  transition: opacity 0.2s ease;
}

.map-tr3__back:hover {
  opacity: 0.88;
}

.map-tr3__map-block {
  position: relative;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

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

.map-tr3__map-stack {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.map-tr3__filter-layer {
  width: 100%;
  height: 100%;
  filter: grayscale(0.38) contrast(1.14) brightness(0.86);
}

.map-tr3__gmaps {
  width: 100%;
  height: 100%;
  min-height: 0;
}

.map-tr3__vignette {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    rgba(19, 19, 19, 0.88) 0%,
    rgba(19, 19, 19, 0) 18%,
    rgba(19, 19, 19, 0) 78%,
    rgba(19, 19, 19, 0.92) 100%
  );
}
</style>

<style>
/* Grocs del mapa: sempre #f7e628 (mateix que botons TR3 / crear compte). */
/* Popup del mapa: alineat amb EventCardTr3 (inici) — cos a l’esquerra, CTA centrat */
.map-tr3-iw {
  box-sizing: border-box;
  width: 100%;
  max-width: 188px;
  padding: 0;
  margin: 0;
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.1);
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
  background: #1c1b1b;
}

.map-tr3-iw__media-wrap {
  position: relative;
  width: 100%;
  height: 6.5rem;
  overflow: hidden;
  background: #222;
  border-radius: 1rem 1rem 0 0;
}

.map-tr3-iw__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  margin: 0;
  padding: 0;
}

.map-tr3-iw__ph {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  color: #666;
  background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
}

.map-tr3-iw__close {
  position: absolute;
  top: 0.35rem;
  right: 0.35rem;
  left: auto;
  z-index: 3;
  width: 1.35rem;
  height: 1.35rem;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: #000000;
  border: 1.5px solid #f7e628;
  color: #f7e628;
  cursor: pointer;
  box-sizing: border-box;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    color 0.18s ease;
}

.map-tr3-iw__close:hover,
.map-tr3-iw__close:focus-visible {
  background: #f7e628;
  border-color: #131313;
  color: #131313;
  outline: none;
}

.map-tr3-iw__close-ico {
  font-size: 0.72rem;
  line-height: 1;
  color: currentColor;
  font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 20;
}

.map-tr3-iw__body {
  padding: 0.55rem 0.65rem 0.65rem;
  text-align: left;
}

.map-tr3-iw__row-kicker {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
}

.map-tr3-iw__kicker {
  flex: 1;
  min-width: 0;
  font-size: 0.55rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #f7e628;
  line-height: 1.2;
}

.map-tr3-iw__row-kicker .map-tr3-iw__price {
  flex-shrink: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  font-size: 0.78rem;
  color: #f7e628;
}

.map-tr3-iw__title {
  margin: 0 0 0.35rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.82rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.02em;
  color: #fff;
  word-break: break-word;
}

.map-tr3-iw__when {
  margin: 0 0 0.15rem;
  font-size: 0.65rem;
  font-weight: 500;
  color: #ccc7ac;
  line-height: 1.35;
}

.map-tr3-iw__action-wrap {
  margin-top: 0.45rem;
  text-align: center;
}

.map-tr3-iw__a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-height: 1.65rem;
  padding: 0.28rem 0.65rem;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.58rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-decoration: none;
  text-align: center;
  color: #000;
  background: #f7e628;
  border: none;
  transition: opacity 0.2s ease;
}

.map-tr3-iw__a:hover {
  opacity: 0.92;
}

/* Barra de tancament nativa de Google (no ha de restar alçària a la foto) */
.gm-style .gm-style-iw-chr {
  display: none !important;
  height: 0 !important;
  min-height: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  overflow: hidden !important;
}

.gm-style .gm-style-iw-tc {
  padding-top: 0 !important;
}

.gm-style .gm-style-iw-c {
  padding: 0 !important;
  border-radius: 1rem !important;
  background: #1c1b1b !important;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.55) !important;
}

.gm-style .gm-style-iw-d {
  overflow: hidden !important;
  max-height: none !important;
}

.gm-style .gm-style-iw-tc::after {
  display: none !important;
}
</style>
