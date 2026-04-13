<?php

namespace App\Services\Order;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Seatmap\EventSeatHoldService;
use App\Services\Ticket\JwtTicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPaymentConfirmationService
{
    public function __construct (
        private readonly SeatHoldService $seatHoldService,
        private readonly JwtTicketService $jwtTicketService,
        private readonly EventSeatHoldService $eventSeatHoldService,
    ) {}

    /**
     * Confirmació final (stub passarel·la): SoT PostgreSQL; si falla, missatge fix i alliberament de hold.
     *
     * @return array{ok: true, order: Order}|array{ok: false, http_status: int, reason: string, message?: string}
     */
    public function confirmFinalPayment (User $user, Order $order): array
    {
        if ((int) $order->user_id !== (int) $user->id) {
            return ['ok' => false, 'http_status' => 403, 'reason' => 'forbidden'];
        }

        $holdUuid = $order->hold_uuid;
        if ($holdUuid === null || $holdUuid === '') {
            if ($order->quantity !== null && $order->quantity > 0) {
                return $this->confirmQuantityOrder($user, $order);
            }

            if ($this->orderHasCinemaSeatKeys($order)) {
                return $this->confirmCinemaSeatOrder($user, $order);
            }

            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        if ($order->state !== Order::STATE_PENDING_PAYMENT) {
            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        $failed = false;

        try {
            DB::transaction(function () use ($order, $holdUuid, $user) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->state !== Order::STATE_PENDING_PAYMENT) {
                    throw new \RuntimeException('invalid_state');
                }

                $lineSeatIds = OrderLine::query()
                    ->where('order_id', $locked->id)
                    ->orderBy('id')
                    ->pluck('seat_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $seats = Seat::query()
                    ->whereIn('id', $lineSeatIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($lineSeatIds)) {
                    throw new \RuntimeException('seat_unavailable');
                }

                foreach ($seats as $seat) {
                    $heldOk = $seat->status === 'held'
                        && (string) $seat->current_hold_id === (string) $holdUuid
                        && ($seat->held_until === null || $seat->held_until->isFuture());

                    if (! $heldOk) {
                        throw new \RuntimeException('seat_unavailable');
                    }
                }

                foreach ($seats as $seat) {
                    $seat->status = 'sold';
                    $seat->current_hold_id = null;
                    $seat->held_until = null;
                    $seat->save();
                }

                $this->seatHoldService->forgetHoldCache($holdUuid);

                $lines = OrderLine::query()
                    ->where('order_id', $locked->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $eventId = (int) $locked->event_id;
                $ttlSeconds = (int) config('jwt.ticket_ttl_seconds', 900);

                foreach ($lines as $line) {
                    $ticket = Ticket::query()->create([
                        'id' => (string) Str::uuid(),
                        'public_uuid' => (string) Str::uuid(),
                        'order_line_id' => $line->id,
                        'status' => Ticket::STATUS_VENUDA,
                        'jwt_expires_at' => now()->addSeconds($ttlSeconds),
                    ]);

                    $this->jwtTicketService->issueForTicket($ticket, $user, $eventId);
                }

                $locked->state = Order::STATE_PAID;
                $locked->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'invalid_state') {
                return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
            }
            if ($e->getMessage() === 'seat_unavailable') {
                $failed = true;
            } else {
                throw $e;
            }
        }

        if ($failed) {
            DB::transaction(function () use ($order, $holdUuid) {
                $o = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($o !== null && $o->state === Order::STATE_PENDING_PAYMENT) {
                    $o->state = Order::STATE_FAILED;
                    $o->save();
                }

                $this->seatHoldService->forceReleaseHold($holdUuid);
            });

            return [
                'ok' => false,
                'http_status' => 409,
                'reason' => 'seat_unavailable',
                'message' => 'Seient ja no disponible',
            ];
        }

        $fresh = Order::query()
            ->with(['orderLines.ticket'])
            ->whereKey($order->id)
            ->firstOrFail();

        return ['ok' => true, 'order' => $fresh];
    }

    private function orderHasCinemaSeatKeys (Order $order): bool
    {
        $lines = OrderLine::query()->where('order_id', $order->id)->get();
        $n = $lines->count();
        for ($i = 0; $i < $n; $i++) {
            $sk = $lines[$i]->seat_key;
            if ($sk !== null && $sk !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Pagament confirmat: marca seients venuts a events.seat_layout, crea entrades, Redis + socket (sold).
     *
     * @return array{ok: true, order: Order}|array{ok: false, http_status: int, reason: string, message?: string}
     */
    private function confirmCinemaSeatOrder (User $user, Order $order): array
    {
        $finalizeKeys = [];
        $finalizeEventId = 0;

        try {
            DB::transaction(function () use ($order, $user, &$finalizeKeys, &$finalizeEventId) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->state !== Order::STATE_PENDING_PAYMENT) {
                    throw new \RuntimeException('invalid_state');
                }

                $event = Event::query()->whereKey($locked->event_id)->lockForUpdate()->first();
                if ($event === null) {
                    throw new \RuntimeException('invalid_state');
                }

                $layout = $event->seat_layout;
                if (! is_array($layout)) {
                    $layout = [];
                }

                $orderLines = OrderLine::query()
                    ->where('order_id', $locked->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $seatKeys = [];
                $m = $orderLines->count();
                for ($j = 0; $j < $m; $j++) {
                    $line = $orderLines[$j];
                    $sk = $line->seat_key;
                    if ($sk === null || $sk === '') {
                        throw new \RuntimeException('invalid_state');
                    }
                    $seatKeys[] = $sk;
                    if ($this->isSeatMarkedSoldInLayout($layout, $sk)) {
                        throw new \RuntimeException('seat_conflict');
                    }
                }

                $nk = count($seatKeys);
                for ($k = 0; $k < $nk; $k++) {
                    $layout[$seatKeys[$k]] = true;
                }
                $event->seat_layout = $layout;
                $event->save();

                $eventId = (int) $locked->event_id;
                $ttlSeconds = (int) config('jwt.ticket_ttl_seconds', 900);

                for ($t = 0; $t < $m; $t++) {
                    $line = $orderLines[$t];
                    $ticket = Ticket::query()->create([
                        'id' => (string) Str::uuid(),
                        'public_uuid' => (string) Str::uuid(),
                        'order_line_id' => $line->id,
                        'status' => Ticket::STATUS_VENUDA,
                        'jwt_expires_at' => now()->addSeconds($ttlSeconds),
                    ]);

                    $this->jwtTicketService->issueForTicket($ticket, $user, $eventId);
                }

                $locked->state = Order::STATE_PAID;
                $locked->save();

                $finalizeKeys = $seatKeys;
                $finalizeEventId = $eventId;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'seat_conflict') {
                return [
                    'ok' => false,
                    'http_status' => 409,
                    'reason' => 'seat_unavailable',
                    'message' => 'Un o més seients ja no estan disponibles',
                ];
            }

            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        if (count($finalizeKeys) > 0 && $finalizeEventId > 0) {
            $this->eventSeatHoldService->finalizeCinemaSeatsAsSold(
                (string) $finalizeEventId,
                (int) $user->id,
                $finalizeKeys
            );
        }

        $fresh = Order::query()
            ->with(['orderLines.ticket'])
            ->whereKey($order->id)
            ->firstOrFail();

        return ['ok' => true, 'order' => $fresh];
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    private function isSeatMarkedSoldInLayout (array $layout, string $seatId): bool
    {
        if (! array_key_exists($seatId, $layout)) {
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

    /**
     * Confirm payment for quantity-based order (no seats).
     */
    private function confirmQuantityOrder (User $user, Order $order): array
    {
        try {
            DB::transaction(function () use ($order, $user) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->state !== Order::STATE_PENDING_PAYMENT) {
                    throw new \RuntimeException('invalid_state');
                }

                $quantity = (int) $locked->quantity;
                if ($quantity < 1) {
                    throw new \RuntimeException('invalid_state');
                }

                $ttlSeconds = (int) config('jwt.ticket_ttl_seconds', 900);

                $orderLines = OrderLine::query()
                    ->where('order_id', $locked->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $eventId = (int) $locked->event_id;

                foreach ($orderLines as $line) {
                    $ticket = Ticket::query()->create([
                        'id' => (string) Str::uuid(),
                        'public_uuid' => (string) Str::uuid(),
                        'order_line_id' => $line->id,
                        'status' => Ticket::STATUS_VENUDA,
                        'jwt_expires_at' => now()->addSeconds($ttlSeconds),
                    ]);

                    $this->jwtTicketService->issueForTicket($ticket, $user, $eventId);
                }

                $locked->state = Order::STATE_PAID;
                $locked->save();
            });
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        $fresh = Order::query()
            ->with(['orderLines.ticket'])
            ->whereKey($order->id)
            ->firstOrFail();

        return ['ok' => true, 'order' => $fresh];
    }
}
