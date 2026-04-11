<?php

namespace App\Services\Seatmap;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Zone;

/**
 * Fallback: zones (i opcionalment imatge) des de PostgreSQL quan Top Picks no està disponible.
 */
class PostgresSeatmapFallbackService
{
    /**
     * @return array{snapshotImageUrl: ?string, zones: array<int, array{id: string, label: string}>}
     */
    public function buildForEvent (Event $event): array
    {
        $zones = [];
        $rows = Zone::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $z) {
            $zones[] = [
                'id' => (string) $z->id,
                'label' => $z->label,
            ];
        }

        $snapshot = null;
        $layout = $event->seat_layout;
        if (is_array($layout) && isset($layout['snapshotImageUrl']) && is_string($layout['snapshotImageUrl'])) {
            $snapshot = $layout['snapshotImageUrl'];
        }

        return [
            'snapshotImageUrl' => $snapshot,
            'zones' => $zones,
        ];
    }

    /**
     * Llista de seients des de PostgreSQL per al mapa interactiu (T023).
     *
     * @return array<int, array{id: int, zoneId: string, key: string, status: string}>
     */
    public function seatsForEvent (Event $event): array
    {
        $rows = Seat::query()
            ->where('event_id', $event->id)
            ->orderBy('zone_id')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $s) {
            $out[] = [
                'id' => (int) $s->id,
                'zoneId' => (string) $s->zone_id,
                'key' => $s->external_seat_key,
                'status' => $s->status,
            ];
        }

        return $out;
    }
}
