<template>
  <div class="adm-dash-charts-root">
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
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { fetchAdminDashboardChartData, mountAdminDashboardCharts } from '~/composables/useAdminDashboardCharts';

const props = defineProps({
  postGraphql: {
    type: Function,
    required: true,
  },
});

const revCanvas = ref(null);
const ordCanvas = ref(null);
const chartsErr = ref('');
let destroyCharts = null;

async function loadCharts () {
  chartsErr.value = '';
  try {
    const result = await fetchAdminDashboardChartData(props.postGraphql);
    if (result.error && result.error !== '') {
      chartsErr.value = result.error;
      return;
    }
    await nextTick();
    await nextTick();
    const rc = revCanvas.value;
    const oc = ordCanvas.value;
    if (!rc || !oc) {
      return;
    }
    if (destroyCharts) {
      destroyCharts();
      destroyCharts = null;
    }
    const mounted = await mountAdminDashboardCharts(
      rc,
      oc,
      result.revenuePoints,
      result.ordersPoints,
    );
    destroyCharts = mounted.destroy;
  } catch (e) {
    chartsErr.value = 'Error carregant gràfics (xarxa o sessió).';
    console.error(e);
  }
}

onMounted(() => {
  setTimeout(() => {
    loadCharts();
  }, 0);
});

onUnmounted(() => {
  if (typeof destroyCharts === 'function') {
    destroyCharts();
    destroyCharts = null;
  }
});
</script>

<style scoped>
.adm-dash-charts-root {
  --adm-chart-h: 300px;
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
  color: #ccc7ac;
}

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

.adm-dash__err {
  color: #ffb4ab;
}

.adm-dash__err--charts {
  margin: 0 0 1rem;
}
</style>
