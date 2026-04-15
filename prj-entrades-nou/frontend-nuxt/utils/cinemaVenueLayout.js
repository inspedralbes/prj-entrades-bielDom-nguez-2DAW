/**
 * Geometria del mapa tipus cinema (ha de coincidir amb App\Seatmap\CinemaVenueLayout al backend).
 * Graella 10×10; el passadís és només visual (entre columnes 5 i 6).
 */

/** Nombre de files del mapa cinema (coincideix amb CinemaVenueLayout::ROWS al backend). */
export const CINEMA_VENUE_ROWS = 10;

/** Nombre de columnes del mapa cinema (coincideix amb CinemaVenueLayout::COLS al backend). */
export const CINEMA_VENUE_COLS = 10;

/** Columna després de la qual es dibuixa el passadís visual (bloc esquerre 1–5, dret 6–10). */
export const AISLE_AFTER_COL = 5;

export function columnsForRow (row) {
  if (row < 1 || row > CINEMA_VENUE_ROWS) {
    return [];
  }
  const out = [];
  for (let c = 1; c <= CINEMA_VENUE_COLS; c += 1) {
    out.push(c);
  }
  return out;
}

export function seatIdFor (row, col) {
  return `section_1-row_${row}-seat_${col}`;
}

export function allSeatCells () {
  const cells = [];
  for (let row = 1; row <= CINEMA_VENUE_ROWS; row += 1) {
    const cols = columnsForRow(row);
    for (let i = 0; i < cols.length; i += 1) {
      const col = cols[i];
      cells.push({ row, col, seatId: seatIdFor(row, col) });
    }
  }
  return cells;
}

/**
 * Centre horitzontal del seient (coordenades de graella amb passadís).
 * @param {number} col — 1..10
 */
export function seatCenterX (col, cellX, aisleW) {
  const base = (col - 0.5) * cellX;
  if (col <= AISLE_AFTER_COL) {
    return base;
  }
  return base + aisleW;
}

/** Amplada total del bloc de seients (sense marges d’etiquetes). */
export function gridSeatsWidth (cellX, aisleW) {
  return CINEMA_VENUE_COLS * cellX + aisleW;
}

/** Alçada total del bloc de seients. */
export function gridSeatsHeight (cellY) {
  return CINEMA_VENUE_ROWS * cellY;
}
