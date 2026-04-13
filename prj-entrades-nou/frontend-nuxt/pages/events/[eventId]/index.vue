<template>
  <main class="event-detail">
    <div v-if="pending" class="loading">Carregant…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else-if="event">
      <div class="event-hero">
        <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="event-image" />
        <div v-else class="event-image-placeholder">Sense imatge</div>
      </div>

      <div class="event-content">
        <div class="event-header">
          <h1 class="event-title">{{ event.name }}</h1>
          <button type="button" class="save-btn" :class="{ saved: isSaved }" @click="toggleSave">
            <span class="save-icon">{{ isSaved ? '♥' : '♡' }}</span>
            <span class="save-text">{{ isSaved ? 'Desar' : 'Guardar' }}</span>
          </button>
        </div>

        <div class="event-actions">
          <button type="button" class="action-btn" @click="openShareModal">Compartir</button>
          <button type="button" class="action-btn action-btn--ghost" @click="copyEventLink">
            {{ copyFeedback ? copyFeedback : 'Copiar enllaç' }}
          </button>
        </div>

        <div class="event-meta">
          <p class="event-date">{{ formatDate(event.starts_at) }}</p>
          <p v-if="event.venue" class="event-venue">
            {{ event.venue.name }}<br />
            <span v-if="event.venue.address">{{ event.venue.address }}, </span>{{ event.venue.city }}
          </p>
        </div>

        <div class="event-map" v-if="event.venue?.map_lat && event.venue?.map_lng">
          <p v-if="mapError" class="map-err">{{ mapError }}</p>
          <div ref="miniMapEl" class="map-canvas"></div>
          <button type="button" class="map-expand-btn" @click="showMapModal = true">
            Veure mapa gran
          </button>
        </div>

        <div v-if="showMapModal" class="map-modal" @click.self="showMapModal = false">
          <div class="map-modal-content">
            <button type="button" class="map-modal-close" @click="showMapModal = false">×</button>
            <div ref="modalMapEl" class="map-modal-canvas"></div>
            <div class="map-modal-info">
              <p v-if="event.venue?.address" class="map-address">{{ event.venue.address }}</p>
              <p v-if="event.venue?.city" class="map-city">{{ event.venue.city }}</p>
              <a
                v-if="event.venue?.map_lat && event.venue?.map_lng"
                :href="googleMapsUrl"
                target="_blank"
                rel="noopener"
                class="map-gmaps-link"
              >
                Obrir a Google Maps
              </a>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showShareModal" class="share-modal" @click.self="showShareModal = false">
        <div class="share-modal__box">
          <button type="button" class="share-modal__close" @click="showShareModal = false">×</button>
          <h2 class="share-modal__title">Compartir amb un amic</h2>
          <div class="share-modal__search">
            <span class="share-modal__icon" aria-hidden="true">⌕</span>
            <input
              v-model="friendQuery"
              type="search"
              class="share-modal__input"
              placeholder="Cercar amic…"
              @input="scheduleFriendSearch"
            />
          </div>
          <ul v-if="shareFriendsLoading" class="share-modal__list">
            <li class="share-modal__muted">Carregant…</li>
          </ul>
          <ul v-else class="share-modal__list">
            <li v-for="f in shareFriends" :key="f.id">
              <button type="button" class="share-modal__friend" @click="shareEventToFriend(f)">
                @{{ f.username }} <span class="share-modal__fname">{{ f.name }}</span>
              </button>
            </li>
          </ul>
          <p v-if="shareFriends.length === 0 && !shareFriendsLoading" class="share-modal__muted">Cap amic coincideix.</p>
          <p v-if="shareError" class="share-modal__err">{{ shareError }}</p>
          <p v-if="shareOk" class="share-modal__ok">{{ shareOk }}</p>
        </div>
      </div>

      <footer class="event-footer">
        <div class="footer-price">
          <span v-if="event.price" class="price-value">€{{ event.price }}</span>
          <span v-else class="price-value">—</span>
        </div>
        <NuxtLink :to="`/events/${eventId}/seats`" class="footer-btn">Get Tickets</NuxtLink>
      </footer>
    </template>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';
import { useSavedEventsStore } from '~/stores/savedEvents';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

definePageMeta({
  layout: 'default',
});

const PENDING_SAVE_KEY = 'pending_save_event_id';

const route = useRoute();
const rawEventId = route.params.eventId;
const eventId = Array.isArray(rawEventId) ? rawEventId[0] : rawEventId;
const config = useRuntimeConfig();
const authToken = useCookie('auth_token');
const { getJson, postJson } = useAuthorizedApi();
const { load: loadGoogleMaps } = useGoogleMapsLoader();
const savedEventsStore = useSavedEventsStore();

const event = ref(null);
const pending = ref(true);
const error = ref('');

const isSaved = computed(() => {
  if (!event.value) {
    return false;
  }
  return savedEventsStore.isSaved(event.value.id);
});
const showMapModal = ref(false);
const showShareModal = ref(false);
const mapError = ref('');
const miniMapEl = ref(null);
const modalMapEl = ref(null);
let miniMap = null;
let modalMap = null;
let miniMarker = null;
let modalMarker = null;

const friendQuery = ref('');
const shareFriends = ref([]);
const shareFriendsLoading = ref(false);
const shareError = ref('');
const shareOk = ref('');
let friendSearchTimer = null;

const copyFeedback = ref('');

const googleMapsUrl = computed(() => {
  if (!event.value?.venue?.map_lat || !event.value?.venue?.map_lng) {
    return '';
  }
  const { map_lat, map_lng, name } = event.value.venue;
  return `https://www.google.com/maps/search/?api=1&query=${map_lat},${map_lng}&query_place_id=${encodeURIComponent(name || '')}`;
});

function hasAuth () {
  const v = authToken.value;
  if (typeof v !== 'string') {
    return false;
  }
  return v.trim() !== '';
}

async function fetchEvent () {
  pending.value = true;
  error.value = '';
  try {
    const base = resolvePublicApiBaseUrl(config.public.apiUrl);
    const f = await $fetch(`${base}/api/events/${eventId}`, { timeout: 20000 });
    event.value = f;
  } catch (e) {
    error.value = 'No s\'ha pogut carregar l\'esdeveniment.';
    console.error(e);
  } finally {
    pending.value = false;
  }
}

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', {
      dateStyle: 'full',
      timeStyle: 'short',
    });
  } catch {
    return iso;
  }
}

async function syncSavedState () {
  if (!hasAuth()) {
    return;
  }
  if (!event.value) {
    return;
  }
  try {
    const res = await getJson('/api/saved-events');
    const list = res.events || [];
    savedEventsStore.mergeFromApiList(list);
  } catch (e) {
    console.error(e);
  }
}

async function applyPendingSaveIfNeeded () {
  if (!hasAuth()) {
    return;
  }
  if (typeof sessionStorage === 'undefined') {
    return;
  }
  const raw = sessionStorage.getItem(PENDING_SAVE_KEY);
  if (!raw) {
    return;
  }
  if (String(raw) !== String(eventId)) {
    return;
  }
  sessionStorage.removeItem(PENDING_SAVE_KEY);
  savedEventsStore.setSaved(event.value.id, true);
}

/**
 * Persistència servidor: afegir aquí POST/DELETE quan l’API estigui llesta.
 */
async function persistSavedToServer () {
  // TODO: sincronitzar amb /api/saved-events
}

async function toggleSave () {
  if (!hasAuth()) {
    if (typeof sessionStorage !== 'undefined') {
      sessionStorage.setItem(PENDING_SAVE_KEY, String(eventId));
    }
    navigateTo({ path: '/login', query: { redirect: route.fullPath } });
    return;
  }
  if (!event.value) {
    return;
  }
  savedEventsStore.toggle(event.value.id);
  void persistSavedToServer();
}

function openShareModal () {
  if (!hasAuth()) {
    navigateTo({ path: '/login', query: { redirect: route.fullPath } });
    return;
  }
  shareError.value = '';
  shareOk.value = '';
  friendQuery.value = '';
  showShareModal.value = true;
  loadShareFriends();
}

function scheduleFriendSearch () {
  if (friendSearchTimer !== null) {
    clearTimeout(friendSearchTimer);
  }
  friendSearchTimer = setTimeout(() => {
    loadShareFriends();
  }, 350);
}

async function loadShareFriends () {
  if (!showShareModal.value) {
    return;
  }
  shareFriendsLoading.value = true;
  shareError.value = '';
  try {
    const q = friendQuery.value.trim();
    let path = '/api/social/friends';
    if (q !== '') {
      path = `${path}?q=${encodeURIComponent(q)}`;
    }
    const res = await getJson(path);
    shareFriends.value = res.friends || [];
  } catch (e) {
    shareError.value = 'No s\'han pogut carregar els amics.';
    console.error(e);
  } finally {
    shareFriendsLoading.value = false;
  }
}

async function shareEventToFriend (f) {
  shareError.value = '';
  shareOk.value = '';
  try {
    await postJson('/api/social/share-event', {
      event_id: Number(event.value.id),
      to_user_id: f.id,
    });
    shareOk.value = 'Enviat.';
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:notifications-updated'));
    }
  } catch (e) {
    const msg = e?.data?.message || e?.message || 'Error en enviar.';
    shareError.value = msg;
    console.error(e);
  }
}

async function copyEventLink () {
  copyFeedback.value = '';
  if (typeof window === 'undefined') {
    return;
  }
  const url = new URL(route.fullPath, window.location.origin).href;
  try {
    await navigator.clipboard.writeText(url);
    copyFeedback.value = 'Copiat!';
  } catch (e) {
    copyFeedback.value = 'No s\'ha pogut copiar';
    console.error(e);
  }
  setTimeout(() => {
    copyFeedback.value = '';
  }, 2500);
}

function venuePosition () {
  if (!event.value?.venue) {
    return null;
  }
  const lat = Number(event.value.venue.map_lat);
  const lng = Number(event.value.venue.map_lng);
  if (Number.isNaN(lat) || Number.isNaN(lng)) {
    return null;
  }
  return { lat, lng };
}

function markerIcon () {
  return {
    path: window.google.maps.SymbolPath.CIRCLE,
    scale: 12,
    fillColor: '#FFD700',
    fillOpacity: 1,
    strokeColor: '#000',
    strokeWeight: 2,
  };
}

async function initMaps () {
  mapError.value = '';
  if (!miniMapEl.value) {
    return;
  }
  const pos = venuePosition();
  if (!pos) {
    mapError.value = 'Coordenades del local no vàlides.';
    return;
  }

  try {
    await loadGoogleMaps(config.public.googleMapsKey);
  } catch (e) {
    mapError.value = e?.message || 'No s\'ha pogut carregar el mapa (revisa NUXT_PUBLIC_GOOGLE_MAPS_KEY).';
    console.error(e);
    return;
  }

  if (miniMap) {
    miniMap.setCenter(pos);
    miniMap.setZoom(15);
    if (miniMarker) {
      miniMarker.setPosition(pos);
    } else {
      miniMarker = new window.google.maps.Marker({
        position: pos,
        map: miniMap,
        icon: markerIcon(),
      });
    }
    window.google.maps.event.trigger(miniMap, 'resize');
    return;
  }

  while (miniMapEl.value.firstChild) {
    miniMapEl.value.removeChild(miniMapEl.value.firstChild);
  }

  miniMap = new window.google.maps.Map(miniMapEl.value, {
    center: pos,
    zoom: 15,
    disableDefaultUI: true,
    zoomControl: true,
  });

  miniMarker = new window.google.maps.Marker({
    position: pos,
    map: miniMap,
    icon: markerIcon(),
  });

  window.google.maps.event.addListenerOnce(miniMap, 'idle', () => {
    window.google.maps.event.trigger(miniMap, 'resize');
  });
}

async function openModalMap () {
  if (!modalMapEl.value) {
    return;
  }
  const pos = venuePosition();
  if (!pos) {
    return;
  }

  try {
    await loadGoogleMaps(config.public.googleMapsKey);
  } catch (e) {
    mapError.value = e?.message || 'No s\'ha pogut carregar el mapa.';
    console.error(e);
    return;
  }

  if (!modalMap) {
    while (modalMapEl.value.firstChild) {
      modalMapEl.value.removeChild(modalMapEl.value.firstChild);
    }
    modalMap = new window.google.maps.Map(modalMapEl.value, {
      center: pos,
      zoom: 16,
      disableDefaultUI: true,
      zoomControl: true,
    });
    modalMarker = new window.google.maps.Marker({
      position: pos,
      map: modalMap,
      icon: markerIcon(),
    });
  } else {
    modalMap.setCenter(pos);
    if (modalMarker) {
      modalMarker.setPosition(pos);
    } else {
      modalMarker = new window.google.maps.Marker({
        position: pos,
        map: modalMap,
        icon: markerIcon(),
      });
    }
  }

  setTimeout(() => {
    if (modalMap) {
      window.google.maps.event.trigger(modalMap, 'resize');
      modalMap.setCenter(pos);
    }
  }, 200);
}

watch(showMapModal, (val) => {
  if (val) {
    setTimeout(() => {
      openModalMap();
    }, 100);
  }
});

watch(
  () => (Array.isArray(route.params.eventId) ? route.params.eventId[0] : route.params.eventId),
  async (newId, oldId) => {
    if (!newId || String(newId) === String(oldId)) {
      return;
    }
    miniMap = null;
    modalMap = null;
    miniMarker = null;
    modalMarker = null;
    showMapModal.value = false;
    await fetchEvent();
    if (event.value) {
      await applyPendingSaveIfNeeded();
      await syncSavedState();
      await nextTick();
      setTimeout(() => {
        initMaps();
      }, 150);
    }
  },
);

onMounted(async () => {
  await fetchEvent();
  if (event.value) {
    await applyPendingSaveIfNeeded();
    await syncSavedState();
    await nextTick();
    setTimeout(() => {
      initMaps();
    }, 100);
  }
});
</script>

<style scoped>
.event-detail {
  padding-bottom: 80px;
}
.loading, .error {
  padding: 2rem;
  text-align: center;
  color: #888;
}
.error {
  color: #ff6b6b;
}
.event-hero {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #222;
}
.event-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.event-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #666;
}
.event-content {
  padding: 1rem;
}
.event-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.75rem;
}
.event-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #f5f5f5;
  margin: 0;
  flex: 1;
}
.event-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.action-btn {
  padding: 0.45rem 0.85rem;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 8px;
  color: #f5f5f5;
  cursor: pointer;
  font-size: 0.85rem;
}
.action-btn--ghost {
  background: #1a1a1a;
}
.save-btn {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.75rem;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 20px;
  color: #f5f5f5;
  cursor: pointer;
  font-size: 0.9rem;
}
.save-btn.saved {
  color: #ff0055;
  border-color: #ff0055;
}
.save-icon {
  font-size: 1.1rem;
}
.event-meta {
  margin-bottom: 1.5rem;
}
.event-date {
  font-size: 1.1rem;
  color: #f5f5f5;
  margin: 0 0 0.5rem;
}
.event-venue {
  font-size: 0.9rem;
  color: #888;
  margin: 0;
  line-height: 1.4;
}
.event-map {
  margin: 1rem 0;
  border-radius: 8px;
  overflow: hidden;
}
.map-canvas {
  width: 100%;
  height: 200px;
  background: #222;
}
.map-expand-btn {
  width: 100%;
  padding: 0.75rem;
  background: #1a1a1a;
  border: none;
  color: #888;
  cursor: pointer;
  font-size: 0.9rem;
}
.map-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.map-modal-content {
  background: #1a1a1a;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  overflow: hidden;
  position: relative;
}
.map-modal-close {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 32px;
  height: 32px;
  background: rgba(0, 0, 0, 0.5);
  border: none;
  border-radius: 50%;
  color: #fff;
  font-size: 1.5rem;
  cursor: pointer;
  z-index: 1;
}
.map-modal-canvas {
  width: 100%;
  height: 300px;
  background: #222;
}
.map-modal-info {
  padding: 1rem;
}
.map-address, .map-city {
  margin: 0;
  color: #f5f5f5;
}
.map-city {
  color: #888;
  font-size: 0.9rem;
}
.map-gmaps-link {
  display: inline-block;
  margin-top: 0.75rem;
  color: #ff0055;
  text-decoration: none;
  font-size: 0.9rem;
}
.share-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 1rem;
}
.share-modal__box {
  background: #141414;
  border: 1px solid #333;
  border-radius: 12px;
  width: 100%;
  max-width: 420px;
  padding: 1.25rem;
  position: relative;
}
.share-modal__close {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: #888;
  font-size: 1.5rem;
  cursor: pointer;
}
.share-modal__title {
  margin: 0 0 1rem;
  font-size: 1.1rem;
  color: #ff0055;
}
.share-modal__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.5rem 0.65rem;
  background: #0d0d0d;
  border: 1px solid #333;
  border-radius: 8px;
}
.share-modal__icon {
  color: #888;
}
.share-modal__input {
  flex: 1;
  border: none;
  background: transparent;
  color: #fff;
  font-size: 0.95rem;
  outline: none;
}
.share-modal__list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 220px;
  overflow-y: auto;
}
.share-modal__friend {
  width: 100%;
  text-align: left;
  padding: 0.65rem 0.5rem;
  border: none;
  border-bottom: 1px solid #222;
  background: transparent;
  color: #eee;
  cursor: pointer;
  font-size: 0.9rem;
}
.share-modal__friend:hover {
  background: #1f1f1f;
}
.share-modal__fname {
  color: #888;
  font-size: 0.85rem;
}
.share-modal__muted {
  color: #666;
  font-size: 0.85rem;
  margin: 0.5rem 0;
}
.share-modal__err {
  color: #ff6b6b;
  font-size: 0.85rem;
  margin: 0.5rem 0 0;
}
.share-modal__ok {
  color: #7bed9f;
  font-size: 0.85rem;
  margin: 0.5rem 0 0;
}
.event-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #1a1a1a;
  border-top: 1px solid #2a2a2a;
}
.footer-price {
  font-size: 1.25rem;
  font-weight: 600;
  color: #f5f5f5;
}
.footer-btn {
  padding: 0.75rem 1.5rem;
  background: #ff0055;
  color: #fff;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
}
</style>
