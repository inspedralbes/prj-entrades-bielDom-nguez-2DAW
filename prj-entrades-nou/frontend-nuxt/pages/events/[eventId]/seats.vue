<template>
  <main class="seats-page">
    <header class="seats-header">
      <NuxtLink to="/" class="back-link">← Inici</NuxtLink>
      <h1 class="title">Selecció de seients</h1>
      <p class="sub">Esdeveniment #{{ eventId }}</p>
    </header>

    <p v-if="pending" class="muted">Carregant mapa…</p>
    <p v-else-if="error" class="err">No s’ha pogut carregar el mapa.</p>

    <template v-else-if="seatmap">
      <section v-if="seatmap.snapshotImageUrl" class="snapshot-wrap">
        <img
          :src="seatmap.snapshotImageUrl"
          alt="Mapa de la sala"
          class="snapshot-img"
        >
      </section>
      <p v-else class="muted">
        Sense imatge de mapa (fallback només zones / llista de seients).
      </p>

      <div v-if="holdStore.contentionMessage" class="banner banner-warn" role="alert">
        {{ holdStore.contentionMessage }}
        <button type="button" class="banner-close" @click="holdStore.clearContention(); refresh()">
          Tancar
        </button>
      </div>

      <div v-if="holdError" class="banner banner-err">
        {{ holdError }}
      </div>

      <div v-if="holdStore.hasActiveHold" class="hold-bar">
        <span class="hold-label">Reserva activa</span>
        <span class="hold-time">{{ remainingLabel || '—' }}</span>
      </div>

      <section
        v-for="block in zonesWithSeats"
        :key="block.id"
        class="zone-block"
      >
        <h2 class="zone-title">{{ block.label }}</h2>
        <ul class="seat-grid">
          <li v-for="seat in block.seats" :key="seat.id">
            <button
              type="button"
              class="seat-btn"
              :class="seatClass(seat)"
              :disabled="seatDisabled(seat)"
              @click="onSeatClick(seat)"
            >
              {{ seat.key }}
            </button>
          </li>
        </ul>
      </section>

      <footer class="actions">
        <p class="hint">
          Seleccionats: {{ holdStore.selectionCount }} / 6
        </p>
        <button
          type="button"
          class="btn-primary"
          :disabled="holdStore.selectionCount < 1 || holdLoading || holdStore.hasActiveHold"
          @click="createHold"
        >
          Reservar seients
        </button>
      </footer>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const route = useRoute();
const config = useRuntimeConfig();
const holdStore = useHoldStore();
const { fetchApi } = useApi();

const eventId = computed(() => String(route.params.eventId || ''));

const { data: seatmap, pending, error, refresh } = await useAsyncData(
  () => `seatmap-${route.params.eventId}`,
  () => {
    const base = (config.public.apiUrl || '').replace(/\/$/, '');
    return $fetch(`${base}/api/events/${route.params.eventId}/seatmap`);
  },
  { watch: [() => route.params.eventId] },
);

const holdLoading = ref(false);
const holdError = ref(null);

const availableIds = computed(() => {
  const seats = seatmap.value?.seats || [];
  const set = new Set();
  for (const s of seats) {
    if (s.status === 'available') {
      set.add(Number(s.id));
    }
  }
  return set;
});

const zonesWithSeats = computed(() => {
  const m = seatmap.value;
  if (!m?.zones) {
    return [];
  }
  const seats = m.seats || [];
  return m.zones.map((z) => ({
    id: z.id,
    label: z.label,
    seats: seats.filter((s) => String(s.zoneId) === String(z.id)),
  }));
});

function seatClass (seat) {
  const sel = holdStore.selectedSeatIds.includes(Number(seat.id));
  return {
    'is-selected': sel,
    'is-sold': seat.status === 'sold',
    'is-held': seat.status === 'held',
    'is-blocked': seat.status === 'blocked',
  };
}

function seatDisabled (seat) {
  if (holdStore.hasActiveHold) {
    return true;
  }
  if (seat.status !== 'available') {
    return true;
  }
  const id = Number(seat.id);
  const sel = holdStore.selectedSeatIds.includes(id);
  if (sel) {
    return false;
  }
  return holdStore.selectionCount >= 6;
}

function onSeatClick (seat) {
  if (holdStore.hasActiveHold) {
    return;
  }
  holdStore.toggleSeatId(seat.id, { availableIds: availableIds.value });
}

const nowTick = ref(Date.now());
let clockId = null;

onMounted(() => {
  holdStore.ensureAnonymousSession();
  clockId = setInterval(() => {
    nowTick.value = Date.now();
  }, 1000);
});

const remainingLabel = computed(() => {
  if (!holdStore.holdExpiresAt) {
    return null;
  }
  const end = new Date(holdStore.holdExpiresAt).getTime();
  const ms = Math.max(0, end - nowTick.value);
  const s = Math.floor(ms / 1000);
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

let pollId = null;
watch(
  () => holdStore.holdId,
  (id) => {
    if (pollId) {
      clearInterval(pollId);
      pollId = null;
    }
    if (!id) {
      return;
    }
    pollId = setInterval(async () => {
      try {
        const res = await fetchApi(`/api/holds/${id}/time`);
        if (res?.expires_at) {
          holdStore.applyResync({ expiresAt: res.expires_at });
        }
      } catch {
        holdStore.clearHoldTimerOnly();
        refresh();
      }
    }, 12000);
  },
  { immediate: true },
);

onUnmounted(() => {
  if (clockId) {
    clearInterval(clockId);
  }
  if (pollId) {
    clearInterval(pollId);
  }
});

useEventSeatSocket(eventId, {
  onContention: (payload) => {
    const msg = payload?.message;
    holdStore.setContention(typeof msg === 'string' ? msg : null);
    refresh();
  },
  onResync: (payload) => {
    const ex = payload?.expiresAt;
    if (typeof ex === 'string') {
      holdStore.applyResync({ expiresAt: ex });
    }
  },
});

async function createHold () {
  holdError.value = null;
  holdLoading.value = true;
  try {
    const res = await fetchApi(`/api/events/${eventId.value}/holds`, {
      method: 'POST',
      body: {
        seat_ids: holdStore.selectedSeatIds,
        anonymous_session_id: holdStore.anonymousSessionId,
      },
    });
    holdStore.setHoldResult({
      holdId: res.hold_id,
      expiresAt: res.expires_at,
      eventId: eventId.value,
    });
    await refresh();
  } catch (e) {
    const msg = e?.data?.message || e?.message || 'No s’ha pogut reservar';
    holdError.value = msg;
  } finally {
    holdLoading.value = false;
  }
}
</script>

<style scoped>
.seats-page {
  min-height: 100vh;
  background: #0a0a0c;
  color: #f2f2f2;
  padding: 1.25rem 1rem 5rem;
  font-family: system-ui, sans-serif;
}

.seats-header {
  margin-bottom: 1.25rem;
}

.back-link {
  color: #ff6b9d;
  text-decoration: none;
  font-size: 0.9rem;
}

.back-link:hover {
  text-decoration: underline;
}

.title {
  font-size: 1.35rem;
  font-weight: 700;
  margin: 0.5rem 0 0.15rem;
  color: #fff;
}

.sub {
  margin: 0;
  color: #888;
  font-size: 0.85rem;
}

.muted {
  color: #777;
}

.err {
  color: #f66;
}

.snapshot-wrap {
  margin-bottom: 1.25rem;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #222;
}

.snapshot-img {
  display: block;
  width: 100%;
  height: auto;
  max-height: 280px;
  object-fit: contain;
  background: #111;
}

.banner {
  padding: 0.65rem 0.85rem;
  border-radius: 6px;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.banner-warn {
  background: #3d2a12;
  border: 1px solid #a66;
  color: #ffd6bf;
}

.banner-err {
  background: #3a1212;
  border: 1px solid #c44;
  color: #fcc;
}

.banner-close {
  flex-shrink: 0;
  background: transparent;
  border: 1px solid #888;
  color: #fff;
  border-radius: 4px;
  padding: 0.2rem 0.5rem;
  cursor: pointer;
}

.hold-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.6rem 0.85rem;
  margin-bottom: 1rem;
  background: #14221a;
  border: 1px solid #2a5;
  border-radius: 6px;
}

.hold-label {
  font-weight: 600;
  color: #8f8;
}

.hold-time {
  font-variant-numeric: tabular-nums;
  font-size: 1.25rem;
  letter-spacing: 0.05em;
}

.zone-block {
  margin-bottom: 1.5rem;
}

.zone-title {
  font-size: 1rem;
  font-weight: 600;
  color: #ccc;
  margin: 0 0 0.5rem;
}

.seat-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.seat-btn {
  min-width: 2.75rem;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  border: 1px solid #444;
  background: #1e1e22;
  color: #eee;
  font-size: 0.8rem;
  cursor: pointer;
}

.seat-btn:hover:not(:disabled) {
  border-color: #ff6b9d;
  color: #fff;
}

.seat-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.seat-btn.is-selected {
  background: #6b1f3d;
  border-color: #ff6b9d;
  color: #fff;
}

.seat-btn.is-sold,
.seat-btn.is-held,
.seat-btn.is-blocked {
  background: #2a2a2e;
  color: #666;
}

.actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));
  background: linear-gradient(transparent, #0a0a0c 30%);
  border-top: 1px solid #222;
}

.hint {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  color: #999;
}

.btn-primary {
  width: 100%;
  padding: 0.75rem 1rem;
  border: none;
  border-radius: 6px;
  background: #ff0055;
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn-primary:not(:disabled):hover {
  filter: brightness(1.08);
}
</style>
