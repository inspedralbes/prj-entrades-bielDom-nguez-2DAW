<template>
  <div class="ism-wrap" :class="{ 'ism-wrap--readonly': readOnly }">
    <div class="ism-legend">
      <div class="ism-legend__item">
        <span class="ism-swatch ism-swatch--free" />
        <span>Libre</span>
      </div>
      <div class="ism-legend__item">
        <span class="ism-swatch ism-swatch--held-other" />
        <span>Reservat</span>
      </div>
      <div class="ism-legend__item">
        <span class="ism-swatch ism-swatch--sold" />
        <span>Ocupado</span>
      </div>
    </div>

    <div class="ism-stage" aria-hidden="true">
      <span class="ism-stage__deco">🎭</span>
      <span class="ism-stage__label">ESCENARIO</span>
      <span class="ism-stage__deco">🎭</span>
    </div>

    <div ref="mapRoot" class="ism-map-root" />
  </div>
</template>

<script setup>
import { select } from 'd3-selection';
import { storeToRefs } from 'pinia';
import { computed, onMounted, ref } from 'vue';
import { allSeatCells, columnsForRow } from '~/utils/cinemaVenueLayout';
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

const CELL = 10;
const COLS = 39;
const ROWS = 18;
const TOP_PAD = 16;

const svgWidth = computed(() => {
  return (COLS + 3) * CELL;
});

const svgHeight = computed(() => {
  return TOP_PAD + ROWS * CELL + 8;
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
    .attr('width', '100%')
    /* SVG no admet height="auto" (error al navegador); mateixa alçada lògica que el viewBox */
    .attr('height', h);

  const g = svg.append('g').attr('class', 'ism-grid').attr('transform', `translate(0,${TOP_PAD})`);

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

    const rect = g
      .append('rect')
      .attr('x', x)
      .attr('y', y)
      .attr('width', CELL - 1)
      .attr('height', CELL - 1)
      .attr('rx', 1)
      .attr('data-seat-id', seatId)
      .attr('class', `ism-seat ${cls}`);
    if (!props.readOnly) {
      rect.on('click', () => {
        emit('seat-click', { seatId, row, col });
      });
    }

    if (cls === 'seat-sold') {
      g.append('text')
        .attr('x', x + (CELL - 1) / 2)
        .attr('y', y + (CELL - 1) * 0.72)
        .attr('text-anchor', 'middle')
        .attr('class', 'ism-x-mark')
        .text('×');
    }
  }

  const labelG = svg.append('g').attr('class', 'ism-row-labels').attr('transform', `translate(0,${TOP_PAD})`);
  for (let row = 1; row <= ROWS; row += 1) {
    const y = (row - 1) * CELL + CELL * 0.65;
    const x = COLS * CELL + CELL * 0.4;
    labelG
      .append('text')
      .attr('x', x)
      .attr('y', y)
      .attr('class', 'ism-row-num')
      .text(String(row));
  }

  const topNums = svg.append('g').attr('class', 'ism-col-labels');
  const row1cols = columnsForRow(1);
  const m = row1cols.length;
  for (let j = 0; j < m; j += 1) {
    const col = row1cols[j];
    const vx = visualColIndex(col);
    const x = vx * CELL + 2;
    const y = TOP_PAD * 0.55;
    topNums
      .append('text')
      .attr('x', x)
      .attr('y', y)
      .attr('class', 'ism-col-num')
      .text(String(col));
  }

  const aisleG = svg.append('g').attr('class', 'ism-aisle').attr('transform', `translate(0,${TOP_PAD})`);
  const ax = 19.5 * CELL;
  aisleG
    .append('line')
    .attr('x1', ax)
    .attr('y1', 0)
    .attr('x2', ax)
    .attr('y2', ROWS * CELL)
    .attr('stroke', 'rgba(255,255,255,0.15)')
    .attr('stroke-dasharray', '3 3');
}

onMounted(() => {
  render();
  // Pinia: $subscribe notifica qualsevol mutació de l’estat; el watch amb storeToRefs
  // i objectes reemplaçats de vegades no tornava a pintar el D3.
  seatmapStore.$subscribe(() => {
    render();
  });
});
</script>

<style scoped>
.ism-wrap {
  background: rgba(135, 180, 220, 0.12);
  border-radius: 12px;
  padding: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.ism-wrap--readonly :deep(.ism-seat) {
  cursor: default;
}

.ism-legend {
  display: flex;
  gap: 1rem;
  margin-bottom: 10px;
  font-size: 0.8rem;
  color: #ccc;
}

.ism-legend__item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ism-swatch {
  width: 14px;
  height: 14px;
  border-radius: 2px;
  display: inline-block;
  border: 1px solid #444;
}

.ism-swatch--free {
  background: #d8d8d8;
}

.ism-swatch--held-other {
  background: #e8943a;
}

.ism-swatch--sold {
  background: #8b5e3c;
  position: relative;
}

.ism-swatch--sold::after {
  content: '✕';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  color: #111;
}

.ism-stage {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: linear-gradient(180deg, #a8d4f0 0%, #7eb8e0 100%);
  color: #1a3a52;
  font-weight: 800;
  letter-spacing: 0.12em;
  padding: 8px 12px;
  border-radius: 8px;
  margin-bottom: 10px;
  font-size: 0.85rem;
}

.ism-map-root {
  width: 100%;
  overflow-x: auto;
}

.ism-svg {
  display: block;
  min-width: 320px;
}

:deep(.ism-seat) {
  cursor: pointer;
  stroke: rgba(0, 0, 0, 0.25);
  stroke-width: 0.5;
}

:deep(.seat-available) {
  fill: #d0d0d0;
}

:deep(.seat-sold) {
  fill: #8b5e3c;
}

:deep(.seat-sold)::after {
  content: '';
}

:deep(.seat-held-other) {
  fill: #e8943a;
  stroke: rgba(0, 0, 0, 0.45);
  stroke-width: 1;
}

:deep(.seat-held) {
  fill: #3b82f6;
}

:deep(.seat-picked) {
  fill: #22c55e;
  stroke: #fff;
}

.ism-row-num {
  fill: #888;
  font-size: 7px;
}

.ism-col-num {
  fill: #888;
  font-size: 6px;
}

:deep(.ism-x-mark) {
  fill: #1a0f08;
  font-size: 8px;
  pointer-events: none;
}
</style>
