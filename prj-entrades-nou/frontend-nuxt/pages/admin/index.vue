<template>
  <div class="adm-dash">
    <h1 class="adm-dash__h1">Dashboard</h1>

    <section class="adm-dash__panel">
      <h2 class="adm-dash__h2">Resum (API)</h2>
      <p v-if="summaryErr" class="adm-dash__err">{{ summaryErr }}</p>
      <pre v-else class="adm-dash__pre">{{ summaryText }}</pre>
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
import { resolvePublicSocketUrl } from '~/utils/apiBase';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const config = useRuntimeConfig();
const { getJson } = useAuthorizedApi();
const { connectSocket } = useAdminDashboard();

const summary = ref(null);
const summaryErr = ref('');
const lastMetrics = ref(null);
const pollSec = 12;
let stopPoll;
let stopSocket;

const socketUrl = computed(() => resolvePublicSocketUrl(config.public.socketUrl));

const summaryText = computed(() =>
  summary.value ? JSON.stringify(summary.value, null, 2) : '—',
);

const metricsText = computed(() =>
  lastMetrics.value ? JSON.stringify(lastMetrics.value, null, 2) : '(esperant admin:metrics…)',
);

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
.adm-dash__pre {
  margin: 0;
  font-size: 0.8rem;
  overflow: auto;
  color: #ddd;
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
