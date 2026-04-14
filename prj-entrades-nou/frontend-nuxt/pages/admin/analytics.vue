<template>
  <div class="adm-ana">
    <h1 class="adm-ana__h1">Analítiques</h1>

    <section class="adm-ana__panel" aria-label="Filtre de període">
      <h2 class="adm-ana__h2">Període</h2>
      <div class="adm-ana__presets">
        <button
          type="button"
          class="adm-ana__chip"
          :class="{ 'adm-ana__chip--on': preset === '7' }"
          @click="onPreset7"
        >
          7 dies
        </button>
        <button
          type="button"
          class="adm-ana__chip"
          :class="{ 'adm-ana__chip--on': preset === '30' }"
          @click="onPreset30"
        >
          30 dies
        </button>
        <button
          type="button"
          class="adm-ana__chip"
          :class="{ 'adm-ana__chip--on': preset === 'custom' }"
          @click="toggleCustomPanel"
        >
          Personalitzar
        </button>
      </div>
      <div v-if="customPanelOpen" class="adm-ana__custom" role="region" aria-label="Rang de dates personalitzat">
        <p class="adm-ana__custom-hint">
          Tria des de i fins a (es recarrega sol en canviar les dates).
        </p>
        <div class="adm-ana__custom-row">
          <div class="adm-ana__custom-pair">
            <label class="adm-ana__lbl" for="ana-from">Des de</label>
            <input
              id="ana-from"
              v-model="dateFrom"
              type="date"
              class="adm-ana__input"
              @change="onCustomDatesChange"
            >
          </div>
          <div class="adm-ana__custom-pair">
            <label class="adm-ana__lbl" for="ana-to">Fins a</label>
            <input
              id="ana-to"
              v-model="dateTo"
              type="date"
              class="adm-ana__input"
              @change="onCustomDatesChange"
            >
          </div>
        </div>
      </div>
      <p v-if="loadErr" class="adm-ana__err">
        {{ loadErr }}
      </p>
    </section>

    <section class="adm-ana__panel" aria-label="Total guanyat">
      <h2 class="adm-ana__h2">Total guanyat</h2>
      <p v-if="summaryPending" class="adm-ana__muted">
        Carregant…
      </p>
      <p v-else-if="summary" class="adm-ana__kpi">
        €{{ formatEuro(summary.total_revenue_eur) }}
      </p>
      <p v-else class="adm-ana__muted">
        Sense dades.
      </p>
    </section>

    <section class="adm-ana__panel" aria-label="Per esdeveniment">
      <h2 class="adm-ana__h2">Rendiment per esdeveniment</h2>
      <p v-if="eventsPending" class="adm-ana__muted">
        Carregant…
      </p>
      <div v-else-if="eventRows.length > 0" class="adm-ana__tablewrap">
        <table class="adm-ana__table">
          <thead>
            <tr>
              <th scope="col">
                Esdeveniment
              </th>
              <th scope="col">
                Ingressos (€)
              </th>
              <th scope="col">
                Mitjana / dia (€)
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, ri) in eventRows" :key="'ev-' + ri">
              <td>{{ row.name }}</td>
              <td>{{ formatEuro(row.revenue_eur) }}</td>
              <td>{{ formatEuro(row.avg_daily_revenue_eur) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="adm-ana__muted">
        Cap venda en aquest període.
      </p>
    </section>

    <section class="adm-ana__panel" aria-label="Ocupació per categoria">
      <h2 class="adm-ana__h2">
        Ocupació per categoria
      </h2>
      <p v-if="catPending" class="adm-ana__muted">
        Carregant…
      </p>
      <div v-else-if="categoryRows.length > 0" class="adm-ana__bars">
        <div v-for="(c, ci) in categoryRows" :key="'cb-' + ci" class="adm-ana__baritem">
          <div class="adm-ana__barlabel">
            {{ c.label }}
          </div>
          <div
            class="adm-ana__bartrack"
            role="progressbar"
            :aria-valuenow="Math.round(c.occupancy_percent)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Ocupació ${c.label} ${formatPct(c.occupancy_percent)} per cent`"
          >
            <div
              class="adm-ana__barfill"
              :style="{ width: barWidthPct(c.occupancy_percent) }"
            />
          </div>
          <div class="adm-ana__barpct">
            {{ formatPct(c.occupancy_percent) }}%
          </div>
        </div>
      </div>
      <p v-else class="adm-ana__muted">
        Sense dades de categories.
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson } = useAuthorizedApi();

const dateFrom = ref('');
const dateTo = ref('');
const preset = ref('7');
const customPanelOpen = ref(false);
const loadPending = ref(false);
const loadErr = ref('');
const summaryPending = ref(false);
const eventsPending = ref(false);
const catPending = ref(false);
const summary = ref(null);
const eventsPayload = ref(null);
const catPayload = ref(null);

const eventRows = computed(() => {
  const p = eventsPayload.value;
  if (!p || !p.events) {
    return [];
  }
  return p.events;
});

const categoryRows = computed(() => {
  const p = catPayload.value;
  if (!p || !p.categories) {
    return [];
  }
  return p.categories;
});

function isoDate (d) {
  const y = d.getFullYear();
  let m = String(d.getMonth() + 1);
  if (m.length < 2) {
    m = `0${m}`;
  }
  let day = String(d.getDate());
  if (day.length < 2) {
    day = `0${day}`;
  }
  return `${y}-${m}-${day}`;
}

function setRangeDays (days) {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - (days - 1));
  dateFrom.value = isoDate(start);
  dateTo.value = isoDate(end);
}

function onPreset7 () {
  customPanelOpen.value = false;
  preset.value = '7';
  setRangeDays(7);
  loadAll();
}

function onPreset30 () {
  customPanelOpen.value = false;
  preset.value = '30';
  setRangeDays(30);
  loadAll();
}

function toggleCustomPanel () {
  if (customPanelOpen.value) {
    customPanelOpen.value = false;
  } else {
    customPanelOpen.value = true;
    preset.value = 'custom';
  }
}

function onCustomDatesChange () {
  preset.value = 'custom';
  loadAll();
}

function queryString () {
  const q = new URLSearchParams();
  q.set('date_from', dateFrom.value);
  q.set('date_to', dateTo.value);
  return q.toString();
}

async function loadAll () {
  loadErr.value = '';
  if (!dateFrom.value || !dateTo.value) {
    loadErr.value = 'Selecciona des de i fins a.';
    return;
  }
  loadPending.value = true;
  summaryPending.value = true;
  eventsPending.value = true;
  catPending.value = true;
  summary.value = null;
  eventsPayload.value = null;
  catPayload.value = null;
  const qs = queryString();
  try {
    summary.value = await getJson(`/api/admin/analytics/summary?${qs}`);
  } catch (e) {
    loadErr.value = 'No s’ha pogut carregar el resum.';
    console.error(e);
  } finally {
    summaryPending.value = false;
  }
  try {
    eventsPayload.value = await getJson(`/api/admin/analytics/events?${qs}`);
  } catch (e) {
    if (loadErr.value === '') {
      loadErr.value = 'No s’han pogut carregar els esdeveniments.';
    }
    console.error(e);
  } finally {
    eventsPending.value = false;
  }
  try {
    catPayload.value = await getJson(`/api/admin/analytics/categories/occupancy?${qs}`);
  } catch (e) {
    if (loadErr.value === '') {
      loadErr.value = 'No s’ha pogut carregar l’ocupació per categoria.';
    }
    console.error(e);
  } finally {
    catPending.value = false;
    loadPending.value = false;
  }
}

function formatEuro (n) {
  const x = Number(n);
  if (Number.isNaN(x)) {
    return '0';
  }
  return x.toFixed(2);
}

function formatPct (n) {
  const x = Number(n);
  if (Number.isNaN(x)) {
    return '0';
  }
  return x.toFixed(1);
}

function barWidthPct (n) {
  let p = Number(n);
  if (Number.isNaN(p)) {
    p = 0;
  }
  if (p < 0) {
    p = 0;
  }
  if (p > 100) {
    p = 100;
  }
  return `${p}%`;
}

onMounted(() => {
  setRangeDays(7);
  preset.value = '7';
  loadAll();
});
</script>

<style scoped>
.adm-ana {
  max-width: 56rem;
}
.adm-ana__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-ana__h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-ana__panel {
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-ana__presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  align-items: center;
}
.adm-ana__custom {
  margin-top: 0.75rem;
  padding: 0.75rem;
  background: #0a0a0a;
  border: 1px solid #333;
  border-radius: 8px;
}
.adm-ana__custom-hint {
  margin: 0 0 0.65rem;
  font-size: 0.8rem;
  color: #888;
}
.adm-ana__custom-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 1.5rem;
  align-items: flex-end;
}
.adm-ana__custom-pair {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 10rem;
}
.adm-ana__lbl {
  font-size: 0.85rem;
  color: #aaa;
}
.adm-ana__input {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #eee;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  max-width: 100%;
}
.adm-ana__chip {
  background: #222;
  color: #eee;
  border: 1px solid #444;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
}
.adm-ana__chip--on {
  border-color: #ff0055;
  color: #ff0055;
}
.adm-ana__err {
  color: #ff6b6b;
  font-size: 0.9rem;
  margin: 0.75rem 0 0;
}
.adm-ana__muted {
  color: #888;
  font-size: 0.95rem;
}
.adm-ana__kpi {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  color: #fff;
}
.adm-ana__tablewrap {
  overflow: auto;
}
.adm-ana__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.adm-ana__table th,
.adm-ana__table td {
  padding: 0.4rem 0.5rem;
  border-bottom: 1px solid #2a2a2a;
  text-align: left;
  color: #e0e0e0;
}
.adm-ana__bars {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.adm-ana__baritem {
  display: grid;
  grid-template-columns: minmax(6rem, 12rem) 1fr 3.5rem;
  gap: 0.5rem;
  align-items: center;
}
.adm-ana__barlabel {
  font-size: 0.85rem;
  color: #ccc;
}
.adm-ana__bartrack {
  height: 10px;
  background: #2a2a2a;
  border-radius: 6px;
  overflow: hidden;
}
.adm-ana__barfill {
  height: 100%;
  background: #ff0055;
  border-radius: 6px;
}
.adm-ana__barpct {
  font-size: 0.85rem;
  color: #fff;
  text-align: right;
}
</style>
