<?php

namespace Tests\Feature;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\Auth\JwtTokenService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AdminDashboardGraphqlAndLogsTest extends TestCase
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
    }

    public function test_graphql_revenue_series_forbidden_for_non_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($user->fresh());

        $response = $this->postJson('/api/graphql', [
            'query' => 'query { adminDashboardRevenueByDay(days: 7) { date revenue } }',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    public function test_graphql_series_ok_for_admin(): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $response = $this->postJson('/api/graphql', [
            'query' => 'query { adminDashboardRevenueByDay { date revenue } adminDashboardOrdersPaidByDay { date count } }',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.adminDashboardRevenueByDay.0.date', fn ($v) => is_string($v));
        $response->assertJsonPath('data.adminDashboardOrdersPaidByDay.0.count', fn ($v) => is_int($v));
        $json = $response->json();
        $this->assertCount(30, $json['data']['adminDashboardRevenueByDay']);
        $this->assertCount(30, $json['data']['adminDashboardOrdersPaidByDay']);
    }

    public function test_admin_logs_list_forbidden_for_non_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($user->fresh());

        $response = $this->getJson('/api/admin/logs', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_patch_creates_audit_log_and_logs_endpoint(): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $venue = Venue::factory()->create();
        $event = Event::factory()->create(['venue_id' => $venue->id]);

        $this->patchJson('/api/admin/events/'.$event->id, [
            'tm_sync_paused' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $logs = $this->getJson('/api/admin/logs', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $logs->assertOk();
        $logs->assertJsonPath('meta.per_page', 10);
        $logs->assertJsonPath('data.0.admin_name', $admin->profileDisplayName());
        $logs->assertJsonPath('data.0.summary', fn ($s) => is_string($s) && str_contains($s, 'actualitzat'));
    }
}
