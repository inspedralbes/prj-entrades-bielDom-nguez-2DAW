<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Seatmap\EventSeatHoldService;
use App\Services\Seatmap\PostgresSeatmapFallbackService;
use App\Services\Ticketmaster\TicketmasterSeatmapClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatmapController extends Controller
{
    public function __construct (
        private readonly TicketmasterSeatmapClient $ticketmasterSeatmapClient,
        private readonly PostgresSeatmapFallbackService $postgresSeatmapFallbackService,
        private readonly EventSeatHoldService $eventSeatHoldService,
    ) {}

    public function show (string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $fallback = $this->postgresSeatmapFallbackService->buildForEvent($event);
        $seatsOut = $this->postgresSeatmapFallbackService->seatsForEvent($event);

        $tmPayload = null;
        $externalId = $event->external_tm_id;
        if ($externalId !== null && $externalId !== '') {
            $tmPayload = $this->ticketmasterSeatmapClient->fetch($externalId);
        }

        $seatLayoutSold = $this->extractSoldSeatLayout($event);
        $redisHolds = $this->eventSeatHoldService->getHoldsForEvent($event->id);

        $base = [
            'layoutType' => 'cinema',
            'seat_layout' => $seatLayoutSold,
            'redis_holds' => $redisHolds,
        ];

        if ($tmPayload === null) {
            return response()->json(array_merge($fallback, [
                'seats' => $seatsOut,
            ], $base));
        }

        $zonesOut = $tmPayload['zones'];
        $useFallbackZones = true;
        foreach ($zonesOut as $_item) {
            $useFallbackZones = false;
            break;
        }
        if ($useFallbackZones) {
            $zonesOut = $fallback['zones'];
        }

        $snapshotOut = $tmPayload['snapshotImageUrl'];
        if ($snapshotOut === null || $snapshotOut === '') {
            $snapshotOut = $fallback['snapshotImageUrl'];
        }

        return response()->json(array_merge([
            'snapshotImageUrl' => $snapshotOut,
            'zones' => $zonesOut,
            'seats' => $seatsOut,
        ], $base));
    }

    /**
     * POST /api/events/{eventId}/seat-holds — bloqueig temporal (JWT).
     */
    public function holdSeat (Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $validated = $request->validate([
            'seat_id' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->eventSeatHoldService->holdSeat($event, $validated['seat_id'], (int) $user->id);

        if ($result['ok'] === false) {
            $status = 409;
            $reason = $result['reason'];
            if ($reason === 'invalid_seat') {
                $status = 422;
            }

            return response()->json([
                'message' => $result['message'] ?? 'No s\'ha pogut reservar el seient',
                'reason' => $reason,
            ], $status);
        }

        return response()->json([
            'ok' => true,
            'seat_id' => $validated['seat_id'],
            'ttl_seconds' => 120,
        ]);
    }

    /**
     * POST /api/events/{eventId}/seat-holds/release — alliberar reserva Redis (deselecció al mapa).
     */
    public function releaseSeat (Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $validated = $request->validate([
            'seat_id' => ['required', 'string', 'max:255'],
        ]);

        $released = $this->eventSeatHoldService->releaseUserSeatHold(
            $event,
            $validated['seat_id'],
            (int) $user->id,
        );

        if ($released === false) {
            return response()->json(['message' => 'No s\'ha pogut alliberar el seient'], 409);
        }

        return response()->json(['ok' => true, 'seat_id' => $validated['seat_id']]);
    }

    /**
     * POST /api/events/{eventId}/seat-holds/release-all — allibera tots els holds Redis de l’usuari en aquest esdeveniment
     * (p. ex. surt del mapa). Notifica available per socket com releaseSeat.
     */
    public function releaseAllMyHolds (Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $released = $this->eventSeatHoldService->releaseAllHoldsForUser((int) $user->id, (int) $event->id);

        return response()->json([
            'ok' => true,
            'released_seat_ids' => $released,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSoldSeatLayout (Event $event): array
    {
        $layout = $event->seat_layout;
        if (! is_array($layout)) {
            return [];
        }

        $out = [];
        foreach ($layout as $key => $val) {
            if ($key === 'snapshotImageUrl') {
                continue;
            }
            if (is_string($key) && str_starts_with($key, 'section_1-row_')) {
                $out[$key] = $val;
            }
        }

        return $out;
    }
}
