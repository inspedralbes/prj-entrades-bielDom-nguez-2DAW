<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Seatmap\EventSeatHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalSeatHoldController extends Controller
{
    public function __construct(
        private readonly EventSeatHoldService $eventSeatHoldService,
    ) {}

    /**
     * POST /api/internal/seat-holds/release-user — cridat pel socket-server en desconnexió.
     */
    public function releaseUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $released = $this->eventSeatHoldService->releaseAllHoldsForUser((int) $validated['user_id'], null);

        return response()->json([
            'ok' => true,
            'released_seat_ids' => $released,
        ]);
    }

    /**
     * POST /api/internal/seat-holds/release-user-event — allibera holds Redis d’un usuari només per un esdeveniment
     * (p. ex. surt del mapa de seients / desconnexió socket d’aquest room).
     */
    public function releaseUserEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'event_id' => ['required', 'integer', 'min:1'],
        ]);

        $released = $this->eventSeatHoldService->releaseAllHoldsForUser(
            (int) $validated['user_id'],
            (int) $validated['event_id'],
        );

        return response()->json([
            'ok' => true,
            'released_seat_ids' => $released,
        ]);
    }
}
