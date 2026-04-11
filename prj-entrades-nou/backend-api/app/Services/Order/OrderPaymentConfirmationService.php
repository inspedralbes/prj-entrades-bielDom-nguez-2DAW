<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Ticket\JwtTicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPaymentConfirmationService
{
    public function __construct (
        private readonly SeatHoldService $seatHoldService,
        private readonly JwtTicketService $jwtTicketService,
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
}
