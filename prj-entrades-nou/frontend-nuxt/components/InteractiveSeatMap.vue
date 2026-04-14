<template>
  <div
    class="ism-root"
    :class="{ 'ism-root--admin': readOnly }"
  >
    <div class="ism-stage" aria-hidden="true">
      <span class="ism-stage__label">Escenari</span>
      <div class="ism-stage__glow" />
    </div>

    <div ref="mapRoot" class="ism-map-root" />
  </div>
</template>

<script setup>
import { select } from 'd3-selection';
import { storeToRefs } from 'pinia';
import { computed, onMounted, ref } from 'vue';
import { allSeatCells } from '~/utils/cinemaVenueLayout';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';

const props = defineProps({
  eventId: {
    type: String,
    required: true,
  },
  /** Mode panell admin: sense clic ni reserva. */
  readOnly: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['seat-click']);

const mapRoot = ref(null);
const seatmapStore = useInteractiveSeatmapStore();
const { soldBySeatId, heldBySeatId, selectedSeatIds, currentUserId } = storeToRefs(seatmapStore);

/* Cel·la més gran → mapa més llegible sense números de fila/columna */
const CELL = 18;
const COLS = 39;
const ROWS = 18;
const TOP_PAD = 0;
const SEAT_R = 7.2;

const svgWidth = computed(() => {
  return COLS * CELL + 8;
});

const svgHeight = computed(() => {
  return TOP_PAD + ROWS * CELL + 12;
});

function visualColIndex (col) {
  return col - 1;
}

function classForSeat (seatId) {
  const sid = String(seatId);
  if (soldBySeatId.value[sid]) {
    return 'seat-sold';
  }
  const heldUid = heldBySeatId.value[sid];
  if (heldUid !== undefined && heldUid !== null && heldUid !== '') {
    const mine = currentUserId.value !== null && String(heldUid) === String(currentUserId.value);
    if (mine) {
      return 'seat-held';
    }
    return 'seat-held-other';
  }
  const sel = selectedSeatIds.value.indexOf(sid) >= 0;
  if (sel) {
    return 'seat-picked';
  }
  return 'seat-available';
}

function render () {
  const root = mapRoot.value;
  if (!root) {
    return;
  }

  const cells = allSeatCells();
  const w = svgWidth.value;
  const h = svgHeight.value;

  select(root).selectAll('svg').remove();
  const svg = select(root)
    .append('svg')
    .attr('class', 'ism-svg')
    .attr('viewBox', `0 0 ${w} ${h}`)
    .attr('preserveAspectRatio', 'xMidYMid meet')
    .attr('width', '100%');

  const g = svg.append('g').attr('class', 'ism-grid').attr('transform', 'translate(4,4)');

  const n = cells.length;
  for (let i = 0; i < n; i += 1) {
    const cell = cells[i];
    const row = cell.row;
    const col = cell.col;
    const seatId = cell.seatId;
    const vx = visualColIndex(col);
    const vy = row - 1;
    const x = vx * CELL;
    const y = vy * CELL;
    const cls = classForSeat(seatId);
    const cx = x + CELL / 2;
    const cy = y + CELL / 2;

    const circle = g
      .append('circle')
      .attr('cx', cx)
      .attr('cy', cy)
      .attr('r', SEAT_R)
      .attr('data-seat-id', seatId)
      .attr('class', `ism-seat ${cls}`);
    if (!props.readOnly) {
      circle.on('click', () => {
        emit('seat-click', { seatId, row, col });
      });
    }
  }

  const aisleG = svg.append('g').attr('class', 'ism-aisle').attr('transform', 'translate(4,4)');
  const ax = 19.5 * CELL;
  aisleG
    .append('line')
    .attr('x1', ax)
    .attr('y1', 0)
    .attr('x2', ax)
    .attr('y2', ROWS * CELL)
    .attr('class', 'ism-aisle-line');
}

onMounted(() => {
  render();
  seatmapStore.$subscribe(() => {
    render();
  });
});
</script>

<style scoped>
/* Referència TR3: fons #131313, seients circulars, accent #f7e628, sense llegenda ni cap extra al voltant del SVG */
.ism-root {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  gap: 1rem;
}

.ism-root--admin {
  padding-top: 0.25rem;
}

.ism-stage {
  position: relative;
  box-sizing: border-box;
  width: 100%;
  max-width: none;
  min-height: 3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.25rem;
  background: #0e0e0e;
  border-top: 4px solid #f7e628;
  border-radius: 0 0 0.75rem 0.75rem;
}

.ism-stage__label {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.45em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.38);
}

.ism-stage__glow {
  position: absolute;
  bottom: -0.5rem;
  left: 50%;
  transform: translateX(-50%);
  width: 8rem;
  height: 2px;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(247, 230, 40, 0.22),
    transparent
  );
  pointer-events: none;
}

.ism-map-root {
  width: 100%;
  max-width: none;
  overflow-x: auto;
  overflow-y: visible;
}

.ism-svg {
  display: block;
  width: 100%;
  max-width: 100%;
  height: auto;
}

.ism-root--admin :deep(.ism-seat) {
  cursor: default;
}

:deep(.ism-seat) {
  cursor: pointer;
  transition:
    fill 0.15s ease,
    stroke 0.15s ease,
    filter 0.15s ease;
}

/* Disponible: vora groga (referència) */
:deep(.seat-available) {
  fill: none;
  stroke: #f7e628;
  stroke-width: 1.5;
}

:deep(.seat-available:hover) {
  fill: rgba(247, 230, 40, 0.18);
}

/* Seleccionat / hold propi: ple groc + brillantor */
:deep(.seat-picked),
:deep(.seat-held) {
  fill: #f7e628;
  stroke: none;
  filter: drop-shadow(0 0 8px rgba(247, 230, 40, 0.55));
}

/* Reservat per un altre */
:deep(.seat-held-other) {
  fill: none;
  stroke: rgba(247, 230, 40, 0.35);
  stroke-width: 1.4;
  stroke-dasharray: 3 3;
}

:deep(.seat-held-other:hover) {
  fill: rgba(255, 255, 255, 0.04);
}

/* Venut / ocupat: gris pla (surface-container-highest) */
:deep(.seat-sold) {
  fill: #353534;
  stroke: none;
  cursor: not-allowed;
}

:deep(.ism-aisle-line) {
  stroke: rgba(255, 255, 255, 0.08);
  stroke-dasharray: 3 3;
  stroke-width: 1;
}
</style>
