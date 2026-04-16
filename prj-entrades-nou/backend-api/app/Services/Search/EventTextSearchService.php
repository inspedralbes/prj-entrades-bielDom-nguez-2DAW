<?php

namespace App\Services\Search;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use Illuminate\Http\Request;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Cerca textual d’esdeveniments amb filtres opcionals (q, category, date_from, limit).
 */
class EventTextSearchService
{
    /**
     * A. Construeix la consulta (PostGIS join opcional vs sqlite tests).
     * B. Aplica filtres i límit.
     * C. Serialitza cada fila a l’estructura JSON de l’API.
     *
     * @return array{events: list<array<string, mixed>>}
     */
    public function searchEvents(Request $request): array
    {
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

        $queryText = $request->query('q');
        if ($queryText !== null && $queryText !== '') {
            $term = '%'.addcslashes((string) $queryText, '%_\\').'%';
            if ($usePostgisJoin) {
                $q->whereRaw('LOWER(events.name) LIKE LOWER(?)', [$term]);
            } else {
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$term]);
            }
        }

        $category = $request->query('category');
        if ($category !== null && $category !== '') {
            if ($usePostgisJoin) {
                $q->where('events.category', (string) $category);
            } else {
                $q->where('category', (string) $category);
            }
        }

        $from = $request->query('date_from');
        if ($from !== null && $from !== '') {
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

        return ['events' => $out];
    }
}
