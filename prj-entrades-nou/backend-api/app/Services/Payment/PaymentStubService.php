<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Str;

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
