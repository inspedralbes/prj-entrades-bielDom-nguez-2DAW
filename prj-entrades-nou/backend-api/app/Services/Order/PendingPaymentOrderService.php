<?php

namespace App\Services\Order;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Payment\PaymentStubService;
use Illuminate\Support\Facades\DB;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class PendingPaymentOrderService
{
    public function __construct(
        private readonly SeatHoldService $seatHoldService,
        private readonly PaymentStubService $paymentStubService,
    ) {}

    /**
     * @return array{
     *   ok: true,
     *   order: Order,
     *   payment: array{provider: string, client_secret: string, status: string}
     * }|array{ok: false, reason: string, http_status: int}
     */
    public function createFromHold(User $user, string $holdUuid, string $anonymousSessionId): array
    {
        $prepared = $this->seatHoldService->prepareHoldForOrder($holdUuid, (int) $user->id, $anonymousSessionId);
        if ($prepared['ok'] === false) {
            return $this->mapHoldFailure($prepared['reason']);
        }

        /** @var array<string, mixed> $payload */
        $payload = $prepared['payload'];
        $eventId = (int) $payload['event_id'];

        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'reason' => 'event_not_found', 'http_status' => 404];
        }

        $stubUnit = (float) config('services.order.stub_unit_price', 25.0);

        try {
            $order = DB::transaction(function () use ($user, $holdUuid, $payload, $eventId, $stubUnit) {
                if (Order::query()->where('hold_uuid', $holdUuid)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('order_already_exists');
                }

                $seatIdsPayload = [];
                foreach ($payload['seat_ids'] as $sid) {
                    $seatIdsPayload[] = (int) $sid;
                }

                $seats = Seat::query()
                    ->where('current_hold_id', $holdUuid)
                    ->where('event_id', $eventId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $idsDb = [];
                foreach ($seats as $seat) {
                    $idsDb[] = (int) $seat->id;
                }
                sort($idsDb);
                $idsSorted = $seatIdsPayload;
                sort($idsSorted);

                if ($idsDb !== $idsSorted || count($idsDb) === 0) {
                    throw new \RuntimeException('seat_mismatch');
                }

                foreach ($seats as $seat) {
                    if ($seat->status !== 'held') {
                        throw new \RuntimeException('seat_not_held');
                    }
                    if ($seat->held_until !== null && $seat->held_until->isPast()) {
                        throw new \RuntimeException('hold_expired');
                    }
                }

                $this->seatHoldService->persistUserOnHold($holdUuid, $payload, (int) $user->id);

                $total = round($stubUnit * count($seatIdsPayload), 2);

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'event_id' => $eventId,
                    'hold_uuid' => $holdUuid,
                    'state' => Order::STATE_PENDING_PAYMENT,
                    'currency' => 'EUR',
                    'total_amount' => $total,
                ]);

                foreach ($seatIdsPayload as $sid) {
                    OrderLine::query()->create([
                        'order_id' => $order->id,
                        'seat_id' => $sid,
                        'unit_price' => $stubUnit,
                    ]);
                }

                return $order->fresh(['orderLines']);
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'order_already_exists') {
                return [
                    'ok' => false,
                    'reason' => 'order_already_exists',
                    'http_status' => 409,
                ];
            }

            return $this->mapTransactionFailure($msg);
        }

        $payment = $this->paymentStubService->createPaymentIntent($order);

        return [
            'ok' => true,
            'order' => $order,
            'payment' => $payment,
        ];
    }

    /**
     * @param  array{ok: false, reason: string}  $prepared
     * @return array{ok: false, reason: string, http_status: int}
     */
    private function mapHoldFailure(string $reason): array
    {
        $map = [
            'hold_not_found' => 404,
            'session_mismatch' => 403,
            'hold_expired' => 410,
            'user_mismatch' => 403,
        ];

        return [
            'ok' => false,
            'reason' => $reason,
            'http_status' => $map[$reason] ?? 400,
        ];
    }

    private function mapTransactionFailure(string $message): array
    {
        $map = [
            'seat_mismatch' => 409,
            'seat_not_held' => 409,
            'hold_expired' => 410,
            'order_already_exists' => 409,
        ];

        return [
            'ok' => false,
            'reason' => $message,
            'http_status' => $map[$message] ?? 400,
        ];
    }
}
