<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function featured(): JsonResponse
    {
        $events = Event::query()
            ->whereNull('hidden_at')
            ->with($this->venueEagerLoad())
            ->orderBy('starts_at')
            ->limit(24)
            ->get();

        return response()->json([
            'section' => 'featured',
            'events' => $events->map(fn (Event $e) => $this->eventPayload($e))->values()->all(),
        ]);
    }

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

        return response()->json([
            'section' => 'for_you',
            'source' => $source,
            'events' => $events->map(fn (Event $e) => $this->eventPayload($e))->values()->all(),
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
                $q->select('venues.id', 'venues.name');
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
        $ids = array_map(fn ($r) => (int) $r->id, $rows);

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
    private function eventPayload(Event $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'category' => $e->category,
            'image_url' => $e->image_url,
            'tm_url' => $e->tm_url,
            'venue' => $e->venue ? [
                'id' => $e->venue->id,
                'name' => $e->venue->name,
            ] : null,
        ];
    }
}
