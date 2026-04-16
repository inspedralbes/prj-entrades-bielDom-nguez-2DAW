//================================ NAMESPACES
import { select } from 'd3-selection';
import { storeToRefs } from 'pinia';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
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

//================================ FUNCIONS PÚBLIQUES

/**
 * Mapa de seients D3 (mètriques, resize, render SVG) per InteractiveSeatMap.
 * A. readOnly: ref reactiva; B. onSeatSelect: callback { seatId, row, col }.
 */
export function useCinemaD3SeatMap (mapRoot, readOnlyRef, onSeatSelect) {
  const seatmapStore = useInteractiveSeatmapStore();
  const { soldBySeatId, heldBySeatId, selectedSeatIds, currentUserId } = storeToRefs(seatmapStore);

  const LABEL_TOP = 28;
  const LABEL_LEFT = 32;
  const EDGE_PAD = 6;
  const INNER = 4;
  const AISLE_FACTOR = 0.9;
  const COL_VS_ROW = 1.38;

  const layoutCellX = ref(26);
  const layoutCellY = ref(22);
  const layoutAisleW = ref(22);
  const layoutSeatR = ref(11);

  let resizeObserver = null;
  let lastEmitTs = 0;
  let unsubscribeStore = null;

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
    onSeatSelect({ seatId, row, col });
  }

  function bindSeatActivate (node, seatId, row, col) {
    select(node).on('pointerup', (ev) => {
      ev.stopPropagation();
      if (readOnlyRef.value) {
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

      if (readOnlyRef.value) {
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
    const unsub = seatmapStore.$subscribe(() => {
      render();
    });
    if (typeof unsub === 'function') {
      unsubscribeStore = unsub;
    }
  });

  onBeforeUnmount(() => {
    unbindResize();
    if (typeof unsubscribeStore === 'function') {
      unsubscribeStore();
      unsubscribeStore = null;
    }
  });
}
