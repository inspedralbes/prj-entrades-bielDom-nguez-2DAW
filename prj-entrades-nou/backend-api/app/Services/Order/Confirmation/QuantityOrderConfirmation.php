<?php

namespace App\Services\Order\Confirmation;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticket\JwtTicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Confirmació de comandes per quantitat (sense seients físics).
 */
class QuantityOrderConfirmation
{
    public function __construct(
        private readonly JwtTicketService $jwtTicketService,
    ) {}

    /**
     * A. Valida estat i quantitat.
     * B. Crea entrades JWT per cada línia.
     * C. Carrega comanda fresca amb relacions.
     *
     * @return array{ok: true, order: Order}|array{ok: false, http_status: int, reason: string, message?: string}
     */
    public function confirm(User $user, Order $order): array
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
