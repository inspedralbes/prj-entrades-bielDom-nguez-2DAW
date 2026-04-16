<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Informes agregats (admin).
 */
class AdminReportsController extends Controller
{
    public function salesSeries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'event_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'bucket' => ['sometimes', 'string', 'in:hour,day'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        if ($from->greaterThan($to)) {
            return response()->json(['message' => 'El rang from/to no és vàlid'], 422);
        }

        $maxDays = 93;
        if ($from->diffInDays($to) > $maxDays) {
            return response()->json(['message' => 'Rang massa ampli (màx. '.$maxDays.' dies)'], 422);
        }

        $bucket = 'hour';
        if (isset($data['bucket']) && $data['bucket'] === 'day') {
            $bucket = 'day';
        }

        $q = Order::query()
            ->where('state', Order::STATE_PAID)
            ->whereBetween('updated_at', [$from, $to]);

        if (isset($data['event_id']) && $data['event_id'] !== null) {
            $q->where('event_id', (int) $data['event_id']);
        }

        $orders = $q->get(['id', 'updated_at', 'total_amount', 'event_id']);

        $buckets = [];
        $i = 0;
        for (; $i < count($orders); $i++) {
            $o = $orders[$i];
            $u = $o->updated_at;
            if ($u === null) {
                continue;
            }
            $key = '';
            if ($bucket === 'day') {
                $key = $u->format('Y-m-d');
            } else {
                $key = $u->format('Y-m-d H:00:00');
            }
            if (! array_key_exists($key, $buckets)) {
                $buckets[$key] = 0.0;
            }
            $buckets[$key] = $buckets[$key] + (float) $o->total_amount;
        }

        $series = [];
        foreach ($buckets as $label => $amount) {
            $series[] = [
                'bucket' => $label,
                'amount_eur' => round($amount, 2),
            ];
        }

        return response()->json([
            'bucket' => $bucket,
            'series' => $series,
        ]);
    }

    public function occupancy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'min:1'],
        ]);

        $eventId = (int) $data['event_id'];
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $capacity = Seat::query()->where('event_id', $eventId)->count();

        $sold = Ticket::query()
            ->where('status', Ticket::STATUS_VENUDA)
            ->whereHas('orderLine.order', static function ($q) use ($eventId) {
                $q->where('event_id', $eventId)->where('state', Order::STATE_PAID);
            })
            ->count();

        $remaining = $capacity - $sold;
        if ($remaining < 0) {
            $remaining = 0;
        }

        $pct = 0.0;
        if ($capacity > 0) {
            $pct = round(($sold / $capacity) * 100.0, 2);
        }

        return response()->json([
            'event_id' => $eventId,
            'capacity' => $capacity,
            'sold' => $sold,
            'remaining' => $remaining,
            'occupancy_percent' => $pct,
        ]);
    }
}
