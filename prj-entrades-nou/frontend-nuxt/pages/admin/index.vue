<template>
  <div class="adm-dash">
    <header class="admin-page-hero admin-page-hero--spaced">
      <h1 class="admin-page-title">
        Dashboard
      </h1>
      <p class="admin-page-lead">
        Monitoratge en temps real · node administratiu
      </p>
    </header>

    <p v-if="summaryErr" class="adm-dash__err">{{ summaryErr }}</p>
    <div v-else-if="summary" class="adm-dash__kpi-grid">
      <div class="adm-dash__kpi adm-dash__kpi--gold adm-dash__kpi--span-5">
        <p class="adm-dash__kpi-label">Ingressos avui (EUR)</p>
        <p class="adm-dash__kpi-value">{{ summary.revenue_today }}</p>
      </div>
      <div class="adm-dash__kpi adm-dash__kpi--light adm-dash__kpi--span-7">
        <p class="adm-dash__kpi-label">Comandes pagades avui</p>
        <p class="adm-dash__kpi-value">{{ summary.orders_paid_today }}</p>
      </div>
      <div class="adm-dash__kpi adm-dash__kpi--ink adm-dash__kpi--span-4">
        <p class="adm-dash__kpi-label">Usuaris en línia</p>
        <p class="adm-dash__kpi-value">{{ summary.online_users }}</p>
      </div>
      <div class="adm-dash__kpi adm-dash__kpi--soft adm-dash__kpi--span-4">
        <p class="adm-dash__kpi-label">Esdeveniments (catàleg)</p>
        <p class="adm-dash__kpi-value">{{ summary.events_total }}</p>
      </div>
      <div class="adm-dash__kpi adm-dash__kpi--gold-dim adm-dash__kpi--span-4">
        <p class="adm-dash__kpi-label">Comandes pagades (total)</p>
        <p class="adm-dash__kpi-value">{{ summary.orders_paid }}</p>
      </div>
      <div class="adm-dash__kpi adm-dash__kpi--ink adm-dash__kpi--span-12">
        <p class="adm-dash__kpi-label">Tiquets venuts (històric)</p>
        <p class="adm-dash__kpi-value">{{ summary.tickets_sold_total }}</p>
      </div>
    </div>

    <ClientOnly>
      <p v-if="chartsErr" class="adm-dash__err adm-dash__err--charts">{{ chartsErr }}</p>
      <div class="adm-dash__chart-panel">
        <p class="adm-dash__chart-title">Ingressos per dia (EUR) · últims 30 dies</p>
        <div class="adm-dash__chart-area">
          <canvas ref="revCanvas" class="adm-dash__chart-canvas" />
        </div>
      </div>
      <div class="adm-dash__chart-panel">
        <p class="adm-dash__chart-title">Comandes pagades per dia · últims 30 dies</p>
        <div class="adm-dash__chart-area">
          <canvas ref="ordCanvas" class="adm-dash__chart-canvas" />
        </div>
      </div>
    </ClientOnly>

    <NuxtLink
      v-if="summary"
      to="/admin/logs"
      class="adm-dash__panel adm-dash__panel--click"
    >
      <h2 class="adm-dash__h2">Darrers canvis (admin)</h2>
      <p class="adm-dash__muted">Obre la pàgina de registre complet (10 per pàgina).</p>
      <ul v-if="recentLogsPreview.length > 0" class="adm-dash__log-list">
        <li v-for="row in recentLogsPreview" :key="'l'+row.id" class="adm-dash__log-item">
          <span class="adm-dash__log-main">{{ row.date }} {{ row.time }} — {{ row.admin_name }}</span>
          <span class="adm-dash__log-ip">IP {{ row.ip_address }}</span>
          <span class="adm-dash__log-sum">{{ row.summary }}</span>
        </li>
      </ul>
      <p v-else class="adm-dash__muted">Encara no hi ha registres d’auditoria.</p>
    </NuxtLink>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useAdminDashboard } from '~/composables/useAdminDashboard';
import { useAdminDashboardStore } from '~/stores/adminDashboard';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson, postGraphql } = useAuthorizedApi();
const { connectSocket } = useAdminDashboard();
const adminDashStore = useAdminDashboardStore();

const summary = ref(null);
const summaryErr = ref('');
const chartsErr = ref('');
const pollSec = 12;
let stopPoll;
let stopSocket;

const revCanvas = ref(null);
const ordCanvas = ref(null);
let revChart;
let ordChart;

const GQL = `
  query AdminDashCharts {
    adminDashboardRevenueByDay(days: 30) { date revenue }
    adminDashboardOrdersPaidByDay(days: 30) { date count }
  }
`;

const recentLogsPreview = computed(() => {
  if (!summary.value) {
    return [];
  }
  const r = summary.value.recent_admin_logs;
  if (!r || !Array.isArray(r)) {
    return [];
  }
  return r;
});

function applyLivePayload (live) {
  if (!summary.value || !live || typeof live !== 'object') {
    return;
  }
  const keys = [
    'revenue_today',
    'pending_payment_count',
    'online_users',
    'events_total',
    'orders_paid',
    'orders_paid_today',
    'tickets_sold_total',
    'sync_alerts',
    'generated_at',
  ];
  const next = { ...summary.value };
  for (let i = 0; i < keys.length; i++) {
    const k = keys[i];
    if (Object.prototype.hasOwnProperty.call(live, k)) {
      next[k] = live[k];
    }
  }
  summary.value = next;
}

async function refreshSummary () {
  summaryErr.value = '';
  try {
    summary.value = await getJson('/api/admin/summary');
  } catch (e) {
    summaryErr.value = 'No s’ha pogut carregar el resum.';
    console.error(e);
  }
}

async function loadCharts () {
  chartsErr.value = '';
  try {
    const res = await postGraphql(GQL);
    const errs = res.errors;
    if (Array.isArray(errs) && errs.length > 0) {
      let detail = '';
      if (errs[0] && typeof errs[0].message === 'string') {
        detail = errs[0].message;
      }
      if (detail !== '') {
        chartsErr.value = 'Gràfics: ' + detail;
      } else {
        chartsErr.value = 'No s’han pogut carregar els gràfics.';
      }
      console.error('GraphQL errors', errs);
      return;
    }
    const d = res.data;
    if (!d) {
      chartsErr.value = 'Resposta GraphQL sense dades.';
      return;
    }
    const rev = d.adminDashboardRevenueByDay;
    const ord = d.adminDashboardOrdersPaidByDay;
    if (!rev || !ord || !Array.isArray(rev) || !Array.isArray(ord)) {
      chartsErr.value = 'Dades dels gràfics incompletes.';
      return;
    }
    await nextTick();
    await nextTick();
    await renderCharts(rev, ord);
  } catch (e) {
    chartsErr.value = 'Error carregant gràfics (xarxa o sessió).';
    console.error(e);
  }
}

async function renderCharts (revenuePoints, ordersPoints) {
  const mod = await import('chart.js');
  const Chart = mod.Chart;
  const registerables = mod.registerables;
  Chart.register(...registerables);

  const labels = [];
  const revData = [];
  const ordData = [];
  for (let i = 0; i < revenuePoints.length; i++) {
    labels.push(revenuePoints[i].date);
    revData.push(parseFloat(revenuePoints[i].revenue));
  }
  for (let j = 0; j < ordersPoints.length; j++) {
    ordData.push(ordersPoints[j].count);
  }

  if (revChart) {
    revChart.destroy();
    revChart = null;
  }
  if (ordChart) {
    ordChart.destroy();
    ordChart = null;
  }

  const rc = revCanvas.value;
  const oc = ordCanvas.value;
  if (!rc || !oc) {
    return;
  }

  const tickFont = { family: 'Inter, system-ui, sans-serif', size: 10 };
  const gridColor = 'rgba(229, 226, 225, 0.06)';
  const borderMuted = 'rgba(74, 71, 51, 0.35)';

  revChart = new Chart(rc, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Ingressos (EUR)',
          data: revData,
          borderColor: '#f7e628',
          backgroundColor: 'rgba(247, 230, 40, 0.14)',
          tension: 0.2,
          borderWidth: 2,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 8, right: 8, bottom: 4, left: 4 },
      },
      plugins: {
        legend: {
          labels: {
            color: '#ccc7ac',
            font: tickFont,
          },
        },
      },
      scales: {
        x: {
          ticks: { color: '#959178', maxRotation: 45, font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
        y: {
          ticks: { color: '#959178', font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
      },
    },
  });

  ordChart = new Chart(oc, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Comandes pagades',
          data: ordData,
          backgroundColor: 'rgba(210, 201, 122, 0.4)',
          borderColor: '#d2c97a',
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 8, right: 8, bottom: 4, left: 4 },
      },
      plugins: {
        legend: {
          labels: {
            color: '#ccc7ac',
            font: tickFont,
          },
        },
      },
      scales: {
        x: {
          ticks: { color: '#959178', maxRotation: 45, font: tickFont },
          grid: { display: false },
          border: { color: borderMuted },
        },
        y: {
          ticks: { color: '#959178', stepSize: 1, font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
      },
    },
  });
}

onMounted(async () => {
  await refreshSummary();
  stopPoll = setInterval(refreshSummary, pollSec * 1000);
  stopSocket = connectSocket((payload) => {
    adminDashStore.setLiveMetrics(payload);
    applyLivePayload(payload);
  });
  await nextTick();
  await nextTick();
  setTimeout(() => {
    loadCharts();
  }, 0);
});

onUnmounted(() => {
  if (stopPoll) {
    clearInterval(stopPoll);
  }
  if (typeof stopSocket === 'function') {
    stopSocket();
  }
  if (revChart) {
    revChart.destroy();
  }
  if (ordChart) {
    ordChart.destroy();
  }
});
</script>

<style scoped>
/* Tokens alineats amb la referència «Command Center» (fons #131313, accent #f7e628) */
.adm-dash {
  box-sizing: border-box;
  width: 100%;
  max-width: 120rem;
  margin: 0 auto;
  padding-bottom: 2.5rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
  --background: #131313;
  --on-surface: #e5e2e1;
  --on-surface-variant: #ccc7ac;
  --surface-container-low: #1c1b1b;
  --surface-container-high: #2a2a2a;
  --surface-container-highest: #353534;
  --outline-variant: #4a4733;
  --primary-fixed: #f7e628;
  --on-primary-container: #6e6600;
  --primary-container: #f7e628;
  --secondary: #d2c97a;
  /* Alçada del canvas Chart.js (fixa, una mica més alta que abans) */
  --adm-chart-h: 300px;
}

.adm-dash__h2 {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--on-surface-variant);
}

.adm-dash__panel {
  margin-bottom: 1.5rem;
  padding: 2rem;
  background: var(--surface-container-low);
  border: 1px solid rgba(74, 71, 51, 0.2);
  border-radius: 1rem;
}

.adm-dash__panel--click {
  display: block;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.adm-dash__panel--click:hover {
  border-color: rgba(247, 230, 40, 0.35);
  background: #201f1f;
}

.adm-dash__panel--click:focus-visible {
  outline: 2px solid var(--primary-fixed);
  outline-offset: 2px;
}

.adm-dash__kpi-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
  margin-bottom: 2rem;
}

@media (min-width: 900px) {
  .adm-dash__kpi-grid {
    grid-template-columns: repeat(12, minmax(0, 1fr));
  }

  .adm-dash__kpi--span-5 {
    grid-column: span 5;
  }

  .adm-dash__kpi--span-7 {
    grid-column: span 7;
  }

  .adm-dash__kpi--span-4 {
    grid-column: span 4;
  }

  .adm-dash__kpi--span-12 {
    grid-column: span 12;
  }
}

.adm-dash__kpi {
  position: relative;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 7.5rem;
  padding: 1.35rem 1.5rem;
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.28);
}

.adm-dash__kpi--gold {
  background: linear-gradient(145deg, #f7e628 0%, #d9c900 100%);
  border-color: rgba(54, 49, 0, 0.35);
}

.adm-dash__kpi--gold .adm-dash__kpi-label {
  color: #4e4800;
}

.adm-dash__kpi--gold .adm-dash__kpi-value {
  color: #1f1c00;
}

.adm-dash__kpi--light {
  background: linear-gradient(180deg, #f5f4ef 0%, #e8e6df 100%);
  border-color: rgba(30, 28, 24, 0.12);
}

.adm-dash__kpi--light .adm-dash__kpi-label {
  color: #3d3a33;
}

.adm-dash__kpi--light .adm-dash__kpi-value {
  color: #121110;
}

.adm-dash__kpi--ink {
  background: linear-gradient(165deg, #181818 0%, #0f0f0f 100%);
  border-color: rgba(247, 230, 40, 0.18);
}

.adm-dash__kpi--ink .adm-dash__kpi-label {
  color: #ccc7ac;
}

.adm-dash__kpi--ink .adm-dash__kpi-value {
  color: #f7e628;
}

.adm-dash__kpi--soft {
  background: #232220;
  border-color: rgba(255, 255, 255, 0.06);
}

.adm-dash__kpi--soft .adm-dash__kpi-label {
  color: #b8b39a;
}

.adm-dash__kpi--soft .adm-dash__kpi-value {
  color: #f5f3ea;
}

.adm-dash__kpi--gold-dim {
  background: linear-gradient(135deg, #2c2812 0%, #1a170a 100%);
  border-color: rgba(247, 230, 40, 0.22);
}

.adm-dash__kpi--gold-dim .adm-dash__kpi-label {
  color: #d2c97a;
}

.adm-dash__kpi--gold-dim .adm-dash__kpi-value {
  color: #f7e628;
}

.adm-dash__kpi-label {
  margin: 0 0 0.5rem;
  position: relative;
  z-index: 1;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.adm-dash__kpi-value {
  margin: 0;
  position: relative;
  z-index: 1;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: clamp(1.75rem, 3.5vw, 2.35rem);
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1;
}

.adm-dash__kpi--light .adm-dash__kpi-value {
  font-size: clamp(2.75rem, 6vw, 4rem);
}

.adm-dash__kpi--gold .adm-dash__kpi-value {
  font-size: clamp(2rem, 4vw, 2.85rem);
}

.adm-dash__chart-panel {
  display: grid;
  grid-template-rows: auto var(--adm-chart-h);
  gap: 0.85rem;
  box-sizing: border-box;
  margin-bottom: 1.5rem;
  padding: 1.5rem 1.75rem;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.28);
  background-color: #1c1b1b;
  background-image: radial-gradient(circle, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 20px 20px;
}

.adm-dash__chart-title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--on-surface-variant);
}

/* Alçada fixa: Chart.js + `1fr` / minmax feia créixer el canvas a cada redibuixat */
.adm-dash__chart-area {
  position: relative;
  width: 100%;
  height: var(--adm-chart-h);
  min-height: var(--adm-chart-h);
  max-height: var(--adm-chart-h);
  overflow: hidden;
}

.adm-dash__chart-canvas {
  display: block;
  width: 100%;
  height: var(--adm-chart-h);
  max-height: var(--adm-chart-h);
}

.adm-dash__log-list {
  list-style: none;
  margin: 0.75rem 0 0;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: 0.7rem;
}

.adm-dash__log-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid rgba(74, 71, 51, 0.35);
  color: #c8c4b0;
}

.adm-dash__log-main {
  color: var(--on-surface);
  font-weight: 600;
}

.adm-dash__log-ip {
  font-size: 0.68rem;
  color: var(--on-surface-variant);
}

.adm-dash__log-sum {
  color: var(--on-surface);
}

.adm-dash__muted {
  font-size: 0.75rem;
  color: var(--on-surface-variant);
  margin: 0.75rem 0 0;
  letter-spacing: 0.04em;
}

.adm-dash__err {
  color: #ffb4ab;
}

.adm-dash__err--charts {
  margin: 0 0 1rem;
}

.adm-dash ::selection {
  background: var(--primary-container);
  color: var(--on-primary-container);
}
</style>
