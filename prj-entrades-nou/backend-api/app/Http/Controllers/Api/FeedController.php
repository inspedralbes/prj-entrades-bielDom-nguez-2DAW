<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class FeedController extends Controller
{
    /**
     * A. Sense autenticació.
     * B. Consulta esdeveniments visibles amb venue mínim.
     * C. JSON `featured` (bucle explícit, sense `map` de col·lecció).
     */
    public function featured(): JsonResponse
    {
        $events = Event::query()
            ->whereNull('hidden_at')
            ->with($this->venueEagerLoad())
            ->orderBy('starts_at')
            ->limit(24)
            ->get();

        $payloads = [];
        foreach ($events as $e) {
            $payloads[] = $this->eventPayload($e);
        }

        return response()->json([
            'section' => 'featured',
            'events' => $payloads,
        ]);
    }

    /**
     * A. Usuari autenticat + ajustos de proximitat opcionals.
     * B. Consulta cronològica o PostGIS segons configuració.
     * C. JSON `for_you`.
     */
    public function forYou(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $settings = UserSetting::query()->find($user->id);
        $proximityOn = $settings?->proximity_personalization_enabled ?? false;

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $baseQuery = Event::query()
            ->whereNull('hidden_at')
            ->with($this->venueEagerLoad());

        if ($proximityOn && $this->canUsePostgisProximity($lat, $lng)) {
            $events = $this->eventsByProximity((float) $lat, (float) $lng);
            $source = 'proximity';
        } else {
            $events = (clone $baseQuery)->orderBy('starts_at')->limit(16)->get();
            $source = 'chronological';
        }

        $payloadsForYou = [];
        foreach ($events as $e) {
            $payloadsForYou[] = $this->eventPayload($e);
        }

        return response()->json([
            'section' => 'for_you',
            'source' => $source,
            'events' => $payloadsForYou,
        ]);
    }

    /**
     * @return Collection<int, Event>
     */
    private function eventsByIdsPreservingOrder(array $ids)
    {
        if ($ids === []) {
            return collect();
        }
        $events = Event::query()->whereIn('id', $ids)->with($this->venueEagerLoad())->get()->keyBy('id');
        $ordered = collect();
        foreach ($ids as $id) {
            if ($events->has($id)) {
                $ordered->push($events->get($id));
            }
        }

        return $ordered;
    }

    /**
     * Només id + nom del recinte; evita carregar geography `location` (PostGIS) al feed.
     *
     * @return array<string, \Closure>
     */
    private function venueEagerLoad(): array
    {
        return [
            'venue' => static function ($q) {
                $q->select('venues.id', 'venues.name', 'venues.city');
            },
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    private function eventsByProximity(float $lat, float $lng)
    {
        $rows = DB::select(
            'SELECT e.id FROM events e
             INNER JOIN venues v ON v.id = e.venue_id
             WHERE e.hidden_at IS NULL AND v.location IS NOT NULL
             ORDER BY ST_Distance(v.location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)
             LIMIT 16',
            [$lng, $lat]
        );
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r->id;
        }

        return $this->eventsByIdsPreservingOrder($ids);
    }

    private function canUsePostgisProximity(?string $lat, ?string $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }
        if (config('database.default') !== 'pgsql') {
            return false;
        }

        return is_numeric($lat) && is_numeric($lng);
    }

    /**
     * @return array<string, mixed>
     */
    //================================ LÒGICA PRIVADA ================

    private function eventPayload(Event $e): array
    {
        $starts = null;
        if ($e->starts_at !== null) {
            $starts = $e->starts_at->toIso8601String();
        }
        $venuePayload = null;
        if ($e->venue !== null) {
            $venuePayload = [
                'id' => $e->venue->id,
                'name' => $e->venue->name,
                'city' => $e->venue->city,
            ];
        }

        return [
            'id' => $e->id,
            'name' => $e->name,
            'starts_at' => $starts,
            'category' => $e->category,
            'image_url' => $e->image_url,
            'tm_url' => $e->tm_url,
            'price' => $e->price,
            'venue' => $venuePayload,
        ];
    }
}
