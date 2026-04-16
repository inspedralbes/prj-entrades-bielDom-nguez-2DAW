<?php

namespace App\Observers;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Order;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Socket\InternalSocketNotifier;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('state') && ! $order->wasChanged('total_amount')) {
            return;
        }

        try {
            $payload = app(AdminDashboardMetricsService::class)->buildFullDashboardPayload();
            app(InternalSocketNotifier::class)->emitMetricsStub($payload);
        } catch (\Throwable) {
            // sense socket en tests locals
        }
    }
}
