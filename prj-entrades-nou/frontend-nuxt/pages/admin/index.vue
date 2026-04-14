<template>
  <div class="adm-dash">
    <h1 class="adm-dash__h1">Dashboard</h1>

    <section class="adm-dash__panel">
      <h2 class="adm-dash__h2">Indicadors</h2>
      <p v-if="summaryErr" class="adm-dash__err">{{ summaryErr }}</p>
      <div v-else-if="summary" class="adm-dash__kpi-grid">
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Ingressos avui (EUR)</p>
          <p class="adm-dash__kpi-value">{{ summary.revenue_today }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Comandes pagades avui</p>
          <p class="adm-dash__kpi-value">{{ summary.orders_paid_today }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Pendents de pagament</p>
          <p class="adm-dash__kpi-value">{{ summary.pending_payment_count }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Usuaris en línia</p>
          <p class="adm-dash__kpi-value">{{ summary.online_users }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Esdeveniments (catàleg)</p>
          <p class="adm-dash__kpi-value">{{ summary.events_total }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Comandes pagades (total)</p>
          <p class="adm-dash__kpi-value">{{ summary.orders_paid }}</p>
        </div>
        <div class="adm-dash__kpi">
          <p class="adm-dash__kpi-label">Tiquets venuts (històric)</p>
          <p class="adm-dash__kpi-value">{{ summary.tickets_sold_total }}</p>
        </div>
      </div>
      <p v-if="summary && summary.generated_at" class="adm-dash__muted">
        Darrera actualització: {{ summary.generated_at }}
      </p>
    </section>

    <section v-if="summary" class="adm-dash__panel">
      <h2 class="adm-dash__h2">Alertes sincronització Ticketmaster</h2>
      <ul v-if="syncAlertLines.length > 0" class="adm-dash__alert-list">
        <li v-for="(line, idx) in syncAlertLines" :key="'a'+idx">{{ line }}</li>
      </ul>
      <p v-else class="adm-dash__muted">Sense alertes de l’última sincronització Discovery.</p>
    </section>

    <ClientOnly>
      <section class="adm-dash__panel">
        <h2 class="adm-dash__h2">Gràfics (últims 30 dies)</h2>
        <p v-if="chartsErr" class="adm-dash__err">{{ chartsErr }}</p>
        <div class="adm-dash__charts">
          <div class="adm-dash__chart-box">
            <p class="adm-dash__chart-title">Ingressos per dia (EUR)</p>
            <canvas ref="revCanvas" height="220" />
          </div>
          <div class="adm-dash__chart-box">
            <p class="adm-dash__chart-title">Comandes pagades per dia</p>
            <canvas ref="ordCanvas" height="220" />
          </div>
        </div>
      </section>
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

    <p class="adm-dash__muted">Actualització resum cada {{ pollSec }}s · temps real via Socket.IO</p>
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

const syncAlertLines = computed(() => {
  const out = [];
  if (!summary.value) {
    return out;
  }
  const alerts = summary.value.sync_alerts;
  if (!alerts || !Array.isArray(alerts)) {
    return out;
  }
  for (let i = 0; i < alerts.length; i++) {
    const a = alerts[i];
    if (!a || typeof a.message !== 'string') {
      continue;
    }
    out.push(a.message);
  }
  return out;
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

  revChart = new Chart(rc, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Ingressos (EUR)',
          data: revData,
          borderColor: '#ff0055',
          backgroundColor: 'rgba(255,0,85,0.15)',
          tension: 0.2,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { labels: { color: '#ccc' } },
      },
      scales: {
        x: { ticks: { color: '#888', maxRotation: 45 } },
        y: { ticks: { color: '#888' } },
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
          backgroundColor: 'rgba(46,213,115,0.45)',
          borderColor: '#2ed573',
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { labels: { color: '#ccc' } },
      },
      scales: {
        x: { ticks: { color: '#888', maxRotation: 45 } },
        y: { ticks: { color: '#888', stepSize: 1 } },
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
.adm-dash {
  max-width: 56rem;
}
.adm-dash__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-dash__h2 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-dash__panel {
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-dash__panel--click {
  display: block;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
}
.adm-dash__panel--click:hover {
  border-color: #444;
}
.adm-dash__panel--click:focus-visible {
  outline: 1px solid #ff0055;
}
.adm-dash__kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  gap: 0.75rem;
}
.adm-dash__kpi {
  padding: 0.65rem 0.75rem;
  background: #161616;
  border-radius: 6px;
  border: 1px solid #2a2a2a;
}
.adm-dash__kpi-label {
  margin: 0 0 0.25rem;
  font-size: 0.75rem;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.adm-dash__kpi-value {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #f0f0f0;
}
.adm-dash__charts {
  display: grid;
  gap: 1rem;
}
@media (min-width: 768px) {
  .adm-dash__charts {
    grid-template-columns: 1fr 1fr;
  }
}
.adm-dash__chart-box {
  background: #0d0d0d;
  border-radius: 6px;
  padding: 0.5rem;
}
.adm-dash__chart-title {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  color: #aaa;
}
.adm-dash__alert-list {
  margin: 0.25rem 0 0;
  padding-left: 1.1rem;
  color: #ffb347;
  font-size: 0.9rem;
}
.adm-dash__log-list {
  list-style: none;
  margin: 0.5rem 0 0;
  padding: 0;
}
.adm-dash__log-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #2a2a2a;
  font-size: 0.88rem;
  color: #ddd;
}
.adm-dash__log-main {
  color: #e0e0e0;
}
.adm-dash__log-ip {
  font-size: 0.8rem;
  color: #888;
}
.adm-dash__log-sum {
  color: #bbb;
}
.adm-dash__muted {
  font-size: 0.8rem;
  color: #777;
  margin: 0.5rem 0 0;
}
.adm-dash__err {
  color: #ff6b6b;
}
</style>
