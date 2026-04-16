<?php

namespace App\Services\Payment;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;
use Illuminate\Support\Str;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Passarel·la de pagament simulada (mode dev / proves) per a T020.
 */
class PaymentStubService
{
    /**
     * @return array{provider: string, client_secret: string, status: string}
     */
    public function createPaymentIntent(Order $order): array
    {
        return [
            'provider' => 'stub',
            'client_secret' => 'stub_cs_'.$order->id.'_'.Str::lower(Str::random(24)),
            'status' => 'requires_payment_method',
        ];
    }
}
