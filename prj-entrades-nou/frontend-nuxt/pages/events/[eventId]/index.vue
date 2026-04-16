<template>
  <main class="event-detail user-page">
    <header class="event-detail__toolbar">
      <button
        type="button"
        class="event-detail__back"
        aria-label="Tornar enrere"
        @click="goBack"
      >
        <span class="material-symbols-rounded event-detail__back-ico" aria-hidden="true">arrow_back</span>
      </button>
    </header>

    <div v-if="pending" class="event-detail__state">Carregant…</div>
    <div v-else-if="error" class="event-detail__state event-detail__state--err">{{ error }}</div>
    <template v-else-if="event">
      <div class="event-hero">
        <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="event-image" />
        <div v-else class="event-image-placeholder">Sense imatge</div>
      </div>

      <div class="event-content">
        <h1 class="event-title">{{ event.name }}</h1>

        <div class="event-quick-actions" role="toolbar" aria-label="Accions ràpides">
          <button
            type="button"
            class="event-qa"
            @click="openShareModal"
          >
            <span class="material-symbols-rounded event-qa__ico" aria-hidden="true">share</span>
            <span class="event-qa__lab">Compartir</span>
          </button>
          <button
            type="button"
            class="event-qa"
            @click="copyEventLink"
          >
            <span class="material-symbols-rounded event-qa__ico" aria-hidden="true">content_copy</span>
            <span class="event-qa__lab">{{ copyActionLabel }}</span>
          </button>
          <button
            type="button"
            class="event-qa"
            :class="{ 'event-qa--on': isSaved }"
            :aria-pressed="isSaved"
            :aria-label="isSaved ? 'Treure dels preferits' : 'Afegir a preferits'"
            @click="toggleSave"
          >
            <span
              class="material-symbols-rounded event-qa__ico event-qa__ico--fav"
              :class="{ 'event-qa__ico--fill': isSaved }"
              aria-hidden="true"
            >favorite</span>
            <span class="event-qa__lab">{{ isSaved ? 'Guardat' : 'Guardar' }}</span>
          </button>
        </div>

        <div class="event-meta">
          <p class="event-date">{{ formatDate(event.starts_at) }}</p>
          <p v-if="event.venue" class="event-venue">
            {{ event.venue.name }}<br />
            <span v-if="event.venue.address">{{ event.venue.address }}, </span>{{ event.venue.city }}
          </p>
        </div>

        <section v-if="eventDescriptionText !== ''" class="event-block event-block--desc">
          <h2 class="event-section-title">
            Descripció
          </h2>
          <p class="event-description-body">
            {{ eventDescriptionText }}
          </p>
        </section>

        <section v-if="hasMoreDetailsBlock" class="event-block event-block--more">
          <h2 class="event-section-title">
            Més detalls
          </h2>
          <dl class="event-dl">
            <template v-if="detailCategory !== ''">
              <dt class="event-dl__dt">
                Categoria
              </dt>
              <dd class="event-dl__dd">
                {{ detailCategory }}
              </dd>
            </template>
            <template v-if="detailTmCategory !== ''">
              <dt class="event-dl__dt">
                Tipus
              </dt>
              <dd class="event-dl__dd">
                {{ detailTmCategory }}
              </dd>
            </template>
          </dl>
        </section>

        <div
          class="event-map"
          v-if="event.venue && venueHasMapCoords(event.venue)"
        >
          <p v-if="mapError" class="map-err">{{ mapError }}</p>
          <div class="event-map-stack">
            <div class="event-map-filter">
              <div ref="miniMapEl" class="map-canvas"></div>
            </div>
            <div class="event-map-vignette" aria-hidden="true" />
          </div>
          <button type="button" class="map-expand-btn" @click="showMapModal = true">
            Veure mapa gran
          </button>
        </div>

        <div
          v-if="showMapModal"
          class="map-modal"
          role="dialog"
          aria-modal="true"
          aria-label="Mapa del local"
          @click.self="showMapModal = false"
        >
          <div class="map-modal-content" @click.stop>
            <!-- Només mapa estil TR3 (mateix que /search/map); sense imatge de l’esdeveniment -->
            <div class="map-modal-stack map-modal-stack--expanded">
              <button
                type="button"
                class="map-modal-close"
                aria-label="Tancar"
                @click="showMapModal = false"
              >
                <span class="material-symbols-outlined map-modal-close-ico" aria-hidden="true">close</span>
              </button>
              <div class="event-map-filter">
                <div ref="modalMapEl" class="map-modal-canvas"></div>
              </div>
              <div class="event-map-vignette" aria-hidden="true" />
            </div>
            <div class="map-modal-info">
              <p v-if="event.venue?.address" class="map-address">{{ event.venue.address }}</p>
              <p v-if="event.venue?.city" class="map-city">{{ event.venue.city }}</p>
              <a
                v-if="event.venue && venueHasMapCoords(event.venue)"
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
        <NuxtLink :to="seatsLinkTo" class="footer-btn">Comprar entrades</NuxtLink>
      </footer>
    </template>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';
import { buildTr3EventMarkerDataUrl } from '~/utils/tr3MapMarkerIcon';
import { buildTr3GoogleMapOptions } from '~/utils/tr3MapOptions';
import { useSavedEventsStore } from '~/stores/savedEvents';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';
definePageMeta({
  layout: 'default',
});

const PENDING_SAVE_KEY = 'pending_save_event_id';

const route = useRoute();
const router = useRouter();
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

const eventDescriptionText = computed(() => {
  const ev = event.value;
  if (!ev) {
    return '';
  }
  const d = ev.description;
  if (d === undefined || d === null) {
    return '';
  }
  const s = String(d).trim();
  return s;
});

const detailCategory = computed(() => {
  const ev = event.value;
  if (!ev || ev.category === undefined || ev.category === null) {
    return '';
  }
  const s = String(ev.category).trim();
  return s;
});

const detailTmCategory = computed(() => {
  const ev = event.value;
  if (!ev || ev.tm_category === undefined || ev.tm_category === null) {
    return '';
  }
  const s = String(ev.tm_category).trim();
  return s;
});

const hasMoreDetailsBlock = computed(() => {
  if (detailCategory.value !== '') {
    return true;
  }
  if (detailTmCategory.value !== '') {
    return true;
  }
  return false;
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
/** Per tornar a calcular el tile canvas quan el contenidor passa de 0×0 a mida real (SSR / layout). */
let miniMapResizeObserver = null;

const friendQuery = ref('');
const shareFriends = ref([]);
const shareFriendsLoading = ref(false);
const shareError = ref('');
const shareOk = ref('');
let friendSearchTimer = null;

const copyFeedback = ref('');

const copyActionLabel = computed(() => {
  if (copyFeedback.value !== '') {
    return copyFeedback.value;
  }
  return 'Copiar enllaç';
});

function venueHasMapCoords (venue) {
  if (!venue) {
    return false;
  }
  const lat = venue.map_lat;
  const lng = venue.map_lng;
  if (lat === undefined || lat === null || lng === undefined || lng === null) {
    return false;
  }
  const ls = String(lat).trim();
  const gs = String(lng).trim();
  if (ls === '' || gs === '') {
    return false;
  }
  const ln = Number(ls);
  const gn = Number(gs);
  if (Number.isNaN(ln) || Number.isNaN(gn)) {
    return false;
  }
  return true;
}

const googleMapsUrl = computed(() => {
  if (!event.value?.venue || !venueHasMapCoords(event.value.venue)) {
    return '';
  }
  const { map_lat, map_lng, name } = event.value.venue;
  return `https://www.google.com/maps/search/?api=1&query=${map_lat},${map_lng}&query_place_id=${encodeURIComponent(name || '')}`;
});

/** Enllaç a seients conservant ?from= per al footer. */
const seatsLinkTo = computed(() => {
  const path = `/events/${eventId}/seats`;
  const fr = route.query.from;
  if (fr === undefined || fr === null) {
    return path;
  }
  const s = String(fr).trim();
  if (s === '') {
    return path;
  }
  return { path, query: { from: s } };
});

function fallbackPathFromFooter () {
  const raw = route.query.from;
  if (raw === undefined || raw === null) {
    return '/';
  }
  const slug = String(raw).toLowerCase().trim();
  if (slug === '') {
    return '/';
  }
  if (slug === 'home') {
    return '/';
  }
  if (slug === 'search') {
    return '/search';
  }
  if (slug === 'tickets') {
    return '/tickets';
  }
  if (slug === 'saved') {
    return '/saved';
  }
  if (slug === 'social') {
    return '/social';
  }
  if (slug === 'profile') {
    return '/profile';
  }
  return '/';
}

function goBack () {
  if (typeof window === 'undefined') {
    return;
  }
  if (window.history.length > 1) {
    router.back();
    return;
  }
  void navigateTo(fallbackPathFromFooter());
}

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
  await savedEventsStore.mergeFromServer();
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

function toggleSave () {
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
  void savedEventsStore.toggleFavorite(event.value.id).catch(() => {});
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

function venueMarkerIcon () {
  let n = '·';
  if (event.value && event.value.name && String(event.value.name).trim() !== '') {
    n = String(event.value.name).trim();
  }
  const url = buildTr3EventMarkerDataUrl(n);
  const g = window.google.maps;
  return {
    url,
    scaledSize: new g.Size(112, 52),
    anchor: new g.Point(56, 50),
  };
}

function detachMiniMapResizeObserver () {
  if (miniMapResizeObserver !== null) {
    miniMapResizeObserver.disconnect();
    miniMapResizeObserver = null;
  }
}

function attachMiniMapResizeObserver () {
  detachMiniMapResizeObserver();
  if (typeof ResizeObserver === 'undefined') {
    return;
  }
  if (!miniMapEl.value || !miniMap) {
    return;
  }
  miniMapResizeObserver = new ResizeObserver(() => {
    if (!miniMap) {
      return;
    }
    window.google.maps.event.trigger(miniMap, 'resize');
    const p = venuePosition();
    if (p) {
      miniMap.setCenter(p);
    }
  });
  miniMapResizeObserver.observe(miniMapEl.value);
}

function scheduleMiniMapResize () {
  if (!miniMap) {
    return;
  }
  const p = venuePosition();
  if (!p) {
    return;
  }
  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      if (!miniMap) {
        return;
      }
      window.google.maps.event.trigger(miniMap, 'resize');
      miniMap.setCenter(p);
    });
  });
  setTimeout(() => {
    if (!miniMap) {
      return;
    }
    window.google.maps.event.trigger(miniMap, 'resize');
    miniMap.setCenter(p);
  }, 280);
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
        icon: venueMarkerIcon(),
      });
    }
    scheduleMiniMapResize();
    attachMiniMapResizeObserver();
    return;
  }

  while (miniMapEl.value.firstChild) {
    miniMapEl.value.removeChild(miniMapEl.value.firstChild);
  }

  const miniOpts = buildTr3GoogleMapOptions(pos, 15, { variant: 'searchMonochrome' });
  miniOpts.disableDefaultUI = true;
  miniOpts.zoomControl = true;
  miniMap = new window.google.maps.Map(miniMapEl.value, miniOpts);

  miniMarker = new window.google.maps.Marker({
    position: pos,
    map: miniMap,
    icon: venueMarkerIcon(),
  });

  window.google.maps.event.addListenerOnce(miniMap, 'idle', () => {
    scheduleMiniMapResize();
    attachMiniMapResizeObserver();
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
    const modalOpts = buildTr3GoogleMapOptions(pos, 16, { variant: 'searchMonochrome' });
    modalOpts.disableDefaultUI = true;
    modalOpts.zoomControl = true;
    modalMap = new window.google.maps.Map(modalMapEl.value, modalOpts);
    modalMarker = new window.google.maps.Marker({
      position: pos,
      map: modalMap,
      icon: venueMarkerIcon(),
    });
  } else {
    modalMap.setCenter(pos);
    if (modalMarker) {
      modalMarker.setPosition(pos);
    } else {
      modalMarker = new window.google.maps.Marker({
        position: pos,
        map: modalMap,
        icon: venueMarkerIcon(),
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
  () => [
    pending.value,
    event.value && event.value.venue ? event.value.venue.map_lat : null,
    event.value && event.value.venue ? event.value.venue.map_lng : null,
  ],
  () => {
    if (pending.value) {
      return;
    }
    if (!event.value || !event.value.venue || !venueHasMapCoords(event.value.venue)) {
      return;
    }
    void nextTick(() => {
      window.requestAnimationFrame(() => {
        void initMaps();
      });
    });
  },
  { flush: 'post' },
);

watch(
  () => (Array.isArray(route.params.eventId) ? route.params.eventId[0] : route.params.eventId),
  async (newId, oldId) => {
    if (!newId || String(newId) === String(oldId)) {
      return;
    }
    detachMiniMapResizeObserver();
    miniMap = null;
    modalMap = null;
    miniMarker = null;
    modalMarker = null;
    showMapModal.value = false;
    await fetchEvent();
    if (event.value) {
      await applyPendingSaveIfNeeded();
      await syncSavedState();
    }
  },
);

onMounted(async () => {
  await fetchEvent();
  if (event.value) {
    await applyPendingSaveIfNeeded();
    await syncSavedState();
  }
});

onUnmounted(() => {
  detachMiniMapResizeObserver();
});
</script>

<style scoped>
.event-detail {
  padding-bottom: 5.75rem;
  padding-top: 0;
}

@media (max-width: 899px) {
  .event-detail {
    padding-bottom: calc(var(--footer-stack, 5rem) + 5.25rem);
  }
}

.event-detail__toolbar {
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  margin: 0 -1rem 0.75rem;
  padding: 0.35rem 0.25rem 0.5rem;
  background: linear-gradient(180deg, #131313f2 0%, #13131300 100%);
}

@media (min-width: 900px) {
  .event-detail__toolbar {
    margin: 0 -2rem 1rem;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
  }
}

.event-detail__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 2px solid rgba(247, 230, 40, 0.55);
  border-radius: 9999px;
  background: rgba(19, 19, 19, 0.92);
  color: var(--accent);
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    color 0.18s ease;
  -webkit-tap-highlight-color: transparent;
}

.event-detail__back:hover,
.event-detail__back:focus-visible {
  background: var(--accent);
  border-color: #131313;
  color: #131313;
  outline: none;
}

.event-detail__back-ico {
  font-size: 1.45rem;
  line-height: 1;
  font-variation-settings:
    'FILL' 0,
    'wght' 500,
    'GRAD' 0,
    'opsz' 24;
}

.event-detail__state {
  padding: 2rem 0;
  text-align: center;
  font-size: 0.95rem;
  color: #ccc7ac;
}

.event-detail__state--err {
  color: #ff8a80;
}

.event-hero {
  width: 100%;
  max-width: 42rem;
  margin-left: auto;
  margin-right: auto;
  aspect-ratio: 16 / 9;
  background: #222;
  border-radius: 1rem;
  overflow: hidden;
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
  font-size: 0.9rem;
}
/* Els marges horitzontals venen de `.user-page` (app.css); sense padding extra aquí */
.event-content {
  padding: 0.75rem 0 0;
  max-width: 42rem;
  margin-left: auto;
  margin-right: auto;
}

.event-title {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: clamp(1.85rem, 6vw, 2.75rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--fg);
  margin: 0 0 1.1rem;
  line-height: 1.08;
}

/* Sense caixes: tres accions alineades al centre, icona a dalt i text a sota */
.event-quick-actions {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  justify-content: center;
  align-items: flex-start;
  gap: clamp(0.75rem, 4vw, 1.75rem);
  margin: 0 0 1.35rem;
}

.event-qa {
  flex: 0 1 auto;
  min-width: 4.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 0.4rem;
  padding: 0.35rem 0.5rem;
  border: none;
  border-radius: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.92);
  cursor: pointer;
  font-family: Epilogue, system-ui, sans-serif;
  transition: color 0.2s ease, opacity 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}

.event-qa:hover,
.event-qa:focus-visible {
  color: #fff;
  opacity: 0.95;
  outline: none;
}

.event-qa--on {
  color: #f7e628;
}

.event-qa__ico {
  font-size: 1.55rem;
  line-height: 1;
  font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;
}

.event-qa__ico--fill {
  font-variation-settings:
    'FILL' 1,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;
}

.event-qa__lab {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  text-align: center;
  line-height: 1.2;
  word-break: break-word;
}

.event-block {
  margin-bottom: 1.5rem;
  padding: 1rem 1rem 1.1rem;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.2);
  background: rgba(22, 22, 22, 0.65);
}

.event-section-title {
  margin: 0 0 0.65rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(247, 230, 40, 0.88);
}

/* «Més detalls»: títol de secció més llegible */
.event-block--more > .event-section-title {
  font-size: clamp(0.95rem, 2.8vw, 1.2rem);
  letter-spacing: 0.1em;
  margin-bottom: 0.85rem;
}

.event-description-body {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.55;
  color: #ccc7ac;
  white-space: pre-wrap;
  word-break: break-word;
}

.event-dl {
  margin: 0;
  padding: 0;
}

.event-dl__dt {
  margin: 0 0 0.2rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.45);
}

.event-dl__dd {
  margin: 0 0 0.85rem;
  font-size: 0.9rem;
  color: var(--fg);
  line-height: 1.4;
}

.event-dl__dd:last-child {
  margin-bottom: 0;
}

.event-meta {
  margin-bottom: 1.5rem;
}
.event-date {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--fg);
  margin: 0 0 0.5rem;
}
.event-venue {
  font-size: 0.9rem;
  color: #ccc7ac;
  margin: 0;
  line-height: 1.45;
}
.event-map {
  margin: 1rem 0;
  border-radius: 1rem;
  overflow: hidden;
  border: 1px solid rgba(74, 71, 51, 0.15);
}
.event-map-stack,
.map-modal-stack {
  position: relative;
}
.event-map-filter {
  filter: grayscale(0.38) contrast(1.14) brightness(0.86);
}
.event-map-vignette {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  /* Mateix vinietat que /search/map (map-tr3__vignette) */
  background: linear-gradient(
    to bottom,
    rgba(19, 19, 19, 0.88) 0%,
    rgba(19, 19, 19, 0) 18%,
    rgba(19, 19, 19, 0) 78%,
    rgba(19, 19, 19, 0.92) 100%
  );
}
.map-canvas {
  width: 100%;
  height: 200px;
  background: #0e0e0e;
}
.map-expand-btn {
  width: 100%;
  padding: 0.85rem;
  background: #1c1b1b;
  border: none;
  border-top: 1px solid rgba(74, 71, 51, 0.2);
  color: #ccc7ac;
  cursor: pointer;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  transition: color 0.2s ease, background 0.2s ease;
}
.map-expand-btn:hover {
  color: var(--accent);
  background: #222;
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
  border-radius: 14px;
  width: 100%;
  max-width: 500px;
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
}

.map-modal-stack--expanded {
  position: relative;
  flex: 1;
  min-height: 0;
}

.map-modal-close {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  z-index: 10;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: #000000;
  border: 2px solid #f7e628;
  color: #f7e628;
  cursor: pointer;
  box-sizing: border-box;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    color 0.18s ease;
}

.map-modal-close:hover,
.map-modal-close:focus-visible {
  background: #f7e628;
  border-color: #131313;
  color: #131313;
  outline: none;
}

.map-modal-close-ico {
  font-size: 1.35rem;
  line-height: 1;
  color: currentColor;
  font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
}

.map-modal-canvas {
  width: 100%;
  /* ~el doble de l’alçària anterior del mapa al modal (220px → zona gran sense hero) */
  min-height: clamp(22rem, 56vh, 32rem);
  height: min(56vh, 32rem);
  background: #0e0e0e;
}
.map-modal-info {
  padding: 1rem;
}
.map-address, .map-city {
  margin: 0;
  color: var(--fg);
}
.map-city {
  color: #ccc7ac;
  font-size: 0.9rem;
}
.map-gmaps-link {
  display: inline-block;
  margin-top: 0.75rem;
  color: #f7e628;
  text-decoration: none;
  font-size: 0.9rem;
}
.share-modal {
  position: fixed;
  inset: 0;
  background: rgba(19, 19, 19, 0.88);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 1rem;
}

/* Panell TR3: alineat amb login (AuthEmailPage) — #131313, vores fines, groc #f7e628 */
.share-modal__box {
  background: #131313;
  border: 1px solid rgba(74, 71, 51, 0.28);
  border-radius: 1rem;
  width: 100%;
  max-width: 26rem;
  padding: 1.5rem 1.35rem 1.35rem;
  position: relative;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
}

.share-modal__close {
  position: absolute;
  top: 0.65rem;
  right: 0.65rem;
  width: 2.35rem;
  height: 2.35rem;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 2px solid #f7e628;
  border-radius: 9999px;
  background: #000000;
  color: #f7e628;
  font-size: 1.35rem;
  line-height: 1;
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    color 0.18s ease;
}

.share-modal__close:hover,
.share-modal__close:focus-visible {
  background: #f7e628;
  border-color: #131313;
  color: #131313;
  outline: none;
}

.share-modal__title {
  margin: 0 2.5rem 1.15rem 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #f7e628;
}

.share-modal__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.85rem;
  min-height: 3.5rem;
  padding: 0 1rem;
  box-sizing: border-box;
  background: #0e0e0e;
  border: 1px solid rgba(74, 71, 51, 0.2);
  border-radius: 1rem;
  transition: border-color 0.2s ease;
}

.share-modal__search:focus-within {
  border-color: #f7e628;
}

.share-modal__icon {
  color: rgba(204, 199, 172, 0.65);
  font-size: 1.2rem;
  line-height: 1;
  flex-shrink: 0;
}

.share-modal__input {
  flex: 1;
  min-width: 0;
  height: 3.25rem;
  border: none;
  background: transparent;
  color: #e5e2e1;
  font-family: Inter, system-ui, sans-serif;
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  outline: none;
}

.share-modal__input::placeholder {
  color: rgba(204, 199, 172, 0.4);
}

.share-modal__list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 220px;
  overflow-y: auto;
  border-radius: 0.65rem;
}

.share-modal__friend {
  width: 100%;
  text-align: left;
  padding: 0.7rem 0.65rem;
  border: none;
  border-bottom: 1px solid rgba(74, 71, 51, 0.25);
  background: transparent;
  color: #e5e2e1;
  cursor: pointer;
  font-family: Inter, system-ui, sans-serif;
  font-size: 0.92rem;
}

.share-modal__friend:hover {
  background: rgba(255, 255, 255, 0.04);
}

.share-modal__fname {
  color: #ccc7ac;
  font-size: 0.85rem;
}

.share-modal__muted {
  color: #ccc7ac;
  font-size: 0.88rem;
  margin: 0.55rem 0 0;
}

.share-modal__err {
  color: #ffb4ab;
  font-size: 0.88rem;
  margin: 0.55rem 0 0;
}

.share-modal__ok {
  color: #7bed9f;
  font-size: 0.88rem;
  margin: 0.55rem 0 0;
}
.event-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 30;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem calc(0.85rem + env(safe-area-inset-bottom, 0px));
  background: rgba(19, 19, 19, 0.96);
  border-top: 1px solid rgba(247, 230, 40, 0.18);
  backdrop-filter: blur(10px);
}
.footer-price {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--fg);
}
.footer-btn {
  padding: 0.75rem 1.35rem;
  background: var(--accent);
  color: var(--accent-on);
  text-decoration: none;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  transition: opacity 0.2s ease;
}
.footer-btn:hover {
  opacity: 0.92;
}

@media (max-width: 899px) {
  .event-footer {
    bottom: var(--footer-stack, 5rem);
  }
}
</style>
