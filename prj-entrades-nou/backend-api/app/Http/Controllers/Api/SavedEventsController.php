<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SavedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedEventsController extends Controller
{
    public function index (Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = SavedEvent::query()
            ->where('user_id', $user->id)
            ->with('event.venue')
            ->orderByDesc('created_at')
            ->get();

        $events = $rows->map(fn (SavedEvent $s) => $s->event)->filter();

        return response()->json([
            'events' => $events->map(fn (Event $e) => [
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
            ])->values()->all(),
        ]);
    }

    public function store (Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $user = $request->user();
        $event = Event::query()->whereNull('hidden_at')->findOrFail($data['event_id']);

        SavedEvent::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'event_id' => $event->id,
            ],
            [],
        );

        return response()->json(['saved' => true, 'event_id' => $event->id], 201);
    }

    public function destroy (Request $request, int $eventId): JsonResponse
    {
        $user = $request->user();
        SavedEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->delete();

        return response()->json(['deleted' => true]);
    }
}
