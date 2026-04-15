<template>
  <main class="seats-page" data-seatmap-route="interactive-v2">
    <header class="seats-header">
      <NuxtLink
        :to="detailBackHref"
        class="seats-back-btn"
        aria-label="Tornar a l'esdeveniment"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      </NuxtLink>
    </header>

    <div v-if="pending" class="loading">Carregant mapa…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else-if="event">
      <div class="event-info-bar">
        <h2 class="event-name">{{ event.name }}</h2>
        <p class="event-meta">{{ formatDate(event.starts_at) }} · {{ event.venue?.name }}</p>
      </div>

      <!-- Mapa D3: només client (evita desalineació SSR/hidratació). -->
      <div class="seats-map-grow">
        <ClientOnly>
          <InteractiveSeatMap :event-id="eventId" @seat-click="onSeatClick" />
          <template #fallback>
            <p class="loading">Preparant mapa de seients…</p>
          </template>
        </ClientOnly>
      </div>

      <p v-if="holdMessage" class="seats-toast">{{ holdMessage }}</p>

      <footer v-if="event" class="ticket-footer" aria-label="Resum de compra">
        <div class="ticket-footer__left">
          <p v-if="unitPrice > 0" class="ticket-footer__muted">
            Preu per entrada: €{{ unitPrice.toFixed(2) }}
          </p>
          <p class="ticket-footer__count">
            {{ selectedCount }} {{ selectedCount === 1 ? 'entrada' : 'entrades' }}
            <span v-if="selectedCount > 0" class="ticket-footer__hint"> (màx. {{ maxSeats }} per persona)</span>
          </p>
          <p class="ticket-footer__total">
            Total: €{{ totalPrice.toFixed(2) }}
          </p>
        </div>
        <button
          type="button"
          class="ticket-footer__cta"
          :disabled="selectedCount === 0 || pendingSeatSyncCount > 0 || checkoutPending"
          @click="goToCheckout"
        >
          <span v-if="checkoutPending">Preparant compra…</span>
          <span v-else>Comprar · €{{ totalPrice.toFixed(2) }}</span>
        </button>
      </footer>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import InteractiveSeatMap from '~/components/InteractiveSeatMap.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
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

/** Conserva ?from= per al detall i el tab del footer. */
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

/** Esdeveniment del mapa (fix per onUnmounted: route.params ja pot ser la ruta nova). */
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
const holdMessage = ref('');
const checkoutPending = ref(false);
/** Seients amb POST pendent (evita doble clic i bloqueja compra fins sincronitzar). */
const pendingSeatSync = ref({});
const maxSeats = 6;

const pendingSeatSyncCount = computed(() => {
  const o = pendingSeatSync.value;
  const keys = Object.keys(o);
  let n = 0;
  for (let i = 0; i < keys.length; i++) {
    if (o[keys[i]] === true) {
      n += 1;
    }
  }
  return n;
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

/**
 * Carrega esdeveniment + seatmap (SoT: PG + holds Redis).
 * silent: sense spinner (tornada a la pestanya / keep-alive) per no amagar el mapa ja pintat.
 */
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
  /* Qualsevol reserva mentre la pestanya estava en segon pla: tornem a llegir Redis+PG */
  fetchData({ silent: true });
}

/**
 * Navegador “enrere/avant” (bfcache): la pàgina es restaura sense tornar a executar setup;
 * cal tornar a llegir el seatmap o els holds d’altres usuaris no apareixen.
 */
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

function setPendingSeat (seatId, v) {
  const next = {};
  const keys = Object.keys(pendingSeatSync.value);
  for (let i = 0; i < keys.length; i++) {
    next[keys[i]] = pendingSeatSync.value[keys[i]];
  }
  if (v) {
    next[seatId] = true;
  } else {
    delete next[seatId];
  }
  pendingSeatSync.value = next;
}

async function onSeatClick ({ seatId }) {
  holdMessage.value = '';
  if (pendingSeatSync.value[seatId]) {
    return;
  }
  if (seatmapStore.soldBySeatId[seatId]) {
    return;
  }

  const uid = auth.user && auth.user.id !== undefined ? String(auth.user.id) : '';

  if (seatmapStore.selectedSeatIds.indexOf(seatId) >= 0) {
    setPendingSeat(seatId, true);
    seatmapStore.optimisticRelease(seatId);
    try {
      await postJson(`/api/events/${eventId.value}/seat-holds/release`, { seat_id: seatId });
    } catch (err) {
      seatmapStore.restoreAfterFailedRelease(seatId, uid);
      const msg = err && err.data && err.data.message;
      holdMessage.value = msg || 'No s\'ha pogut alliberar el seient.';
    } finally {
      setPendingSeat(seatId, false);
    }
    return;
  }

  if (seatmapStore.selectedSeatIds.length >= maxSeats) {
    holdMessage.value = `Màxim ${maxSeats} entrades per persona.`;
    return;
  }

  const held = seatmapStore.heldBySeatId[seatId];
  if (held !== undefined && held !== '' && held !== uid) {
    holdMessage.value = 'Aquest seient està reservat per un altre usuari.';
    return;
  }

  setPendingSeat(seatId, true);
  seatmapStore.optimisticReserve(seatId, uid);
  emitSeatHoldIntent(seatId);
  try {
    await postJson(`/api/events/${eventId.value}/seat-holds`, { seat_id: seatId });
  } catch (err) {
    emitSeatHoldRollback(seatId);
    seatmapStore.revertOptimisticReserve(seatId);
    const msg = err && err.data && err.data.message;
    holdMessage.value = msg || 'No s\'ha pogut reservar el seient.';
  } finally {
    setPendingSeat(seatId, false);
  }
}

async function goToCheckout () {
  if (seatmapStore.selectedSeatIds.length === 0) {
    return;
  }
  if (checkoutPending.value) {
    return;
  }
  holdMessage.value = '';
  checkoutPending.value = true;
  const keys = [];
  const sel = seatmapStore.selectedSeatIds;
  for (let i = 0; i < sel.length; i++) {
    keys.push(sel[i]);
  }
  try {
    const evNum = parseInt(String(eventId.value), 10);
    if (Number.isNaN(evNum)) {
      holdMessage.value = 'Esdeveniment invàlid.';
      return;
    }
    const created = await postJson('/api/orders/cinema-seats', {
      event_id: evNum,
      seat_keys: keys,
    });
    await postJson(`/api/orders/${created.order_id}/confirm-payment`, {});
    await router.push({
      path: '/tickets',
      query: {
        eventId: String(eventId.value),
        orderId: String(created.order_id),
        new: '1',
      },
    });
  } catch (err) {
    let msg = 'No s\'ha pogut iniciar la compra.';
    if (err && err.data && err.data.message) {
      msg = err.data.message;
    }
    holdMessage.value = msg;
  } finally {
    checkoutPending.value = false;
  }
}

/* fullPath: tornar a aquesta URL (mateix eventId) torna a sincronitzar; només eventId no disparava si ja era el mateix */
watch(
  () => route.fullPath,
  () => {
    fetchData();
  },
  { immediate: true },
);

/**
 * Allibera tots els holds Redis d’aquest esdeveniment (mateix usuari).
 * Cridat en sortir del mapa: més fiable que només el socket-server → Laravel intern
 * (el fetch des del contenidor Node pot fallar sense que el client ho vegi).
 */
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
.seats-header {
  display: flex;
  align-items: center;
  margin-bottom: 0.65rem;
}

/* Mateix patró que `map-tr3__back` (cerca mapa): botó rodó, només icona. */
.seats-back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(42, 42, 42, 0.9);
  border: 1px solid rgba(74, 71, 51, 0.35);
  color: #ffee32;
  text-decoration: none;
  transition: opacity 0.2s ease;
}

.seats-back-btn:hover {
  opacity: 0.88;
}

.seats-back-btn .material-symbols-outlined {
  font-size: 1.35rem;
  line-height: 1;
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
.ticket-footer {
  position: fixed;
  left: 0;
  right: 0;
  z-index: 45;
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1rem;
  padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
  background: #0d0d0d;
  border-top: 1px solid #333;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.45);
  bottom: 0;
}

/* Barra de compra just a sobre del menú inferior fix (mòbil). */
@media (max-width: 899px) {
  .ticket-footer {
    bottom: var(--footer-stack);
  }

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

  /* Graella 10×10: omple l’alçària útil; sense scroll horitzontal. */
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

  .seats-header {
    margin-bottom: 0.5rem;
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
.ticket-footer__left {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
}
.ticket-footer__muted {
  margin: 0;
  font-size: 0.75rem;
  color: #888;
}
.ticket-footer__count {
  margin: 0;
  font-size: 0.9rem;
  color: #e5e5e5;
}
.ticket-footer__hint {
  font-size: 0.75rem;
  color: #666;
}
.ticket-footer__total {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}
.ticket-footer__cta {
  align-self: center;
  flex-shrink: 0;
  padding: 0.85rem 1.25rem;
  background: var(--accent);
  color: var(--accent-on);
  border: none;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
}
.ticket-footer__cta:disabled {
  background: #444;
  cursor: not-allowed;
}
.ticket-footer__cta:not(:disabled):hover {
  background: var(--accent-dim);
}
</style>
