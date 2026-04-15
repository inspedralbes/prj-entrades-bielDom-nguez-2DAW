<?php

namespace App\Services\Admin;

use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Agregacions per a la pàgina d’analítiques admin (període global).
 */
class AdminAnalyticsService
{
    /**
     * Ingressos totals de comandes pagades dins del rang (updated_at).
     *
     * @return array{total_revenue_eur: float}
     */
    public function buildSummary(Carbon $fromStart, Carbon $toEnd): array
    {
        $sum = Order::query()
            ->where('state', Order::STATE_PAID)
            ->whereBetween('updated_at', [$fromStart, $toEnd])
            ->sum('total_amount');

        $total = round((float) $sum, 2);

        return [
            'total_revenue_eur' => $total,
        ];
    }

    /**
     * Rendiment per esdeveniment: ingressos al període i mitjana diària (ingressos / dies naturals del període).
     *
     * @return array{events: list<array{event_id: int, name: string, revenue_eur: float, avg_daily_revenue_eur: float}>}
     */
    public function buildPerEvent(Carbon $fromStart, Carbon $toEnd, int $daysInPeriod): array
    {
        $orders = Order::query()
            ->where('state', Order::STATE_PAID)
            ->whereBetween('updated_at', [$fromStart, $toEnd])
            ->get(['event_id', 'total_amount']);

        $revenueByEvent = [];
        $i = 0;
        for (; $i < count($orders); $i++) {
            $o = $orders[$i];
            $eid = (int) $o->event_id;
            if (! array_key_exists($eid, $revenueByEvent)) {
                $revenueByEvent[$eid] = 0.0;
            }
            $revenueByEvent[$eid] = $revenueByEvent[$eid] + (float) $o->total_amount;
        }

        $eventIds = array_keys($revenueByEvent);
        if (count($eventIds) === 0) {
            return ['events' => []];
        }

        $events = Event::query()->whereIn('id', $eventIds)->get(['id', 'name']);
        $nameById = [];
        $j = 0;
        for (; $j < count($events); $j++) {
            $ev = $events[$j];
            $nameById[(int) $ev->id] = (string) $ev->name;
        }

        $den = $daysInPeriod;
        if ($den < 1) {
            $den = 1;
        }

        $rows = [];
        $k = 0;
        for (; $k < count($eventIds); $k++) {
            $eid = $eventIds[$k];
            $rev = $revenueByEvent[$eid];
            $name = '';
            if (array_key_exists($eid, $nameById)) {
                $name = $nameById[$eid];
            }
            $avg = $rev / (float) $den;
            $rows[] = [
                'event_id' => $eid,
                'name' => $name,
                'revenue_eur' => round($rev, 2),
                'avg_daily_revenue_eur' => round($avg, 2),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['revenue_eur'] > $b['revenue_eur']) {
                return -1;
            }
            if ($a['revenue_eur'] < $b['revenue_eur']) {
                return 1;
            }

            return 0;
        });

        return ['events' => $rows];
    }

    /**
     * Ocupació agregada per categoria: venuts (tiquets amb comanda pagada dins del rang) vs capacitat total dels esdeveniments de la categoria.
     *
     * @return array{categories: list<array{category_key: string, label: string, capacity: int, sold: int, occupancy_percent: float}>}
     */
    public function buildCategoryOccupancy(Carbon $fromStart, Carbon $toEnd): array
    {
        $allEvents = Event::query()->get(['id', 'category']);
        $idsByCategory = [];
        $n = 0;
        for (; $n < count($allEvents); $n++) {
            $ev = $allEvents[$n];
            $key = $ev->category;
            if ($key === null) {
                $key = '';
            } else {
                $key = (string) $key;
            }
            if (! array_key_exists($key, $idsByCategory)) {
                $idsByCategory[$key] = [];
            }
            $idsByCategory[$key][] = (int) $ev->id;
        }

        $categories = [];
        foreach ($idsByCategory as $catKey => $eventIds) {
            $capacity = 0;
            $ei = 0;
            for (; $ei < count($eventIds); $ei++) {
                $eid = $eventIds[$ei];
                $capacity = $capacity + Seat::query()->where('event_id', $eid)->count();
            }

            $sold = Ticket::query()
                ->where('status', Ticket::STATUS_VENUDA)
                ->whereHas('orderLine.order', static function ($q) use ($fromStart, $toEnd, $eventIds): void {
                    $q->where('state', Order::STATE_PAID)
                        ->whereBetween('updated_at', [$fromStart, $toEnd])
                        ->whereIn('event_id', $eventIds);
                })
                ->count();

            $pct = 0.0;
            if ($capacity > 0) {
                $pct = round(((float) $sold / (float) $capacity) * 100.0, 2);
            }

            $label = $catKey;
            if ($catKey === '') {
                $label = 'Sense categoria';
            }

            $categories[] = [
                'category_key' => $catKey,
                'label' => $label,
                'capacity' => $capacity,
                'sold' => $sold,
                'occupancy_percent' => $pct,
            ];
        }

        usort($categories, static function (array $a, array $b): int {
            if ($a['occupancy_percent'] > $b['occupancy_percent']) {
                return -1;
            }
            if ($a['occupancy_percent'] < $b['occupancy_percent']) {
                return 1;
            }

            return 0;
        });

        return ['categories' => $categories];
    }
}
