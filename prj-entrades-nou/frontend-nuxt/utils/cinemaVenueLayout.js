/**
 * Geometria del mapa tipus cinema (ha de coincidir amb App\Seatmap\CinemaVenueLayout al backend).
 */

function mergeSides (minL, maxL, minR, maxR) {
  const out = [];
  let c = minL;
  while (c <= maxL) {
    out.push(c);
    c += 1;
  }
  c = minR;
  while (c <= maxR) {
    out.push(c);
    c += 1;
  }
  return out;
}

export function columnsForRow (row) {
  if (row < 1 || row > 18) {
    return [];
  }
  if (row <= 2 || row >= 8) {
    return mergeSides(1, 19, 21, 39);
  }
  if (row === 3) {
    return mergeSides(9, 19, 21, 30);
  }
  if (row === 4) {
    return mergeSides(12, 19, 21, 27);
  }
  if (row === 5) {
    return mergeSides(11, 19, 21, 28);
  }
  if (row === 6) {
    return mergeSides(14, 19, 21, 25);
  }
  if (row === 7) {
    return mergeSides(15, 19, 21, 22);
  }
  return [];
}

export function seatIdFor (row, col) {
  return `section_1-row_${row}-seat_${col}`;
}

export function allSeatCells () {
  const cells = [];
  for (let row = 1; row <= 18; row += 1) {
    const cols = columnsForRow(row);
    for (let i = 0; i < cols.length; i += 1) {
      const col = cols[i];
      cells.push({ row, col, seatId: seatIdFor(row, col) });
    }
  }
  return cells;
}
