<?php

namespace App\Services\Admin;

use App\Http\Resources\AdminLogResource;
use App\Models\AdminLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
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

    public const MAX_CHART_DAYS = 30;

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
    public function recordDiscoverySyncResult (array $result): void
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
     * Payload base (KPIs sense recomptes globals ni logs).
     *
     * @return array<string, mixed>
     */
    public function buildSummaryPayload (): array
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

    /**
     * Payload complet del panell admin (summary HTTP + socket): inclou KPIs extres, logs recents i metadades.
     *
     * @return array<string, mixed>
     */
    public function buildFullDashboardPayload (): array
    {
        $metrics = $this->buildSummaryPayload();

        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);
        $start = Carbon::now($tz)->startOfDay();
        $end = Carbon::now($tz);

        $ordersPaidToday = Order::query()
            ->where('state', Order::STATE_PAID)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<=', $end)
            ->count();

        // Tiquets emesos (historial): totes les files de tiquets venuts al sistema.
        $ticketsSoldTotal = Ticket::query()->count();

        $recentLogs = AdminLog::query()
            ->with('adminUser')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recent = [];
        foreach ($recentLogs as $log) {
            $recent[] = (new AdminLogResource($log))->resolve();
        }

        $metrics['orders_paid_today'] = $ordersPaidToday;
        $metrics['tickets_sold_total'] = $ticketsSoldTotal;
        $metrics['recent_admin_logs'] = $recent;
        $metrics['events_total'] = Event::query()->count();
        $metrics['orders_paid'] = Order::query()->where('state', Order::STATE_PAID)->count();
        $metrics['generated_at'] = now()->toIso8601String();

        return $metrics;
    }

    /**
     * Sèrie d’ingressos per dia natural (TZ negoci), darrers N dies (1–30).
     *
     * @return array<int, array{date: string, revenue: string}>
     */
    public function revenueByDay (int $days): array
    {
        if ($days < 1) {
            $days = 1;
        }
        if ($days > self::MAX_CHART_DAYS) {
            $days = self::MAX_CHART_DAYS;
        }

        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayStart = Carbon::now($tz)->copy()->subDays($i)->startOfDay();
            $dayEnd = Carbon::now($tz)->copy()->subDays($i)->endOfDay();
            $sum = Order::query()
                ->where('state', Order::STATE_PAID)
                ->where('updated_at', '>=', $dayStart)
                ->where('updated_at', '<=', $dayEnd)
                ->sum('total_amount');
            $rev = '0.00';
            if ($sum !== null) {
                $rev = number_format((float) $sum, 2, '.', '');
            }
            $row = [
                'date' => $dayStart->format('Y-m-d'),
                'revenue' => $rev,
            ];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Recompte de comandes pagades per dia natural, darrers N dies.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function ordersPaidByDay (int $days): array
    {
        if ($days < 1) {
            $days = 1;
        }
        if ($days > self::MAX_CHART_DAYS) {
            $days = self::MAX_CHART_DAYS;
        }

        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $tz = new DateTimeZone($tzName);
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayStart = Carbon::now($tz)->copy()->subDays($i)->startOfDay();
            $dayEnd = Carbon::now($tz)->copy()->subDays($i)->endOfDay();
            $c = Order::query()
                ->where('state', Order::STATE_PAID)
                ->where('updated_at', '>=', $dayStart)
                ->where('updated_at', '<=', $dayEnd)
                ->count();
            $row = [
                'date' => $dayStart->format('Y-m-d'),
                'count' => (int) $c,
            ];
            $out[] = $row;
        }

        return $out;
    }

    private function countOnlineUsersFromPresenceZset (): int
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
    private function buildSyncAlertsFromCache (): array
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
}
