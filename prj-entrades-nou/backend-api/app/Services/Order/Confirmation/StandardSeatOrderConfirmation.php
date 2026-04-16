<?php

namespace App\Services\Order\Confirmation;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Hold\SeatHoldService;
use App\Services\Ticket\JwtTicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Confirmació amb hold clàssic (seients `seats` + `hold_uuid`).
 */
class StandardSeatOrderConfirmation
{
    public function __construct(
        private readonly SeatHoldService $seatHoldService,
        private readonly JwtTicketService $jwtTicketService,
    ) {}

    /**
     * A. Transacció: bloqueig comanda i seients en estat held vàlid.
     * B. Marca seients com a venuts, crea entrades JWT.
     * C. Si conflicte de seient: marca comanda failed i allibera hold.
     *
     * @return array{ok: true, order: Order}|array{ok: false, http_status: int, reason: string, message?: string}
     */
    public function confirm(User $user, Order $order, string $holdUuid): array
    {
        $failed = false;

        try {
            DB::transaction(function () use ($order, $holdUuid, $user) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->state !== Order::STATE_PENDING_PAYMENT) {
                    throw new \RuntimeException('invalid_state');
                }

                $seatIdRows = OrderLine::query()
                    ->where('order_id', $locked->id)
                    ->orderBy('id')
                    ->pluck('seat_id');
                $lineSeatIds = [];
                foreach ($seatIdRows as $id) {
                    $lineSeatIds[] = (int) $id;
                }

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
