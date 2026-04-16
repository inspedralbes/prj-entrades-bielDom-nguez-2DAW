<?php

namespace App\Services\Search;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Esdeveniments dins d’un radi (PostGIS); requereix `pgsql`.
 */
class EventNearbySearchService
{
    /**
     * A. Calcula radi en metres.
     * B. Consulta amb ST_DWithin i distància.
     * C. Serialitza resultats.
     *
     * @return array{events: list<array<string, mixed>>}
     */
    public function eventsWithinRadius(float $lat, float $lng, int $radiusKm): array
    {
        $radiusMeters = $radiusKm * 1000;

        $events = Event::query()
            ->whereNull('hidden_at')
            ->join('venues', 'events.venue_id', '=', 'venues.id')
            ->whereNotNull('venues.location')
            ->selectRaw('events.*, ST_Distance(venues.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) / 1000 as distance_km', [$lng, $lat])
            ->whereRaw('ST_DWithin(venues.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radiusMeters])
            ->orderBy('distance_km')
            ->limit(60)
            ->get();

        $nearbyOut = [];
        foreach ($events as $e) {
            $starts = null;
            if ($e->starts_at !== null) {
                $starts = $e->starts_at->toIso8601String();
            }
            $venuePayload = null;
            if ($e->venue !== null) {
                $venuePayload = [
                    'id' => $e->venue->id,
                    'name' => $e->venue->name,
                ];
            }
            $nearbyOut[] = [
                'id' => $e->id,
                'name' => $e->name,
                'starts_at' => $starts,
                'category' => $e->category,
                'tm_category' => $e->tm_category,
                'image_url' => $e->image_url,
                'tm_url' => $e->tm_url,
                'distance_km' => round($e->distance_km, 1),
                'venue' => $venuePayload,
            ];
        }

        return ['events' => $nearbyOut];
    }
}
