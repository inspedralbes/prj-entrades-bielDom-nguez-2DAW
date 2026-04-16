<?php

namespace App\Services\Search;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Venue;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Autocompletat de ciutats/recintes amb coordenades (PostGIS).
 */
class EventCitySearchService
{
    /**
     * @return array{cities: list<array{name: string, lat: mixed, lng: mixed}>}
     */
    public function searchCities(string $query): array
    {
        if (strlen($query) < 2) {
            return ['cities' => []];
        }

        $term = '%'.addcslashes($query, '%_\\').'%';

        $cities = Venue::query()
            ->whereNotNull('location')
            ->selectRaw('DISTINCT ON (name) name, ST_Y(location) as lat, ST_X(location) as lng')
            ->where('name', 'ILIKE', $term)
            ->limit(10)
            ->get();

        $citiesOut = [];
        foreach ($cities as $v) {
            $citiesOut[] = [
                'name' => $v->name,
                'lat' => $v->lat,
                'lng' => $v->lng,
            ];
        }

        return ['cities' => $citiesOut];
    }
}
