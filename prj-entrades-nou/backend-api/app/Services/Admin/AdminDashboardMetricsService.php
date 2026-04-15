<?php

namespace App\Services\Admin;

use App\Models\Order;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

/**
 * Mètriques agregades per al dashboard d’administració (font: PostgreSQL via Eloquent).
 */
class AdminDashboardMetricsService
{
    public const CACHE_KEY_LAST_DISCOVERY = 'admin_last_discovery_sync';

    /**
     * Desa el resultat de l’última execució Discovery per mostrar alertes al dashboard.
     *
     * @param  array{
     *   inserted: int,
     *   skipped_no_venue: int,
     *   skipped_existing: int,
     *   pages_fetched: int,
     *   errors: array<int, string>
     * }  $result
     */
    public function recordDiscoverySyncResult(array $result): void
    {
        $errors = [];
        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $err) {
                if (is_string($err)) {
                    $errors[] = $err;
                }
            }
        }

        $payload = [
            'finished_at' => now()->toIso8601String(),
            'inserted' => (int) ($result['inserted'] ?? 0),
            'skipped_no_venue' => (int) ($result['skipped_no_venue'] ?? 0),
            'skipped_existing' => (int) ($result['skipped_existing'] ?? 0),
            'pages_fetched' => (int) ($result['pages_fetched'] ?? 0),
            'errors' => $errors,
        ];

        Cache::put(self::CACHE_KEY_LAST_DISCOVERY, $payload, now()->addDays(30));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummaryPayload(): array
    {
        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);
        $start = Carbon::now($tz)->startOfDay();
        $end = Carbon::now($tz);

        $revenueToday = Order::query()
            ->where('state', Order::STATE_PAID)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<=', $end)
            ->sum('total_amount');

        $revenueStr = '0.00';
        if ($revenueToday !== null) {
            $revenueStr = number_format((float) $revenueToday, 2, '.', '');
        }

        $pendingPaymentCount = Order::query()
            ->where('state', Order::STATE_PENDING_PAYMENT)
            ->count();

        $syncAlerts = $this->buildSyncAlertsFromCache();

        $online = $this->countOnlineUsersFromPresenceZset();

        return [
            'stub' => false,
            'revenue_today' => $revenueStr,
            'pending_payment_count' => $pendingPaymentCount,
            'sync_alerts' => $syncAlerts,
            'online_users' => $online,
        ];
    }

    private function countOnlineUsersFromPresenceZset(): int
    {
        try {
            $conn = Redis::connection();
            $now = time();
            $cutoff = $now - 120;
            $conn->zremrangebyscore('presence:online_ts', '-inf', $cutoff);

            return (int) $conn->zcard('presence:online_ts');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, array{message: string, severity: string, at: string}>
     */
    private function buildSyncAlertsFromCache(): array
    {
        $cached = Cache::get(self::CACHE_KEY_LAST_DISCOVERY);
        if (! is_array($cached)) {
            return [];
        }

        $at = '';
        if (isset($cached['finished_at']) && is_string($cached['finished_at'])) {
            $at = $cached['finished_at'];
        }

        $out = [];
        if (isset($cached['errors']) && is_array($cached['errors'])) {
            foreach ($cached['errors'] as $msg) {
                if (! is_string($msg)) {
                    continue;
                }
                if ($msg === '') {
                    continue;
                }
                $row = [
                    'message' => $msg,
                    'severity' => 'error',
                    'at' => $at,
                ];
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, revenue: string}>
     */
    public function revenueByDay(int $days = 30): array
    {
        $points = [];
        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::now($tz)->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $revenue = Order::query()
                ->where('state', Order::STATE_PAID)
                ->where('updated_at', '>=', $start)
                ->where('updated_at', '<=', $end)
                ->sum('total_amount');

            $points[] = [
                'date' => $day->toDateString(),
                'revenue' => number_format((float) ($revenue ?? 0), 2, '.', ''),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    public function ordersPaidByDay(int $days = 30): array
    {
        $points = [];
        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::now($tz)->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $count = Order::query()
                ->where('state', Order::STATE_PAID)
                ->where('updated_at', '>=', $start)
                ->where('updated_at', '<=', $end)
                ->count();

            $points[] = [
                'date' => $day->toDateString(),
                'count' => (int) $count,
            ];
        }

        return $points;
    }
}
