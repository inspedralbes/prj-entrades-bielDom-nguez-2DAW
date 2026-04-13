<?php

namespace App\Services\Order;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\Payment\PaymentStubService;
use Illuminate\Support\Facades\DB;

class QuantityPurchaseService
{
    public function __construct (
        private readonly PaymentStubService $paymentStubService,
    ) {}

    /**
     * Create an order for quantity-based purchase (no seat selection).
     *
     * @return array{
     *   ok: true,
     *   order: Order,
     *   payment: array{provider: string, client_secret: string, status: string}
     * }|array{ok: false, reason: string, http_status: int}
     */
    public function createQuantityOrder (User $user, int $eventId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 6) {
            return ['ok' => false, 'reason' => 'invalid_quantity', 'http_status' => 422];
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'reason' => 'event_not_found', 'http_status' => 404];
        }

        $stubUnit = (float) config('services.order.stub_unit_price', 25.0);
        $total = round($stubUnit * $quantity, 2);

        try {
            // Una línia de comanda per entrada: tickets.order_line_id és UNIQUE (1 ticket ↔ 1 línia).
            $order = DB::transaction(function () use ($user, $eventId, $quantity, $stubUnit, $total) {
                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'event_id' => $eventId,
                    'state' => Order::STATE_PENDING_PAYMENT,
                    'currency' => 'EUR',
                    'total_amount' => $total,
                    'quantity' => $quantity,
                ]);

                for ($i = 0; $i < $quantity; $i++) {
                    OrderLine::query()->create([
                        'order_id' => $order->id,
                        'seat_id' => null,
                        'unit_price' => $stubUnit,
                    ]);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'order_creation_failed', 'http_status' => 500];
        }

        $payment = $this->paymentStubService->createPaymentIntent($order);

        return [
            'ok' => true,
            'order' => $order,
            'payment' => $payment,
        ];
    }
}