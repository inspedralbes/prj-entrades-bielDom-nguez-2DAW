<?php

namespace App\Seatmap;

/**
 * Geometria fixa del mapa tipus cinema: graella 10×10 (columnes 1–10).
 * El passadís entre blocs esquerre/dret és només visual al client (entre cols 5 i 6).
 * Ha de coincidir amb `frontend-nuxt/utils/cinemaVenueLayout.js`.
 */
final class CinemaVenueLayout
{
    public const ROWS = 10;

    public const COLS = 10;

    /**
     * @return list<int>
     */
    public static function columnsForRow (int $row): array
    {
        if ($row < 1 || $row > self::ROWS) {
            return [];
        }

        $out = [];
        for ($c = 1; $c <= self::COLS; $c++) {
            $out[] = $c;
        }

        return $out;
    }

    /**
     * @return list<string> Tots els seat_id vàlids (section_1-row_{fila}-seat_{col}).
     */
    public static function allSeatIds (): array
    {
        $out = [];
        for ($row = 1; $row <= self::ROWS; $row++) {
            $cols = self::columnsForRow($row);
            $n = count($cols);
            for ($i = 0; $i < $n; $i++) {
                $col = $cols[$i];
                $out[] = 'section_1-row_'.$row.'-seat_'.$col;
            }
        }

        return $out;
    }

    public static function isValidSeatId (string $seatId): bool
    {
        if (! preg_match('/^section_1-row_(\d+)-seat_(\d+)$/', $seatId, $m)) {
            return false;
        }

        $row = (int) $m[1];
        $col = (int) $m[2];
        $allowed = self::columnsForRow($row);
        $n = count($allowed);
        for ($i = 0; $i < $n; $i++) {
            if ($allowed[$i] === $col) {
                return true;
            }
        }

        return false;
    }
}
