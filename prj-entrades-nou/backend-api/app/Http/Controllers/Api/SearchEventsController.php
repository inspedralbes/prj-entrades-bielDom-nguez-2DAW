<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Services\Search\EventCitySearchService;
use App\Services\Search\EventDetailService;
use App\Services\Search\EventNearbySearchService;
use App\Services\Search\EventPricingService;
use App\Services\Search\EventTextSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class SearchEventsController extends Controller
{
    public function __construct(
        private readonly EventTextSearchService $eventTextSearchService,
        private readonly EventNearbySearchService $eventNearbySearchService,
        private readonly EventCitySearchService $eventCitySearchService,
        private readonly EventPricingService $eventPricingService,
        private readonly EventDetailService $eventDetailService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->eventTextSearchService->searchEvents($request));
    }

    /**
     * Get events within a radius (proximity filter).
     */
    public function nearby(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radiusKm = (int) $request->query('radius', 50);

        if ($lat === null || $lng === null) {
            return response()->json(['message' => 'lat and lng are required'], 422);
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        if (config('database.default') !== 'pgsql') {
            return response()->json(['message' => 'PostGIS not available'], 500);
        }

        return response()->json(
            $this->eventNearbySearchService->eventsWithinRadius($latF, $lngF, $radiusKm)
        );
    }

    /**
     * Search cities with autocomplete.
     */
    public function searchCities(Request $request): JsonResponse
    {
        $q = $request->query('q');
        if ($q === null) {
            return response()->json(['cities' => []]);
        }

        return response()->json(
            $this->eventCitySearchService->searchCities((string) $q)
        );
    }

    /**
     * Get unit price for an event (for quantity purchase).
     */
    public function eventPrice(int $eventId): JsonResponse
    {
        $payload = $this->eventPricingService->unitPricePayload($eventId);
        if ($payload === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        return response()->json($payload);
    }

    /**
     * Get event detail by ID.
     */
    public function show(int $eventId): JsonResponse
    {
        $payload = $this->eventDetailService->detailPayload($eventId);
        if ($payload === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        return response()->json($payload);
    }
}
