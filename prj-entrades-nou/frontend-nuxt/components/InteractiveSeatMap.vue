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
import { select } from 'd3-selection';
import { storeToRefs } from 'pinia';
import {
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
} from 'vue';
import {
  AISLE_AFTER_COL,
  allSeatCells,
  CINEMA_VENUE_COLS,
  CINEMA_VENUE_ROWS,
  gridSeatsHeight,
  gridSeatsWidth,
  seatCenterX,
} from '~/utils/cinemaVenueLayout';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';

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
const seatmapStore = useInteractiveSeatmapStore();
const { soldBySeatId, heldBySeatId, selectedSeatIds, currentUserId } = storeToRefs(seatmapStore);

const LABEL_TOP = 28;
const LABEL_LEFT = 32;
const EDGE_PAD = 6;
const INNER = 4;
/** Passadís en proporció a la separació horitzontal entre columnes. */
const AISLE_FACTOR = 0.9;
/** Columnes una mica més separades que les files. */
const COL_VS_ROW = 1.38;

const layoutCellX = ref(26);
const layoutCellY = ref(22);
const layoutAisleW = ref(22);
const layoutSeatR = ref(11);

let resizeObserver = null;
let lastEmitTs = 0;

function computeMetrics () {
  const el = mapRoot.value;
  if (!el) {
    return;
  }
  const cw = el.clientWidth;
  const ch = el.clientHeight;
  if (cw < 48 || ch < 48) {
    return;
  }
  const availW = cw - EDGE_PAD * 2 - LABEL_LEFT;
  const availH = ch - EDGE_PAD * 2 - LABEL_TOP;
  if (availW < 80 || availH < 80) {
    return;
  }

  let cellY = Math.floor((availH - 6) / (CINEMA_VENUE_ROWS + 0.2));
  if (cellY < 12) {
    cellY = 12;
  }

  let cellX = Math.floor(cellY * COL_VS_ROW);
  let aisleW = Math.floor(cellX * AISLE_FACTOR);
  if (aisleW < 10) {
    aisleW = 10;
  }

  const gridW = gridSeatsWidth(cellX, aisleW);
  if (gridW > availW - 4) {
    cellX = Math.floor((availW - 4) / (CINEMA_VENUE_COLS + AISLE_FACTOR));
    if (cellX < 12) {
      cellX = 12;
    }
    aisleW = Math.floor(cellX * AISLE_FACTOR);
    cellY = Math.min(cellY, Math.floor(cellX / COL_VS_ROW * 1.05));
  }

  const gridH = gridSeatsHeight(cellY);
  if (gridH > availH - 4) {
    cellY = Math.floor((availH - 4) / (CINEMA_VENUE_ROWS + 0.2));
    if (cellY < 12) {
      cellY = 12;
    }
    cellX = Math.floor(cellY * COL_VS_ROW);
    aisleW = Math.floor(cellX * AISLE_FACTOR);
  }

  const seatR = Math.max(5.5, Math.min(cellX, cellY) * 0.34);

  layoutCellX.value = cellX;
  layoutCellY.value = cellY;
  layoutAisleW.value = aisleW;
  layoutSeatR.value = seatR;
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

function emitSeatIfNeeded (seatId, row, col) {
  const now = typeof performance !== 'undefined' ? performance.now() : Date.now();
  if (now - lastEmitTs < 45) {
    return;
  }
  lastEmitTs = now;
  emit('seat-click', { seatId, row, col });
}

function bindSeatActivate (node, seatId, row, col) {
  select(node).on('pointerup', (ev) => {
    ev.stopPropagation();
    if (props.readOnly) {
      return;
    }
    if (ev.pointerType === 'mouse' && ev.button !== 0) {
      return;
    }
    emitSeatIfNeeded(seatId, row, col);
  });
}

function render () {
  const root = mapRoot.value;
  if (!root) {
    return;
  }

  const cellX = layoutCellX.value;
  const cellY = layoutCellY.value;
  const aisleW = layoutAisleW.value;
  const SEAT_R = layoutSeatR.value;
  const cells = allSeatCells();

  const gridW = gridSeatsWidth(cellX, aisleW);
  const gridH = gridSeatsHeight(cellY);
  const w = EDGE_PAD * 2 + LABEL_LEFT + gridW + INNER;
  const h = EDGE_PAD * 2 + LABEL_TOP + gridH + INNER;

  select(root).selectAll('svg').remove();
  const svg = select(root)
    .append('svg')
    .attr('class', 'ism-svg')
    .attr('width', w)
    .attr('height', h)
    .attr('viewBox', `0 0 ${w} ${h}`)
    .attr('preserveAspectRatio', 'xMidYMid meet');

  const ox = EDGE_PAD + LABEL_LEFT;
  const oy = EDGE_PAD + LABEL_TOP;
  const g = svg.append('g').attr('class', 'ism-grid').attr('transform', `translate(${ox},${oy})`);

  const aisleX0 = AISLE_AFTER_COL * cellX;
  const aisleX1 = AISLE_AFTER_COL * cellX + aisleW;
  g
    .append('rect')
    .attr('x', aisleX0)
    .attr('y', 0)
    .attr('width', aisleW)
    .attr('height', gridH)
    .attr('class', 'ism-aisle-band');
  g
    .append('line')
    .attr('x1', aisleX0)
    .attr('y1', 0)
    .attr('x2', aisleX0)
    .attr('y2', gridH)
    .attr('class', 'ism-aisle-edge');
  g
    .append('line')
    .attr('x1', aisleX1)
    .attr('y1', 0)
    .attr('x2', aisleX1)
    .attr('y2', gridH)
    .attr('class', 'ism-aisle-edge');

  const labelGroup = svg.append('g').attr('class', 'ism-labels');

  for (let c = 1; c <= CINEMA_VENUE_COLS; c += 1) {
    const cx = ox + seatCenterX(c, cellX, aisleW);
    labelGroup
      .append('text')
      .attr('x', cx)
      .attr('y', EDGE_PAD + Math.floor(LABEL_TOP * 0.62))
      .attr('text-anchor', 'middle')
      .attr('class', 'ism-col-label')
      .text(String(c));
  }

  for (let r = 1; r <= CINEMA_VENUE_ROWS; r += 1) {
    const cy = oy + (r - 0.5) * cellY;
    labelGroup
      .append('text')
      .attr('x', EDGE_PAD + Math.floor(LABEL_LEFT * 0.42))
      .attr('y', cy)
      .attr('dominant-baseline', 'middle')
      .attr('text-anchor', 'middle')
      .attr('class', 'ism-row-label')
      .text(String(r));
  }

  const hitR = Math.max(SEAT_R + 14, 22);

  const n = cells.length;
  for (let i = 0; i < n; i += 1) {
    const cell = cells[i];
    const row = cell.row;
    const col = cell.col;
    const seatId = cell.seatId;
    const cx = seatCenterX(col, cellX, aisleW);
    const cy = (row - 0.5) * cellY;
    const cls = classForSeat(seatId);

    g
      .append('circle')
      .attr('cx', cx)
      .attr('cy', cy)
      .attr('r', SEAT_R)
      .attr('data-seat-id', seatId)
      .attr('class', `ism-seat-vis ${cls}`)
      .attr('pointer-events', 'none');

    const hit = g
      .append('circle')
      .attr('cx', cx)
      .attr('cy', cy)
      .attr('r', hitR)
      .attr('class', `ism-seat-hit ${cls}`)
      .attr('data-seat-id', seatId)
      .attr('tabindex', '-1');

    if (props.readOnly) {
      hit.attr('pointer-events', 'none');
    } else {
      bindSeatActivate(hit.node(), seatId, row, col);
    }
  }
}

function bindResize () {
  const el = mapRoot.value;
  if (!el || typeof ResizeObserver === 'undefined') {
    return;
  }
  resizeObserver = new ResizeObserver(() => {
    computeMetrics();
    render();
  });
  resizeObserver.observe(el);
}

function unbindResize () {
  if (resizeObserver && mapRoot.value) {
    resizeObserver.unobserve(mapRoot.value);
  }
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
}

onMounted(() => {
  nextTick(() => {
    computeMetrics();
    render();
    bindResize();
  });
  seatmapStore.$subscribe(() => {
    render();
  });
});

onBeforeUnmount(() => {
  unbindResize();
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
