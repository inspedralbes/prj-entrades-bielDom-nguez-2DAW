<template>
  <main class="seats-page" data-seatmap-route="interactive-v2">
    <SeatsPageHeader :to="detailBackHref" />

    <div v-if="pending" class="loading">Carregant mapa…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else-if="event">
      <div class="event-info-bar">
        <h2 class="event-name">{{ event.name }}</h2>
        <p class="event-meta">{{ formatDate(event.starts_at) }} · {{ event.venue?.name }}</p>
      </div>

      <div class="seats-map-grow">
        <ClientOnly>
          <InteractiveSeatMap :event-id="eventId" @seat-click="onSeatClick" />
          <template #fallback>
            <p class="loading">Preparant mapa de seients…</p>
          </template>
        </ClientOnly>
      </div>

      <p v-if="holdMessage" class="seats-toast">{{ holdMessage }}</p>

      <SeatsPurchaseFooter
        v-if="event"
        :unit-price="unitPrice"
        :selected-count="selectedCount"
        :max-seats="maxSeats"
        :total-price="totalPrice"
        :checkout-pending="checkoutPending"
        :pending-seat-sync-count="pendingSeatSyncCount"
        @checkout="goToCheckout"
      />
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import InteractiveSeatMap from '~/components/InteractiveSeatMap.vue';
import SeatsPageHeader from '~/components/seatmap/SeatsPageHeader.vue';
import SeatsPurchaseFooter from '~/components/seatmap/SeatsPurchaseFooter.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useCinemaSeatCheckoutFlow } from '~/composables/useCinemaSeatCheckoutFlow';
import { usePrivateSeatmapSocket } from '~/composables/usePrivateSeatmapSocket';
import { useAuthStore } from '~/stores/auth';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const router = useRouter();
const { getJson, postJson } = useAuthorizedApi();
const auth = useAuthStore();
const seatmapStore = useInteractiveSeatmapStore();

const { emitSeatHoldIntent, emitSeatHoldRollback } = usePrivateSeatmapSocket(
  computed(() => route.params.eventId),
);

const eventId = computed(() => {
  const rawId = route.params.eventId;
  if (Array.isArray(rawId)) {
    return rawId[0];
  }
  return rawId;
});

const detailBackHref = computed(() => {
  const path = `/events/${eventId.value}`;
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

const releaseTargetEventId = ref('');

watch(
  () => eventId.value,
  (v) => {
    if (v !== undefined && v !== null && String(v).trim() !== '') {
      releaseTargetEventId.value = String(v);
    }
  },
  { immediate: true },
);

const pending = ref(true);
const error = ref('');
const event = ref(null);
const maxSeats = 6;

const {
  holdMessage,
  checkoutPending,
  pendingSeatSyncCount,
  onSeatClick,
  goToCheckout,
} = useCinemaSeatCheckoutFlow({
  eventId,
  router,
  postJson,
  emitSeatHoldIntent,
  emitSeatHoldRollback,
  maxSeats,
});

const selectedCount = computed(() => {
  return seatmapStore.selectedSeatIds.length;
});

const unitPrice = computed(() => {
  if (!event.value || !event.value.price) {
    return 0;
  }
  return parseFloat(event.value.price);
});

const totalPrice = computed(() => {
  return unitPrice.value * selectedCount.value;
});

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  } catch {
    return iso;
  }
}

async function fetchData (opts) {
  let silent = false;
  if (opts !== undefined && opts !== null && opts.silent === true) {
    silent = true;
  }
  const id = eventId.value;
  if (id === undefined || id === null || id === '') {
    return;
  }
  if (!silent) {
    pending.value = true;
  }
  error.value = '';
  try {
    auth.init();
    const ev = await getJson(`/api/events/${id}`);
    event.value = ev;

    const sm = await getJson(`/api/events/${id}/seatmap`, { noCache: true });
    seatmapStore.bootstrapFromApi(sm, id);

    const uid = auth.user && auth.user.id !== undefined ? auth.user.id : null;
    seatmapStore.setCurrentUserId(uid);
  } catch (e) {
    if (!silent) {
      error.value = 'No s\'ha pogut carregar el mapa.';
    }
    console.error(e);
  } finally {
    if (!silent) {
      pending.value = false;
    }
  }
}

function onVisibilityChange () {
  if (typeof document === 'undefined') {
    return;
  }
  if (document.visibilityState !== 'visible') {
    return;
  }
  const id = eventId.value;
  if (id === undefined || id === null || id === '') {
    return;
  }
  fetchData({ silent: true });
}

function onPageShow (e) {
  if (!e || !e.persisted) {
    return;
  }
  const id = eventId.value;
  if (id === undefined || id === null || id === '') {
    return;
  }
  fetchData({ silent: true });
}

watch(
  () => route.fullPath,
  () => {
    fetchData();
  },
  { immediate: true },
);

function releaseAllHoldsForThisEvent () {
  if (!import.meta.client) {
    return;
  }
  const idStr = releaseTargetEventId.value;
  if (idStr === '') {
    return;
  }
  postJson(`/api/events/${idStr}/seat-holds/release-all`, {}).catch(() => {});
}

onMounted(() => {
  if (import.meta.client && typeof window !== 'undefined') {
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pageshow', onPageShow);
  }
});

onUnmounted(() => {
  releaseAllHoldsForThisEvent();
  if (import.meta.client && typeof window !== 'undefined') {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('pageshow', onPageShow);
  }
});
</script>

<style scoped>
.seats-page {
  padding: 1rem;
  padding-bottom: 1rem;
  min-height: 0;
  flex: 1 1 auto;
  background: var(--bg);
  color: var(--fg);
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
}

.seats-map-grow {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.seats-map-grow :deep(.ism-root) {
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
}
.loading,
.error {
  text-align: center;
  color: #888;
  padding: 2rem;
}
.error {
  color: #ff6b6b;
}
.event-info-bar {
  background: #1a1a1a;
  padding: 0.75rem;
  border-radius: 8px;
  margin-bottom: 1rem;
}
.event-name {
  font-size: 1rem;
  color: #f5f5f5;
  margin: 0 0 0.25rem;
}
.event-meta {
  font-size: 0.8rem;
  color: #888;
  margin: 0;
}
.seats-toast {
  color: #ffb020;
  font-size: 0.9rem;
  margin-top: 0.75rem;
}

@media (max-width: 899px) {
  .seats-page {
    padding: 0.5rem 0.75rem 0.25rem;
    padding-bottom: calc(5.75rem + env(safe-area-inset-bottom, 0px));
  }

  .seats-map-grow {
    min-height: 0;
    flex: 1 1 0;
  }

  .seats-map-grow :deep(.ism-root) {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    gap: 0.45rem;
  }

  .seats-map-grow :deep(.ism-map-root) {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .seats-map-grow :deep(.ism-svg) {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
  }

  .event-info-bar {
    margin-bottom: 0.5rem;
    padding: 0.55rem 0.65rem;
  }
}

@media (min-width: 900px) {
  .seats-page {
    padding-bottom: 6rem;
  }
}
</style>
