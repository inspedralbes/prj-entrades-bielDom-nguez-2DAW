<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchEventsController extends Controller
{
    public function index (Request $request): JsonResponse
    {
        $q = Event::query()
            ->whereNull('hidden_at')
            ->with('venue');

        if ($s = $request->query('q')) {
            $term = '%'.addcslashes((string) $s, '%_\\').'%';
            $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$term]);
        }

        if ($c = $request->query('category')) {
            $q->where('category', (string) $c);
        }

        if ($from = $request->query('date_from')) {
            $q->whereDate('starts_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('starts_at', '<=', $to);
        }

        $events = $q->orderBy('starts_at')->limit(60)->get();

        return response()->json([
            'events' => $events->map(function (Event $e) {
                $id = (int) $e->id;

                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'starts_at' => $e->starts_at?->toIso8601String(),
                    'category' => $e->category,
                    'venue' => $e->venue ? [
                        'id' => $e->venue->id,
                        'name' => $e->venue->name,
                    ] : null,
                    /* Stub mapa (T048): quan hi hagi coordenades reals al venue, substituir. */
                    'map_lat' => 41.3874 + (($id % 20) * 0.0012),
                    'map_lng' => 2.1686 + (($id % 15) * 0.0012),
                ];
            })->values()->all(),
        ]);
    }
}
