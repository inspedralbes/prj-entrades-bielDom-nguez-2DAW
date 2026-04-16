<?php

namespace App\Services\Order;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\Order\Confirmation\CinemaSeatOrderConfirmation;
use App\Services\Order\Confirmation\QuantityOrderConfirmation;
use App\Services\Order\Confirmation\StandardSeatOrderConfirmation;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Orquestrador de confirmació de pagament: delega segons tipus de comanda (hold clàssic, cinema, quantitat).
 */
class OrderPaymentConfirmationService
{
    public function __construct(
        private readonly StandardSeatOrderConfirmation $standardSeatOrderConfirmation,
        private readonly CinemaSeatOrderConfirmation $cinemaSeatOrderConfirmation,
        private readonly QuantityOrderConfirmation $quantityOrderConfirmation,
    ) {}

    /**
     * Confirmació final (stub passarel·la): SoT PostgreSQL; si falla, missatge fix i alliberament de hold.
     *
     * @return array{ok: true, order: Order}|array{ok: false, http_status: int, reason: string, message?: string}
     */
    public function confirmFinalPayment(User $user, Order $order): array
    {
        if ((int) $order->user_id !== (int) $user->id) {
            return ['ok' => false, 'http_status' => 403, 'reason' => 'forbidden'];
        }

        $holdUuid = $order->hold_uuid;
        if ($holdUuid === null || $holdUuid === '') {
            if ($order->quantity !== null && $order->quantity > 0) {
                return $this->quantityOrderConfirmation->confirm($user, $order);
            }

            if ($this->orderHasCinemaSeatKeys($order)) {
                return $this->cinemaSeatOrderConfirmation->confirm($user, $order);
            }

            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        if ($order->state !== Order::STATE_PENDING_PAYMENT) {
            return ['ok' => false, 'http_status' => 409, 'reason' => 'invalid_state'];
        }

        return $this->standardSeatOrderConfirmation->confirm($user, $order, $holdUuid);
    }

    //================================ LÒGICA PRIVADA ================

    private function orderHasCinemaSeatKeys(Order $order): bool
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
}
