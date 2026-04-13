<?php

namespace App\Seatmap;

/**
 * Geometria fixa del mapa tipus cinema (18 files, passadís central entre 19 i 21).
 * Ha de coincidir amb `frontend-nuxt/utils/cinemaVenueLayout.js`.
 */
final class CinemaVenueLayout
{
    /**
     * @return list<int>
     */
    public static function columnsForRow (int $row): array
    {
        if ($row < 1 || $row > 18) {
            return [];
        }

        if ($row <= 2 || $row >= 8) {
            return self::mergeSides(1, 19, 21, 39);
        }

        if ($row === 3) {
            return self::mergeSides(9, 19, 21, 30);
        }

        if ($row === 4) {
            return self::mergeSides(12, 19, 21, 27);
        }

        if ($row === 5) {
            return self::mergeSides(11, 19, 21, 28);
        }

        if ($row === 6) {
            return self::mergeSides(14, 19, 21, 25);
        }

        if ($row === 7) {
            return self::mergeSides(15, 19, 21, 22);
        }

        return [];
    }

    /**
     * @return list<string> Tots els seat_id vàlids (section_1-row_{fila}-seat_{col}).
     */
    public static function allSeatIds (): array
    {
        $out = [];
        for ($row = 1; $row <= 18; $row++) {
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

    /**
     * @return list<int>
     */
    private static function mergeSides (int $minL, int $maxL, int $minR, int $maxR): array
    {
        $out = [];
        for ($c = $minL; $c <= $maxL; $c++) {
            $out[] = $c;
        }
        for ($c = $minR; $c <= $maxR; $c++) {
            $out[] = $c;
        }

        return $out;
    }
}
