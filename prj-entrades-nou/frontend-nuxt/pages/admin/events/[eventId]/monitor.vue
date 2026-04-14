<template>
  <div class="adm-mon">
    <p class="adm-mon__back">
      <NuxtLink to="/admin/events">← Esdeveniments</NuxtLink>
    </p>
    <h1 class="adm-mon__h1">Monitor d’esdeveniment</h1>
    <p v-if="loadErr" class="adm-mon__err">{{ loadErr }}</p>
    <div v-else-if="pending" class="adm-mon__muted">Carregant…</div>
    <template v-else-if="monitor">
      <section class="adm-mon__panel">
        <h2 class="adm-mon__h2">{{ monitor.name }}</h2>
        <dl class="adm-mon__dl">
          <div class="adm-mon__row">
            <dt>Aforament</dt>
            <dd>{{ monitor.capacity }}</dd>
          </div>
          <div class="adm-mon__row">
            <dt>Venuts</dt>
            <dd>{{ monitor.tickets_sold }}</dd>
          </div>
          <div class="adm-mon__row">
            <dt>Disponibles</dt>
            <dd>{{ monitor.remaining }}</dd>
          </div>
          <div class="adm-mon__row">
            <dt>Recaptació (EUR)</dt>
            <dd>{{ monitor.revenue_eur }}</dd>
          </div>
        </dl>
      </section>

      <section class="adm-mon__panel">
        <h2 class="adm-mon__h2">Holds actius (Redis)</h2>
        <p v-if="holdsRows.length === 0" class="adm-mon__muted">Cap hold.</p>
        <table v-else class="adm-mon__table" aria-label="Holds">
          <thead>
            <tr>
              <th>Seient</th>
              <th>Usuari</th>
              <th>TTL (s)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in holdsRows" :key="idx">
              <td>{{ row.seat_id }}</td>
              <td>{{ row.user_id }}</td>
              <td>{{ row.ttl_seconds }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="adm-mon__panel">
        <h2 class="adm-mon__h2">Mapa (temps real)</h2>
        <p class="adm-mon__muted">
          Mateix canal Socket que la vista de compra; només lectura.
        </p>
        <ClientOnly>
          <InteractiveSeatMap :event-id="eventIdStr" :read-only="true" />
          <template #fallback>
            <p class="adm-mon__muted">Preparant mapa…</p>
          </template>
        </ClientOnly>
      </section>
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

const holdsRows = computed(() => {
  const m = monitor.value;
  if (!m || !m.holds) {
    return [];
  }
  const h = m.holds;
  const out = [];
  for (let i = 0; i < h.length; i++) {
    out.push(h[i]);
  }
  return out;
});

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
      const uid = auth.user && auth.user.id !== undefined ? auth.user.id : null;
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
.adm-mon {
  max-width: 56rem;
}
.adm-mon__back {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
}
.adm-mon__back a {
  color: #ff0055;
}
.adm-mon__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-mon__h2 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-mon__panel {
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-mon__dl {
  margin: 0;
  display: grid;
  gap: 0.35rem;
}
.adm-mon__row {
  display: flex;
  gap: 0.75rem;
  font-size: 0.9rem;
  color: #e0e0e0;
}
.adm-mon__row dt {
  min-width: 10rem;
  color: #888;
}
.adm-mon__row dd {
  margin: 0;
}
.adm-mon__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.adm-mon__table th,
.adm-mon__table td {
  border: 1px solid #333;
  padding: 0.35rem 0.5rem;
  text-align: left;
}
.adm-mon__muted {
  font-size: 0.85rem;
  color: #777;
  margin: 0 0 0.5rem;
}
.adm-mon__err {
  color: #ff6b6b;
}
</style>
