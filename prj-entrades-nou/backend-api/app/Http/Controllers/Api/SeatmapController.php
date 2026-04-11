<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Seatmap\PostgresSeatmapFallbackService;
use App\Services\Ticketmaster\TicketmasterSeatmapClient;
use Illuminate\Http\JsonResponse;

class SeatmapController extends Controller
{
    public function __construct (
        private readonly TicketmasterSeatmapClient $ticketmasterSeatmapClient,
        private readonly PostgresSeatmapFallbackService $postgresSeatmapFallbackService,
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

        if ($tmPayload === null) {
            return response()->json(array_merge($fallback, [
                'seats' => $seatsOut,
            ]));
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

        return response()->json([
            'snapshotImageUrl' => $snapshotOut,
            'zones' => $zonesOut,
            'seats' => $seatsOut,
        ]);
    }
}
