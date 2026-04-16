<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use App\Services\Seatmap\EventSeatHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Mètriques temps real per esdeveniment (admin): venuts, aforament, recaptació, holds Redis.
 */
class AdminMonitorController extends Controller
{
    public function __construct(
        private readonly EventSeatHoldService $eventSeatHoldService,
    ) {}

    public function show(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $capacity = Seat::query()->where('event_id', $event->id)->count();

        $sold = Ticket::query()
            ->where('status', Ticket::STATUS_VENUDA)
            ->whereHas('orderLine.order', static function ($q) use ($event) {
                $q->where('event_id', $event->id)->where('state', Order::STATE_PAID);
            })
            ->count();

        $revenue = Order::query()
            ->where('event_id', $event->id)
            ->where('state', Order::STATE_PAID)
            ->sum('total_amount');

        $revenueStr = '0.00';
        if ($revenue !== null) {
            $revenueStr = number_format((float) $revenue, 2, '.', '');
        }

        $remaining = $capacity - $sold;
        if ($remaining < 0) {
            $remaining = 0;
        }

        $holdsRaw = [];
        try {
            $holdsRaw = $this->eventSeatHoldService->getHoldsForEvent($event->id);
        } catch (\Throwable) {
            $holdsRaw = [];
        }

        $holdsList = [];
        $conn = null;
        try {
            $conn = Redis::connection();
        } catch (\Throwable) {
            $conn = null;
        }

        foreach ($holdsRaw as $seatId => $userId) {
            $ttl = 0;
            if ($conn !== null) {
                try {
                    $logicalKey = $this->eventSeatHoldService->redisSeatKey($event->id, (string) $seatId);
                    $ttl = $conn->ttl($logicalKey);
                    if ($ttl < 0) {
                        $ttl = 0;
                    }
                } catch (\Throwable) {
                    $ttl = 0;
                }
            }
            $row = [
                'seat_id' => (string) $seatId,
                'user_id' => (string) $userId,
                'ttl_seconds' => $ttl,
            ];
            $holdsList[] = $row;
        }

        $seatmapData = [];
        try {
            $seatmapResponse = app(SeatmapController::class)->show($eventId);
            $decoded = json_decode($seatmapResponse->getContent(), true);
            if (is_array($decoded)) {
                $seatmapData = $decoded;
            }
        } catch (\Throwable) {
            $seatmapData = [];
        }

        return response()->json([
            'event_id' => (int) $event->id,
            'name' => $event->name,
            'capacity' => $capacity,
            'tickets_sold' => $sold,
            'remaining' => $remaining,
            'revenue_eur' => $revenueStr,
            'holds' => $holdsList,
            'seatmap' => $seatmapData,
        ]);
    }
}
