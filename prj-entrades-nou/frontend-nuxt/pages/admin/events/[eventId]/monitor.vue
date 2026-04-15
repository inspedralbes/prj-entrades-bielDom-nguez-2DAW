<template>
  <div class="adm-mon">
    <div class="adm-mon__back-row">
      <NuxtLink prefetch to="/admin/events" class="adm-mon__back">
        <svg
          class="adm-mon__back-icon"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
        >
          <path
            d="M15 18L9 12L15 6"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span>Enrere</span>
      </NuxtLink>
    </div>
    <header class="adm-mon__hero">
      <div class="adm-mon__hero-text">
        <h1 class="adm-mon__title">
          Monitor d’esdeveniment
        </h1>
        <p class="adm-mon__lead">
          Aforament, vendes i mapa en temps real per a aquest esdeveniment.
        </p>
      </div>
    </header>

    <p v-if="loadErr" class="adm-mon__err">
      {{ loadErr }}
    </p>
    <div v-else-if="pending" class="adm-mon__muted">
      Carregant…
    </div>

    <template v-else-if="monitor">
      <h2 class="adm-mon__event-name">
        {{ monitor.name }}
      </h2>

      <div class="adm-mon__bento">
        <div class="adm-mon__stat">
          <p class="adm-mon__stat-label">
            Aforament
          </p>
          <p class="adm-mon__stat-value">
            {{ monitor.capacity }}
          </p>
        </div>
        <div class="adm-mon__stat">
          <p class="adm-mon__stat-label">
            Venuts
          </p>
          <p class="adm-mon__stat-value">
            {{ monitor.tickets_sold }}
          </p>
        </div>
        <div class="adm-mon__stat">
          <p class="adm-mon__stat-label">
            Disponibles
          </p>
          <p class="adm-mon__stat-value">
            {{ monitor.remaining }}
          </p>
        </div>
        <div class="adm-mon__stat">
          <p class="adm-mon__stat-label">
            Recaptació (EUR)
          </p>
          <p class="adm-mon__stat-value adm-mon__stat-value--accent">
            {{ monitor.revenue_eur }}
          </p>
        </div>
      </div>

      <ClientOnly>
        <div class="adm-mon__seatmap" aria-label="Mapa de seients">
          <InteractiveSeatMap :event-id="eventIdStr" :read-only="true" />
        </div>
        <template #fallback>
          <p class="adm-mon__muted">Preparant mapa…</p>
        </template>
      </ClientOnly>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import InteractiveSeatMap from '~/components/InteractiveSeatMap.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { usePrivateSeatmapSocket } from '~/composables/usePrivateSeatmapSocket';
import { useAuthStore } from '~/stores/auth';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const route = useRoute();
const { getJson } = useAuthorizedApi();
const auth = useAuthStore();
const seatmapStore = useInteractiveSeatmapStore();

const eventIdStr = computed(() => {
  const raw = route.params.eventId;
  if (Array.isArray(raw)) {
    return String(raw[0] || '');
  }
  return String(raw || '');
});

const pending = ref(true);
const loadErr = ref('');
const monitor = ref(null);

usePrivateSeatmapSocket(eventIdStr);

async function loadMonitor () {
  const id = eventIdStr.value;
  if (!id) {
    loadErr.value = 'ID invàlid.';
    pending.value = false;
    return;
  }
  loadErr.value = '';
  pending.value = true;
  try {
    const data = await getJson(`/api/admin/events/${encodeURIComponent(id)}/monitor`);
    monitor.value = data;
    const sm = data.seatmap;
    if (sm && typeof sm === 'object') {
      seatmapStore.bootstrapFromApi(sm, id);
      let uid = null;
      if (auth.user && auth.user.id !== undefined) {
        uid = auth.user.id;
      }
      seatmapStore.setCurrentUserId(uid);
    }
  } catch (e) {
    loadErr.value = 'No s’ha pogut carregar el monitor.';
    console.error(e);
  } finally {
    pending.value = false;
  }
}

onMounted(() => {
  loadMonitor();
});

watch(
  () => eventIdStr.value,
  () => {
    loadMonitor();
  },
);
</script>

<style scoped>
/* Pantalla completa dins l’àrea admin: amplada màxima i mapa amb alçada gran (ResizeObserver del D3). */
.adm-mon {
  box-sizing: border-box;
  width: 100%;
  min-width: 100%;
  max-width: none;
  margin: 0;
  padding-bottom: 1.25rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
  display: flex;
  flex-direction: column;
  /* Omple el viewport sota el padding del layout admin */
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

.adm-mon__back-row {
  margin-bottom: 1.25rem;
}

.adm-mon__back {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border: none;
  background: transparent;
  color: #f7e628;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: color 0.2s ease, opacity 0.2s ease;
}

.adm-mon__back:hover {
  opacity: 0.88;
  color: #fff563;
}

.adm-mon__back-icon {
  flex-shrink: 0;
  display: block;
}

.adm-mon__hero {
  margin-bottom: 1rem;
}

.adm-mon__title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  color: #f7e628;
}

.adm-mon__lead {
  margin: 0.5rem 0 0;
  max-width: 36rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #ccc7ac;
}

.adm-mon__event-name {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.35rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.02em;
}

.adm-mon__bento {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  margin-bottom: 1.25rem;
  flex-shrink: 0;
}

@media (min-width: 640px) {
  .adm-mon__bento {
    grid-template-columns: 1fr 1fr;
  }
}

@media (min-width: 1024px) {
  .adm-mon__bento {
    grid-template-columns: repeat(4, 1fr);
  }
}

.adm-mon__stat {
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
}

.adm-mon__stat-label {
  margin: 0;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #ccc7ac;
}

.adm-mon__stat-value {
  margin: 0.5rem 0 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 2rem;
  font-weight: 900;
  color: #fff;
}

.adm-mon__stat-value--accent {
  color: #f7e628;
}

.adm-mon__seatmap {
  box-sizing: border-box;
  width: 100%;
  min-width: 100%;
  margin-top: 0.5rem;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  /* Espai vertical gran: el mapa escala amb el contenidor (no cal zoom al navegador) */
  min-height: max(28rem, calc(100vh - 20rem));
  min-height: max(28rem, calc(100dvh - 20rem));
}

.adm-mon__seatmap :deep(.ism-root) {
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
  min-width: 100%;
  height: 100%;
}

.adm-mon__seatmap :deep(.ism-map-root) {
  flex: 1 1 auto;
  min-height: max(22rem, calc(100vh - 21rem));
  min-height: max(22rem, calc(100dvh - 21rem));
}

.adm-mon__muted {
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.45);
}

.adm-mon__err {
  color: #ffb4ab;
  font-size: 0.95rem;
  margin: 0 0 1rem;
}
</style>
