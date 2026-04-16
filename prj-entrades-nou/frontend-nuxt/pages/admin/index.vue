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
    <AdminDashboardKpiGrid v-else-if="summary" :summary="summary" />

    <ClientOnly>
      <AdminDashboardChartsClient :post-graphql="postGraphql" />
    </ClientOnly>

    <AdminDashboardLogsPreview v-if="summary" :rows="recentLogsPreview" />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AdminDashboardChartsClient from '~/components/admin/AdminDashboardCharts.client.vue';
import AdminDashboardKpiGrid from '~/components/admin/AdminDashboardKpiGrid.vue';
import AdminDashboardLogsPreview from '~/components/admin/AdminDashboardLogsPreview.vue';
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
const pollSec = 12;
let stopPoll;
let stopSocket;

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

onMounted(async () => {
  await refreshSummary();
  stopPoll = setInterval(refreshSummary, pollSec * 1000);
  stopSocket = connectSocket((payload) => {
    adminDashStore.setLiveMetrics(payload);
    applyLivePayload(payload);
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
}

.adm-dash__err {
  color: #ffb4ab;
}

.adm-dash ::selection {
  background: var(--primary-container);
  color: var(--on-primary-container);
}
</style>
