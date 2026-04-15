<template>
  <div class="adm-ana">
    <header class="admin-page-hero admin-page-hero--spaced">
      <h1 class="admin-page-title">
        Analítiques
      </h1>
      <p class="admin-page-lead">
        Ingressos, rendiment per esdeveniment i ocupació per categoria segons el preset de dates (7, 30 o 90 dies).
      </p>
    </header>

    <section class="adm-ana__panel adm-ana__panel--period" aria-label="Filtre de període">
      <h2 class="adm-ana__h2">
        Període
      </h2>
      <div class="adm-ana__segment" role="tablist" aria-label="Presets de dies">
        <button
          type="button"
          class="adm-ana__segment-btn"
          role="tab"
          :aria-selected="preset === '7'"
          :class="{ 'adm-ana__segment-btn--on': preset === '7' }"
          @click="onPreset7"
        >
          7 dies
        </button>
        <button
          type="button"
          class="adm-ana__segment-btn"
          role="tab"
          :aria-selected="preset === '30'"
          :class="{ 'adm-ana__segment-btn--on': preset === '30' }"
          @click="onPreset30"
        >
          30 dies
        </button>
        <button
          type="button"
          class="adm-ana__segment-btn"
          role="tab"
          :aria-selected="preset === '90'"
          :class="{ 'adm-ana__segment-btn--on': preset === '90' }"
          @click="onPreset90"
        >
          Trimestre
        </button>
      </div>
      <p v-if="loadErr" class="adm-ana__err">
        {{ loadErr }}
      </p>
    </section>

    <section class="adm-ana__panel adm-ana__panel--summary" aria-label="Total guanyat">
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

    <section class="adm-ana__panel adm-ana__panel--events" aria-label="Per esdeveniment">
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

    <section class="adm-ana__panel adm-ana__panel--cats" aria-label="Ocupació per categoria">
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
  preset.value = '7';
  setRangeDays(7);
  loadAll();
}

function onPreset30 () {
  preset.value = '30';
  setRangeDays(30);
  loadAll();
}

function onPreset90 () {
  preset.value = '90';
  setRangeDays(90);
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
/* Tokens alineats amb el mockup TR3 (fons fosc, accent #f7e628 / #ffee32) */
.adm-ana {
  box-sizing: border-box;
  width: 100%;
  max-width: 80rem;
  margin: 0 auto;
  padding: 0 0 2rem;
  display: grid;
  gap: 2.5rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
}

/* Mateix espai títol ↔ primer bloc que al dashboard: el gap de la graella ja és 2.5rem */
.adm-ana > header.admin-page-hero.admin-page-hero--spaced:first-child {
  margin-bottom: 0;
}

.adm-ana__h2 {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
}

.adm-ana__panel {
  position: relative;
  padding: 1.75rem 2rem;
  background: #1c1b1b;
  border: 1px solid rgba(149, 145, 120, 0.2);
  border-radius: 1rem;
}

.adm-ana__panel--summary {
  overflow: hidden;
  background: #1c1b1b;
}

.adm-ana__panel--summary::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: radial-gradient(circle at top right, rgba(247, 230, 40, 0.08) 0%, transparent 70%);
}

.adm-ana__panel--summary > * {
  position: relative;
  z-index: 1;
}

/* Barra segmentada tipus píndola (fons fosc, segment actiu en gris mig) */
.adm-ana__segment {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.2rem;
  max-width: 100%;
  margin-bottom: 0.65rem;
  padding: 0.2rem;
  background: #141414;
  border: 1px solid #262626;
  border-radius: 9999px;
}

.adm-ana__segment-btn {
  padding: 0.45rem 1.1rem;
  border: none;
  border-radius: 9999px;
  background: transparent;
  color: #737373;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease;
}

.adm-ana__segment-btn:hover {
  color: #a3a3a3;
}

.adm-ana__segment-btn--on {
  background: #2d2d2d;
  color: #fafafa;
}

.adm-ana__err {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
  color: #ffb4ab;
}

.adm-ana__muted {
  margin: 0;
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.45);
}

.adm-ana__kpi {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: clamp(2.5rem, 6vw, 3.75rem);
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -0.02em;
  color: #f7e628;
}

.adm-ana__panel--events .adm-ana__h2 {
  margin-bottom: 0.35rem;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: #fff;
}

.adm-ana__panel--cats .adm-ana__h2 {
  margin-bottom: 0.35rem;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: #fff;
}

.adm-ana__tablewrap {
  overflow: auto;
  margin-top: 0.5rem;
}

.adm-ana__table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 0.65rem;
  font-size: 0.9rem;
}

.adm-ana__table thead th {
  padding: 0 1rem 0.35rem;
  border: none;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-align: left;
  color: rgba(255, 255, 255, 0.4);
}

.adm-ana__table thead th:not(:first-child) {
  text-align: right;
}

.adm-ana__table tbody td {
  padding: 1.1rem 1.25rem;
  border: none;
  vertical-align: middle;
  color: #fff;
  background: #353534;
}

.adm-ana__table tbody td:not(:first-child) {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
  text-align: right;
}

.adm-ana__table tbody td:first-child {
  border-top-left-radius: 0.75rem;
  border-bottom-left-radius: 0.75rem;
  border-left: 4px solid #f7e628;
  font-weight: 700;
}

.adm-ana__table tbody td:last-child {
  border-top-right-radius: 0.75rem;
  border-bottom-right-radius: 0.75rem;
}

.adm-ana__table tbody tr {
  outline: 1px solid rgba(149, 145, 120, 0.12);
  border-radius: 0.75rem;
  transition:
    transform 0.2s ease,
    outline-color 0.2s ease;
}

.adm-ana__table tbody tr:hover {
  transform: scale(1.01);
  outline-color: rgba(247, 230, 40, 0.35);
}

.adm-ana__bars {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  margin-top: 0.5rem;
}

.adm-ana__baritem {
  display: grid;
  grid-template-columns: minmax(5rem, 11rem) 1fr 3.25rem;
  gap: 0.65rem;
  align-items: center;
}

.adm-ana__barlabel {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #fff;
}

.adm-ana__bartrack {
  height: 0.5rem;
  overflow: hidden;
  border-radius: 9999px;
  background: #353534;
}

.adm-ana__barfill {
  height: 100%;
  border-radius: 9999px;
  background: #f7e628;
}

.adm-ana__barpct {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.8rem;
  font-weight: 900;
  color: #f7e628;
  text-align: right;
}

@media (min-width: 1024px) {
  .adm-ana {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  }

  .adm-ana > header.admin-page-hero {
    grid-column: 1 / -1;
  }

  .adm-ana__panel--period {
    grid-column: 1 / -1;
  }

  .adm-ana__panel--summary {
    grid-column: 1 / -1;
  }

  .adm-ana__panel--events {
    grid-column: 1;
  }

  .adm-ana__panel--cats {
    grid-column: 2;
    align-self: start;
  }
}
</style>
