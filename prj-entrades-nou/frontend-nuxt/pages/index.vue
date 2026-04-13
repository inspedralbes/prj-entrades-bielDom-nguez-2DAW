<template>
  <div class="home">
    <div class="home__proximity">
      <label class="proximity-toggle">
        <input 
          type="checkbox" 
          v-model="proximityEnabled" 
          @change="onProximityToggle"
        />
        <span>Filtrar per proximitat</span>
      </label>
      <select 
        v-if="proximityEnabled" 
        v-model="radiusKm" 
        @change="onRadiusChange"
        class="radius-select"
      >
        <option :value="10">10 km</option>
        <option :value="25">25 km</option>
        <option :value="50">50 km</option>
        <option :value="100">100 km</option>
        <option :value="200">200 km</option>
      </select>
    </div>

    <p v-if="error" class="home__err home__h2--pad">{{ error }}</p>
    <p v-else-if="loading" class="home__muted home__h2--pad">Carregant…</p>

    <template v-else>
      <section class="home__section" aria-labelledby="h-featured">
        <h2 id="h-featured" class="home__h2 home__h2--pad">{{ auth.token ? 'Destacats' : 'Esdeveniments' }}</h2>
        <ul v-if="featured.length" class="home__list home__list--hero">
          <li v-for="ev in featured" :key="ev.id" class="home__card">
            <NuxtLink :to="`/events/${ev.id}`" class="home__link">
              <div
                class="home__media"
                :class="{ 'home__media--empty': !imageSrc(ev) }"
              >
                <img
                  v-if="imageSrc(ev)"
                  class="home__img"
                  :src="imageSrc(ev)"
                  :alt="imageAlt(ev)"
                  loading="lazy"
                  decoding="async"
                  width="1200"
                  height="675"
                />
              </div>
              <div class="home__body home__body--pad">
                <span class="home__name">{{ ev.name }}</span>
                <span class="home__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</span>
              </div>
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="home__muted home__h2--pad">Sense esdeveniments.</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, onBeforeRouteLeave } from 'vue-router';
import { useAuthStore } from '~/stores/auth';
import { useEventImage } from '~/composables/useEventImage';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const route = useRoute();
const auth = useAuthStore();
const { imageSrc, imageAlt } = useEventImage();

const loading = ref(true);
const error = ref('');
const featured = ref([]);

const proximityEnabled = ref(false);
const radiusKm = ref(50);
const categories = ref(['party', 'dj', 'concert']);

function loadProximityState() {
  const saved = localStorage.getItem('home_proximity');
  if (saved) {
    try {
      const state = JSON.parse(saved);
      proximityEnabled.value = state.enabled || false;
      radiusKm.value = state.radius || 50;
    } catch {}
  }
}

function saveProximityState() {
  localStorage.setItem('home_proximity', JSON.stringify({
    enabled: proximityEnabled.value,
    radius: radiusKm.value
  }));
}

function clearProximityState() {
  proximityEnabled.value = false;
  localStorage.removeItem('home_proximity');
}

function onProximityToggle() {
  saveProximityState();
  fetchFeatured();
}

function onRadiusChange() {
  saveProximityState();
  fetchFeatured();
}

watch(() => route.path, (newPath) => {
  if (newPath !== '/') {
    clearProximityState();
  }
});

/**
 * Convidad: /api/search/events (mateix catàleg que Cerca).
 * Amb sessió: /api/feed/featured (destacats).
 * Amb «Filtrar per proximitat»: /api/events/nearby (tots, geolocalització).
 * Categories per defecte: party, dj, concert.
 */
async function fetchFeatured() {
  const base = resolvePublicApiBaseUrl(config.public.apiUrl).replace(/\/$/, '');
  let url;

  if (proximityEnabled.value && typeof navigator !== 'undefined' && navigator.geolocation) {
    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject);
      });
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      url = `${base}/api/events/nearby?lat=${lat}&lng=${lng}&radius=${radiusKm.value}`;
    } catch {
      /* sense permís de geolocalització: es fa servir cerca o destacats */
    }
  }

  if (!url) {
    url = auth.token
      ? `${base}/api/feed/featured`
      : `${base}/api/search/events`;
  }

  const f = await $fetch(url);
  featured.value = f.events || [];
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

onMounted(async () => {
  loadProximityState();
  auth.init();
  loading.value = true;
  error.value = '';
  try {
    await fetchFeatured();
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
.home__h2--pad {
  padding: 0 1rem;
}
@media (min-width: 768px) {
  .home__h2--pad {
    padding: 0;
  }
}
.home__proximity {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 0 1rem 1.5rem;
  padding: 0.75rem;
  background: #1a1a1a;
  border-radius: 8px;
}
@media (min-width: 768px) {
  .home__proximity {
    margin-left: 0;
    margin-right: 0;
  }
}
.proximity-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
}
.proximity-toggle input {
  cursor: pointer;
}
.radius-select {
  padding: 0.4rem 0.6rem;
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #f5f5f5;
  font-size: 0.85rem;
  cursor: pointer;
}
.home__h2 {
  color: #ff0055;
  font-size: 1.1rem;
  margin: 0 0 0.75rem;
}
.home__section {
  margin-bottom: 2rem;
}
/* Una fila per amplada en mòbil: imatge a ample complet */
.home__list--hero {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
@media (max-width: 767px) {
  .home__list--hero {
    width: 100vw;
    margin-left: calc(50% - 50vw);
  }
}
.home__card {
  margin: 0;
}
.home__link {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: #f5f5f5;
  background: #161616;
  border-bottom: 1px solid #2a2a2a;
}
@media (min-width: 768px) {
  .home__link {
    border-radius: 12px;
    border: 1px solid #2a2a2a;
    overflow: hidden;
    margin-bottom: 1rem;
  }
  .home__list--hero {
    gap: 0;
  }
}
.home__link:hover .home__name {
  color: #ff88aa;
}
.home__media {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #222;
  overflow: hidden;
}
.home__media--empty {
  background: linear-gradient(145deg, #2a2a2a 0%, #1a1a1a 100%);
  min-height: 12rem;
}
.home__media--empty::after {
  content: 'Sense imatge';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: #666;
}
.home__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  vertical-align: top;
}
.home__body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem 0 1.25rem;
}
.home__body--pad {
  padding-left: 1rem;
  padding-right: 1rem;
}
@media (min-width: 768px) {
  .home__body--pad {
    padding: 1rem 1.1rem 1.15rem;
  }
}
.home__name {
  font-weight: 600;
  font-size: 1.05rem;
  line-height: 1.3;
}
.home__meta {
  font-size: 0.85rem;
  color: #888;
}
.home__muted {
  color: #888;
}
.home__err {
  color: #ff6b6b;
}
</style>
