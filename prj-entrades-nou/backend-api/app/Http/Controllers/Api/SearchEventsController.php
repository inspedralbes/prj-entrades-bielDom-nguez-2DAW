<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchEventsController extends Controller
{
    public function index (Request $request): JsonResponse
    {
        // A PostgreSQL, `venues.location` és geography; Eloquent el retorna com a string sense cast a Point.
        // Extraiem lat/lng amb PostGIS al JOIN. A sqlite (tests) no hi ha ST_Y/ST_X sobre geography.
        $usePostgisJoin = config('database.default') === 'pgsql';

        $q = Event::query()->with('venue');

        if ($usePostgisJoin) {
            $q->whereNull('events.hidden_at')
                ->leftJoin('venues as v_map', 'events.venue_id', '=', 'v_map.id')
                ->selectRaw(
                    'events.*, '
                    .'(CASE WHEN v_map.location IS NOT NULL THEN ST_Y(v_map.location::geometry) ELSE NULL END) AS venue_map_lat, '
                    .'(CASE WHEN v_map.location IS NOT NULL THEN ST_X(v_map.location::geometry) ELSE NULL END) AS venue_map_lng'
                );
        } else {
            $q->whereNull('hidden_at');
        }

        if ($s = $request->query('q')) {
            $term = '%'.addcslashes((string) $s, '%_\\').'%';
            if ($usePostgisJoin) {
                $q->whereRaw('LOWER(events.name) LIKE LOWER(?)', [$term]);
            } else {
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$term]);
            }
        }

        if ($c = $request->query('category')) {
            if ($usePostgisJoin) {
                $q->where('events.category', (string) $c);
            } else {
                $q->where('category', (string) $c);
            }
        }

        // Filtre de data només per data d’inici (starts_at) des d’aquest dia; sense “fins a”.
        if ($from = $request->query('date_from')) {
            if ($usePostgisJoin) {
                $q->whereDate('events.starts_at', '>=', $from);
            } else {
                $q->whereDate('starts_at', '>=', $from);
            }
        }

        $limit = 0;
        $limitRaw = $request->query('limit');
        if ($limitRaw !== null && $limitRaw !== '') {
            $limit = (int) $limitRaw;
            if ($limit > 40) {
                $limit = 40;
            }
            if ($limit < 0) {
                $limit = 0;
            }
        }

        if ($usePostgisJoin) {
            $q->orderBy('events.starts_at');
            if ($limit > 0) {
                $q->limit($limit);
            }
            $events = $q->get();
        } else {
            $q->orderBy('starts_at');
            if ($limit > 0) {
                $q->limit($limit);
            }
            $events = $q->get();
        }

        $out = [];
        foreach ($events as $e) {
            $mapLat = null;
            $mapLng = null;
            if ($usePostgisJoin) {
                if ($e->venue_map_lat !== null) {
                    $mapLat = (float) $e->venue_map_lat;
                }
                if ($e->venue_map_lng !== null) {
                    $mapLng = (float) $e->venue_map_lng;
                }
            }
            $venuePayload = null;
            if ($e->venue) {
                $venuePayload = [
                    'id' => $e->venue->id,
                    'name' => $e->venue->name,
                    'city' => $e->venue->city,
                ];
            }
            $starts = null;
            if ($e->starts_at !== null) {
                $starts = $e->starts_at->toIso8601String();
            }
            $out[] = [
                'id' => $e->id,
                'name' => $e->name,
                'starts_at' => $starts,
                'category' => $e->category,
                'image_url' => $e->image_url,
                'tm_url' => $e->tm_url,
                'price' => $e->price,
                'venue' => $venuePayload,
                'map_lat' => $mapLat,
                'map_lng' => $mapLng,
            ];
        }

        return response()->json([
            'events' => $out,
        ]);
    }

    /**
     * Get events within a radius (proximity filter).
     */
    public function nearby (Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radiusKm = (int) $request->query('radius', 50);

        if ($lat === null || $lng === null) {
            return response()->json(['message' => 'lat and lng are required'], 422);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radiusMeters = $radiusKm * 1000;

        if (config('database.default') !== 'pgsql') {
            return response()->json(['message' => 'PostGIS not available'], 500);
        }

        $events = Event::query()
            ->whereNull('hidden_at')
            ->join('venues', 'events.venue_id', '=', 'venues.id')
            ->whereNotNull('venues.location')
            ->selectRaw('events.*, ST_Distance(venues.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) / 1000 as distance_km', [$lng, $lat])
            ->whereRaw('ST_DWithin(venues.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radiusMeters])
            ->orderBy('distance_km')
            ->limit(60)
            ->get();

        return response()->json([
            'events' => $events->map(function (Event $e) {
                $id = (int) $e->id;

                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'starts_at' => $e->starts_at?->toIso8601String(),
                    'category' => $e->category,
                    'tm_category' => $e->tm_category,
                    'image_url' => $e->image_url,
                    'tm_url' => $e->tm_url,
                    'distance_km' => round($e->distance_km, 1),
                    'venue' => $e->venue ? [
                        'id' => $e->venue->id,
                        'name' => $e->venue->name,
                    ] : null,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Search cities with autocomplete.
     */
    public function searchCities (Request $request): JsonResponse
    {
        $q = $request->query('q');

        if ($q === null || strlen($q) < 2) {
            return response()->json(['cities' => []]);
        }

        $term = '%'.addcslashes((string) $q, '%_\\').'%';

        $cities = Venue::query()
            ->whereNotNull('location')
            ->selectRaw("DISTINCT ON (name) name, ST_Y(location) as lat, ST_X(location) as lng")
            ->where('name', 'ILIKE', $term)
            ->limit(10)
            ->get();

        return response()->json([
            'cities' => $cities->map(function (Venue $v) {
                return [
                    'name' => $v->name,
                    'lat' => $v->lat,
                    'lng' => $v->lng,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Get unit price for an event (for quantity purchase).
     */
    public function eventPrice (Request $request, int $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $unitPrice = $event->price ?? (float) config('services.order.fixed_event_price_eur', 20.0);

        return response()->json([
            'event_id' => $eventId,
            'unit_price' => $unitPrice,
            'currency' => 'EUR',
        ]);
    }

    /**
     * Get event detail by ID.
     */
    public function show (int $eventId): JsonResponse
    {
        $event = Event::query()
            ->with('venue')
            ->find($eventId);

        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
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

        return response()->json([
            'id' => $event->id,
            'name' => $event->name,
            'description' => $description,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'category' => $event->category,
            'tm_category' => $event->tm_category,
            'image_url' => $event->image_url,
            'tm_url' => $event->tm_url,
            'price' => $event->price,
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'map_lat' => $mapLat,
                'map_lng' => $mapLng,
            ] : null,
        ]);
    }
}
