<?php

namespace App\Services\Admin;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Order;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Resum del panell admin (mètriques + recompte d’esdeveniments i comandes pagades).
 */
class AdminDashboardSummaryService
{
    public function __construct(
        private readonly AdminDashboardMetricsService $adminDashboardMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSummaryPayload(): array
    {
        $metrics = $this->adminDashboardMetrics->buildSummaryPayload();
        $payload = $metrics;
        $payload['events_total'] = Event::query()->count();
        $payload['orders_paid'] = Order::query()->where('state', Order::STATE_PAID)->count();
        $payload['generated_at'] = now()->toIso8601String();

        return $payload;
    }
}
