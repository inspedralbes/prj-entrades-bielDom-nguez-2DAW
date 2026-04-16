<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Socket\InternalSocketNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Ping de presència: ZSET Redis + emissió Socket cap al panell admin (temps real).
 */
class PresenceController extends Controller
{
    public function __construct(
        private readonly InternalSocketNotifier $socketNotifier,
        private readonly AdminDashboardMetricsService $adminDashboardMetrics,
    ) {}

    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $n = 0;
        try {
            $conn = Redis::connection();
            $now = time();
            $cutoff = $now - 120;
            $conn->zadd('presence:online_ts', $now, (string) $user->id);
            $conn->zremrangebyscore('presence:online_ts', '-inf', $cutoff);
            $n = (int) $conn->zcard('presence:online_ts');
        } catch (\Throwable) {
            $n = 0;
        }

        try {
            $payload = $this->adminDashboardMetrics->buildFullDashboardPayload();
            $payload['online_users'] = $n;
            $payload['generated_at'] = now()->toIso8601String();
            $this->socketNotifier->emitMetricsStub($payload);
        } catch (\Throwable) {
            /* sense socket a tests locals */
        }

        return response()->json([
            'ok' => true,
            'online_users' => $n,
        ]);
    }
}
