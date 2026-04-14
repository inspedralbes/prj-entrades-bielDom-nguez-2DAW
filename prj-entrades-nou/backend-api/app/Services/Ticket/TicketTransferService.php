<?php

namespace App\Services\Ticket;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\Notification\SocialNotificationService;
use App\Services\Social\FriendshipQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketTransferService
{
    public function __construct(
        private readonly FriendshipQuery $friendshipQuery,
        private readonly SocialNotificationService $socialNotificationService,
    )
    {
    }

    /**
     * Transferència de propietat. Si la comanda té una sola línia, canvia el titular de la comanda sencer;
     * si en té diverses, es crea una comanda nova per al destinatari i només es mou la línia d’aquesta entrada.
     * Invalida credencial anterior via nous identificadors (T036).
     *
     * @return array{ok: true, ticket: Ticket}|array{ok: false, http_status: int, message: string}
     */
    public function transfer(User $from, Ticket $ticket, User $to): array
    {
        if ((int) $from->id === (int) $to->id) {
            return ['ok' => false, 'http_status' => 422, 'message' => 'El destinatari ha de ser un altre usuari.'];
        }

        if (!$this->friendshipQuery->areFriends($from, $to)) {
            return ['ok' => false, 'http_status' => 403, 'message' => 'Cal una amistat acceptada per transferir l’entrada.'];
        }

        $ticket->loadMissing(['orderLine.order']);
        $line = $ticket->orderLine;
        $order = $line?->order;
        if ($order === null) {
            return ['ok' => false, 'http_status' => 404, 'message' => 'Comanda no trobada.'];
        }

        if ((int) $order->user_id !== (int) $from->id) {
            return ['ok' => false, 'http_status' => 403, 'message' => 'No ets el titular d’aquesta entrada.'];
        }

        if ($ticket->status !== Ticket::STATUS_VENUDA) {
            return ['ok' => false, 'http_status' => 409, 'message' => 'Només es poden transferir entrades vàlides (no usades).'];
        }

        $ttl = (int) config('jwt.ticket_ttl_seconds', 900);

        try {
            DB::transaction(function () use ($ticket, $order, $line, $from, $to, $ttl) {
                Order::query()->whereKey($order->id)->lockForUpdate()->first();
                $t = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();
                if ($t === null || $t->status !== Ticket::STATUS_VENUDA) {
                    throw new \RuntimeException('state');
                }

                $linesCount = OrderLine::query()->where('order_id', $order->id)->count();
                if ($linesCount < 1) {
                    throw new \RuntimeException('state');
                }

                TicketTransfer::query()->create([
                    'ticket_id' => $t->id,
                    'from_user_id' => $from->id,
                    'to_user_id' => $to->id,
                    'status' => 'completed',
                ]);

                if ($linesCount === 1) {
                    $order->user_id = $to->id;
                    $order->save();
                } else {
                    $lockedLine = OrderLine::query()->whereKey($line->id)->lockForUpdate()->first();
                    if ($lockedLine === null || (int) $lockedLine->order_id !== (int) $order->id) {
                        throw new \RuntimeException('state');
                    }

                    $movedUnit = (float) $lockedLine->unit_price;
                    $newOrder = Order::query()->create([
                        'user_id' => $to->id,
                        'event_id' => (int) $order->event_id,
                        'hold_uuid' => null,
                        'state' => Order::STATE_PAID,
                        'currency' => $order->currency,
                        'total_amount' => round($movedUnit, 2),
                        'quantity' => 1,
                    ]);

                    $lockedLine->order_id = $newOrder->id;
                    $lockedLine->save();

                    $sum = 0.0;
                    $cnt = 0;
                    $remaining = OrderLine::query()->where('order_id', $order->id)->get();
                    foreach ($remaining as $rl) {
                        $sum = $sum + (float) $rl->unit_price;
                        $cnt = $cnt + 1;
                    }
                    $order->total_amount = round($sum, 2);
                    $order->quantity = $cnt;
                    $order->save();
                }

                $t->public_uuid = (string) Str::uuid();
                $t->jwt_expires_at = now()->addSeconds($ttl);
                $t->qr_payload_ref = null;
                $t->save();
            });
        } catch (\RuntimeException) {
            return ['ok' => false, 'http_status' => 409, 'message' => 'Estat de l’entrada incompatible.'];
        }

        $fresh = $ticket->fresh();
        if ($fresh !== null) {
            $this->socialNotificationService->recordTicketTransfer($from, $to, $fresh);
        }

        return ['ok' => true, 'ticket' => $fresh];
    }
}
