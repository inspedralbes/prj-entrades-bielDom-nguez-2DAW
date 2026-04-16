<template>
  <div class="adm-dash__kpi-grid">
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
</template>

<script setup>
defineProps({
  summary: {
    type: Object,
    required: true,
  },
});
</script>

<style scoped>
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
</style>
