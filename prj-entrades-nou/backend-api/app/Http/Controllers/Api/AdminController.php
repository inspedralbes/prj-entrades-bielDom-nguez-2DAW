<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Services\Socket\InternalSocketNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Esborranys T035: panell / import — respostes mínimes fins a T052.
 */
class AdminController extends Controller
{
    public function __construct (
        private readonly InternalSocketNotifier $socketNotifier,
    ) {}

    public function summary (Request $request): JsonResponse
    {
        $this->socketNotifier->emitMetricsStub([
            'events_total' => Event::query()->count(),
            'generated_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'stub' => true,
            'events_total' => Event::query()->count(),
            'orders_paid' => Order::query()->where('state', Order::STATE_PAID)->count(),
        ]);
    }

    public function discoverySync (Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'accepted',
            'stub' => true,
            'message' => 'Sincronització Discovery Feed no implementada (esborrany).',
        ], 202);
    }
}
