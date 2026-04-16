<?php

namespace Tests\Feature;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Auth\JwtTokenService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['admin.business_timezone' => 'UTC']);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_admin_summary_revenue_today_and_pending_payment(): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $user = User::factory()->create();
        $event = Event::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'hold_uuid' => null,
            'state' => Order::STATE_PAID,
            'currency' => 'EUR',
            'total_amount' => 33.5,
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'hold_uuid' => null,
            'state' => Order::STATE_PENDING_PAYMENT,
            'currency' => 'EUR',
            'total_amount' => 10.0,
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/summary');

        $res->assertOk();
        $res->assertJsonPath('revenue_today', '33.50');
        $res->assertJsonPath('pending_payment_count', 1);
    }

    public function test_sync_alerts_populated_after_record_discovery(): void
    {
        $svc = app(AdminDashboardMetricsService::class);
        $svc->recordDiscoverySyncResult([
            'inserted' => 1,
            'skipped_no_venue' => 0,
            'skipped_existing' => 0,
            'pages_fetched' => 1,
            'errors' => ['Error de prova TM'],
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/summary');

        $res->assertOk();
        $alerts = $res->json('sync_alerts');
        $this->assertIsArray($alerts);
        $this->assertGreaterThan(0, count($alerts));
        $this->assertSame('Error de prova TM', $alerts[0]['message']);
        $this->assertSame('error', $alerts[0]['severity']);
    }
}
