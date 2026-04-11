<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\UserSetting;
use App\Services\Recommend\GeminiHomeRecommendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function __construct (
        private readonly GeminiHomeRecommendService $geminiHomeRecommendService
    ) {}

    public function featured (): JsonResponse
    {
        $events = Event::query()
            ->whereNull('hidden_at')
            ->with('venue')
            ->orderBy('starts_at')
            ->limit(24)
            ->get();

        return response()->json([
            'section' => 'featured',
            'events' => $events->map(fn (Event $e) => $this->eventPayload($e))->values()->all(),
        ]);
    }

    public function forYou (Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $settings = UserSetting::query()->find($user->id);
        $geminiOn = $settings?->gemini_personalization_enabled ?? true;

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $baseQuery = Event::query()
            ->whereNull('hidden_at')
            ->with('venue');

        if ($geminiOn) {
            $candidates = (clone $baseQuery)->orderBy('starts_at')->limit(40)->pluck('id')->all();
            $orderedIds = $this->geminiHomeRecommendService->rankEventIds($candidates, $user);
            $events = $this->eventsByIdsPreservingOrder($orderedIds);
        } elseif ($this->canUsePostgisProximity($lat, $lng)) {
            $events = $this->eventsByProximity((float) $lat, (float) $lng);
        } else {
            $events = (clone $baseQuery)->orderBy('starts_at')->limit(16)->get();
        }

        return response()->json([
            'section' => 'for_you',
            'source' => $geminiOn ? 'gemini_stub' : 'proximity_or_chronological',
            'events' => $events->map(fn (Event $e) => $this->eventPayload($e))->values()->all(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Event>
     */
    private function eventsByIdsPreservingOrder (array $ids)
    {
        if ($ids === []) {
            return collect();
        }
        $events = Event::query()->whereIn('id', $ids)->with('venue')->get()->keyBy('id');
        $ordered = collect();
        foreach ($ids as $id) {
            if ($events->has($id)) {
                $ordered->push($events->get($id));
            }
        }

        return $ordered;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Event>
     */
    private function eventsByProximity (float $lat, float $lng)
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

    private function canUsePostgisProximity (?string $lat, ?string $lng): bool
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
    private function eventPayload (Event $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'category' => $e->category,
            'venue' => $e->venue ? [
                'id' => $e->venue->id,
                'name' => $e->venue->name,
            ] : null,
        ];
    }
}
