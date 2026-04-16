<?php

namespace App\Services\Search;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use Illuminate\Support\Facades\DB;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Detall d’esdeveniment per a l’API pública (amb coordenades de recinte si hi ha PostGIS).
 */
class EventDetailService
{
    /**
     * @return array<string, mixed>|null
     */
    public function detailPayload(int $eventId): ?array
    {
        $event = Event::query()
            ->with('venue')
            ->find($eventId);

        if ($event === null) {
            return null;
        }

        $mapLat = null;
        $mapLng = null;
        if (config('database.default') === 'pgsql' && $event->venue_id) {
            $row = DB::selectOne(
                'SELECT ST_Y(location::geometry) AS lat, ST_X(location::geometry) AS lng FROM venues WHERE id = ?',
                [$event->venue_id]
            );
            if ($row !== null && $row->lat !== null && $row->lng !== null) {
                $mapLat = (float) $row->lat;
                $mapLng = (float) $row->lng;
            }
        }

        $description = null;
        if (isset($event->description) && is_string($event->description) && trim($event->description) !== '') {
            $description = $event->description;
        }

        $startsAt = null;
        if ($event->starts_at !== null) {
            $startsAt = $event->starts_at->toIso8601String();
        }

        $venueJson = null;
        if ($event->venue) {
            $venueJson = [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'map_lat' => $mapLat,
                'map_lng' => $mapLng,
            ];
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $description,
            'starts_at' => $startsAt,
            'category' => $event->category,
            'tm_category' => $event->tm_category,
            'image_url' => $event->image_url,
            'tm_url' => $event->tm_url,
            'price' => $event->price,
            'venue' => $venueJson,
        ];
    }
}
