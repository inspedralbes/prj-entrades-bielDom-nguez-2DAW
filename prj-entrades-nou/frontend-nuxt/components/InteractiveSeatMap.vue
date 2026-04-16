<template>
  <div
    class="ism-root"
    :class="{ 'ism-root--admin': readOnly }"
  >
    <div class="ism-stage" aria-hidden="true">
      <span class="ism-stage__label">Davant · escenari</span>
    </div>

    <div ref="mapRoot" class="ism-map-root" />
  </div>
</template>

<script setup>
import { ref, toRef } from 'vue';
import { useCinemaD3SeatMap } from '~/composables/useCinemaD3SeatMap';

const props = defineProps({
  eventId: {
    type: String,
    required: true,
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['seat-click']);

const mapRoot = ref(null);
const readOnlyRef = toRef(props, 'readOnly');

useCinemaD3SeatMap(mapRoot, readOnlyRef, (payload) => {
  emit('seat-click', payload);
});
</script>

<style scoped>
.ism-root {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  width: 100%;
  flex: 1 1 auto;
  min-height: 0;
  gap: 0.55rem;
}

.ism-root--admin {
  padding-top: 0.2rem;
}

/* Mateixa línia visual que .event-info-bar (TR3) */
.ism-stage {
  flex-shrink: 0;
  box-sizing: border-box;
  width: 100%;
  padding: 0.45rem 0.75rem;
  margin: 0;
  min-height: 0;
  background: #1a1a1a;
  border: 1px solid rgba(74, 71, 51, 0.45);
  border-radius: 8px;
}

.ism-stage__label {
  display: block;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(245, 245, 245, 0.55);
  text-align: center;
}

.ism-map-root {
  position: relative;
  z-index: 2;
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.ism-svg {
  display: block;
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
}

:deep(.ism-labels) {
  pointer-events: none;
}

:deep(.ism-col-label),
:deep(.ism-row-label) {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 11px;
  font-weight: 600;
  fill: rgba(245, 245, 245, 0.42);
}

:deep(.ism-aisle-band) {
  fill: rgba(255, 255, 255, 0.03);
  stroke: none;
}

:deep(.ism-aisle-edge) {
  stroke: rgba(247, 230, 40, 0.14);
  stroke-dasharray: 4 4;
  stroke-width: 1;
  fill: none;
}

.ism-root--admin :deep(.ism-seat-hit) {
  cursor: default;
}

:deep(.ism-seat-hit) {
  cursor: pointer;
  fill: rgba(0, 0, 0, 0.02);
  stroke: none;
}

:deep(.ism-seat-hit.seat-sold) {
  cursor: not-allowed;
}

:deep(.ism-seat-vis) {
  transition:
    fill 0.15s ease,
    stroke 0.15s ease,
    filter 0.15s ease;
}

:deep(.ism-seat-vis.seat-available) {
  fill: none;
  stroke: #f7e628;
  stroke-width: 1.75;
}

:deep(.ism-seat-vis.seat-available:hover) {
  fill: rgba(247, 230, 40, 0.18);
}

:deep(.ism-seat-vis.seat-held-other:hover) {
  fill: rgba(255, 255, 255, 0.04);
}

:deep(.ism-seat-vis.seat-sold) {
  fill: #353534;
  stroke: none;
  cursor: not-allowed;
}

:deep(.ism-seat-vis.seat-picked),
:deep(.ism-seat-vis.seat-held) {
  fill: #f7e628;
  stroke: none;
  filter: drop-shadow(0 0 8px rgba(247, 230, 40, 0.55));
}

:deep(.ism-seat-vis.seat-held-other) {
  fill: none;
  stroke: rgba(247, 230, 40, 0.35);
  stroke-width: 1.4;
  stroke-dasharray: 3 3;
}
</style>
