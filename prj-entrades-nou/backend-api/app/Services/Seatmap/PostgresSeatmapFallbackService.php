<?php

namespace App\Services\Seatmap;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Seat;
use App\Models\Zone;
//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Fallback quan Ticketmaster no retorna mapa: zones/seients des de PostgreSQL.
 */
class PostgresSeatmapFallbackService
{
    /**
     * @return array{snapshotImageUrl: ?string, zones: list<array{id: string, label: string, availability?: int}>}
     */
    public function buildForEvent(Event $event): array
    {
        $snapshot = null;
        if ($event->image_url !== null && $event->image_url !== '') {
            $snapshot = (string) $event->image_url;
        }

        $layout = $event->seat_layout;
        if (is_array($layout) && isset($layout['snapshotImageUrl']) && is_string($layout['snapshotImageUrl']) && $layout['snapshotImageUrl'] !== '') {
            $snapshot = $layout['snapshotImageUrl'];
        }

        $zonesOut = [];
        $zones = Zone::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($zones as $z) {
            $zonesOut[] = [
                'id' => (string) $z->id,
                'label' => (string) $z->label,
            ];
        }

        return [
            'snapshotImageUrl' => $snapshot,
            'zones' => $zonesOut,
        ];
    }

    /**
     * @return list<array{id: int, zoneId: string, key: string, status: string}>
     */
    public function seatsForEvent(Event $event): array
    {
        $seats = Seat::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($seats as $s) {
            $out[] = [
                'id' => (int) $s->id,
                'zoneId' => (string) $s->zone_id,
                'key' => (string) $s->external_seat_key,
                'status' => (string) $s->status,
            ];
        }

        return $out;
    }
}
