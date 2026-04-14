<?php

namespace App\Services\Order;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Seatmap\CinemaVenueLayout;
use App\Services\Payment\PaymentStubService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Comanda pendent amb seients del mapa cinema (claus section_1-row_*-seat_*), sense files a la taula `seats`.
 */
class CinemaSeatOrderService
{
    public function __construct(
        private readonly PaymentStubService $paymentStubService,
    )
    {
    }

    /**
     * @param  list<string>  $seatKeys
     * @return array{ok: true, order: Order, payment: array<string, mixed>}|array{ok: false, reason: string, http_status: int, message?: string}
     */
    public function createPendingOrder(User $user, int $eventId, array $seatKeys): array
    {
        $n = count($seatKeys);
        if ($n < 1 || $n > 6) {
            return ['ok' => false, 'reason' => 'invalid_seat_count', 'http_status' => 422];
        }

        $seen = [];
        $normalized = [];
        for ($i = 0; $i < $n; $i++) {
            $k = trim((string) $seatKeys[$i]);
            if ($k === '') {
                return ['ok' => false, 'reason' => 'invalid_seat', 'http_status' => 422, 'message' => 'Seient no vàlid'];
            }
            if (!CinemaVenueLayout::isValidSeatId($k)) {
                return ['ok' => false, 'reason' => 'invalid_seat', 'http_status' => 422, 'message' => 'Seient no vàlid'];
            }
            if (isset($seen[$k])) {
                return ['ok' => false, 'reason' => 'duplicate_seat', 'http_status' => 422];
            }
            $seen[$k] = true;
            $normalized[] = $k;
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'reason' => 'event_not_found', 'http_status' => 404];
        }

        $layout = $event->seat_layout;
        if (!is_array($layout)) {
            $layout = [];
        }

        $uid = (string) $user->id;
        $conn = Redis::connection();

        for ($j = 0; $j < $n; $j++) {
            $key = $normalized[$j];
            if ($this->isSeatSoldInLayout($layout, $key)) {
                return ['ok' => false, 'reason' => 'seat_sold', 'http_status' => 409, 'message' => 'Un o més seients ja estan venuts'];
            }
            $rkey = 'event:'.(string) $eventId.':seat:'.$key;
            $holder = $conn->get($rkey);
            if ($holder === null || $holder === false || (string) $holder !== $uid) {
                return ['ok' => false, 'reason' => 'not_held_by_user', 'http_status' => 409, 'message' => 'Reserva els seients al mapa abans de comprar (o la reserva ha caducat).'];
            }
        }

        $unit = $event->price !== null ? (float) $event->price : (float) config('services.order.stub_unit_price', 25.0);
        $total = round($unit * $n, 2);

        try {
            $order = DB::transaction(function () use ($user, $eventId, $normalized, $unit, $total, $n) {
                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'event_id' => $eventId,
                    'state' => Order::STATE_PENDING_PAYMENT,
                    'currency' => 'EUR',
                    'total_amount' => $total,
                ]);

                for ($k = 0; $k < $n; $k++) {
                    OrderLine::query()->create([
                        'order_id' => $order->id,
                        'seat_id' => null,
                        'seat_key' => $normalized[$k],
                        'unit_price' => $unit,
                    ]);
                }

                return $order->fresh(['orderLines']);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'order_create_failed', 'http_status' => 500];
        }

        $payment = $this->paymentStubService->createPaymentIntent($order);

        return ['ok' => true, 'order' => $order, 'payment' => $payment];
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function isSeatSoldInLayout(array $layout, string $seatId): bool
    {
        if (!array_key_exists($seatId, $layout)) {
            return false;
        }

        $val = $layout[$seatId];
        if ($val === true || $val === 1 || $val === '1') {
            return true;
        }

        if (is_string($val) && $val !== '' && $val !== '0') {
            return true;
        }

        return false;
    }
}
