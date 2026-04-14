<template>
  <div class="adm-dash">
    <h1 class="adm-dash__h1">Dashboard</h1>

    <section class="adm-dash__panel">
      <h2 class="adm-dash__h2">Resum (API)</h2>
      <p v-if="summaryErr" class="adm-dash__err">{{ summaryErr }}</p>
      <template v-else>
        <dl v-if="summary" class="adm-dash__dl">
          <div class="adm-dash__row">
            <dt>Ingressos avui (EUR)</dt>
            <dd>{{ summary.revenue_today }}</dd>
          </div>
          <div class="adm-dash__row">
            <dt>Comandes pending_payment</dt>
            <dd>{{ summary.pending_payment_count }}</dd>
          </div>
          <div class="adm-dash__row">
            <dt>Usuaris en línia (API + ping)</dt>
            <dd>{{ summary.online_users }}</dd>
          </div>
          <div class="adm-dash__row">
            <dt>Esdeveniments totals</dt>
            <dd>{{ summary.events_total }}</dd>
          </div>
          <div class="adm-dash__row">
            <dt>Comandes pagades (tot el temps)</dt>
            <dd>{{ summary.orders_paid }}</dd>
          </div>
        </dl>
        <div v-if="summary && syncAlertLines.length > 0" class="adm-dash__alerts">
          <p class="adm-dash__h2">Alertes sincronització TM</p>
          <ul class="adm-dash__alert-list">
            <li v-for="(line, idx) in syncAlertLines" :key="idx">{{ line }}</li>
          </ul>
        </div>
        <p v-if="summary && syncAlertLines.length === 0" class="adm-dash__muted">
          Sense alertes de l’última sincronització Discovery.
        </p>
        <pre class="adm-dash__pre adm-dash__pre--json">{{ summaryText }}</pre>
      </template>
      <p class="adm-dash__muted">Actualització cada {{ pollSec }}s · crida <code>GET /api/admin/summary</code></p>
    </section>

    <section class="adm-dash__panel">
      <h2 class="adm-dash__h2">Temps real (Socket.IO)</h2>
      <p v-if="!socketUrl" class="adm-dash__muted">Configura <code>NUXT_PUBLIC_SOCKET_URL</code>.</p>
      <pre v-else class="adm-dash__pre">{{ metricsText }}</pre>
    </section>

    <section class="adm-dash__panel">
      <h2 class="adm-dash__h2">Mini mapa d’estats de seient</h2>
      <p class="adm-dash__muted">
        Esborrany: en producció es mostraran estats des de l’API per esdeveniment seleccionat.
      </p>
      <div class="adm-dash__mini-map" aria-hidden="true">
        <span class="dot dot--free" /><span class="dot dot--hold" /><span class="dot dot--sold" />
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useAdminDashboard } from '~/composables/useAdminDashboard';
import { useAdminDashboardStore } from '~/stores/adminDashboard';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const config = useRuntimeConfig();
const { getJson } = useAuthorizedApi();
const { connectSocket } = useAdminDashboard();
const adminDashStore = useAdminDashboardStore();

const summary = ref(null);
const summaryErr = ref('');
const lastMetrics = ref(null);
const pollSec = 12;
let stopPoll;
let stopSocket;

const socketUrl = computed(() => config.public.socketUrl || '');

const summaryText = computed(() => {
  if (!summary.value) {
    return '—';
  }
  return JSON.stringify(summary.value, null, 2);
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

const metricsText = computed(() => {
  if (!lastMetrics.value) {
    return '(esperant admin:metrics…)';
  }
  return JSON.stringify(lastMetrics.value, null, 2);
});

async function refreshSummary () {
  summaryErr.value = '';
  try {
    summary.value = await getJson('/api/admin/summary');
  } catch (e) {
    summaryErr.value = 'No s’ha pogut carregar el resum.';
    console.error(e);
  }
}

onMounted(() => {
  refreshSummary();
  stopPoll = setInterval(refreshSummary, pollSec * 1000);
  stopSocket = connectSocket((payload) => {
    lastMetrics.value = payload;
    adminDashStore.setLiveMetrics(payload);
  });
});

onUnmounted(() => {
  if (stopPoll) {
    clearInterval(stopPoll);
  }
  if (typeof stopSocket === 'function') {
    stopSocket();
  }
});
</script>

<style scoped>
.adm-dash {
  max-width: 52rem;
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
.adm-dash__dl {
  margin: 0 0 0.75rem;
  display: grid;
  gap: 0.35rem;
}
.adm-dash__row {
  display: flex;
  gap: 0.75rem;
  font-size: 0.9rem;
  color: #e0e0e0;
}
.adm-dash__row dt {
  min-width: 12rem;
  color: #888;
}
.adm-dash__row dd {
  margin: 0;
}
.adm-dash__alerts {
  margin-bottom: 0.75rem;
}
.adm-dash__alert-list {
  margin: 0.25rem 0 0;
  padding-left: 1.1rem;
  color: #ffb347;
  font-size: 0.85rem;
}
.adm-dash__pre {
  margin: 0;
  font-size: 0.8rem;
  overflow: auto;
  color: #ddd;
}
.adm-dash__pre--json {
  margin-top: 0.75rem;
  opacity: 0.85;
}
.adm-dash__muted {
  font-size: 0.8rem;
  color: #777;
  margin: 0.5rem 0 0;
}
.adm-dash__err {
  color: #ff6b6b;
}
.adm-dash__mini-map {
  display: flex;
  gap: 0.35rem;
  margin-top: 0.5rem;
}
.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}
.dot--free {
  background: #2ed573;
}
.dot--hold {
  background: #ffa502;
}
.dot--sold {
  background: #ff4757;
}
</style>
