<?php

namespace Tests\Feature;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        $this->seed(RoleSeeder::class);
    }

    public function test_analytics_summary_requires_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($user->fresh());

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/analytics/summary?date_from='.$from.'&date_to='.$to)
            ->assertStatus(403);
    }

    public function test_analytics_invalid_range_returns_422(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/analytics/summary?date_from=2025-12-10&date_to=2025-01-01')
            ->assertStatus(422);
    }

    public function test_analytics_summary_and_events_with_paid_order(): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $buyer = User::factory()->create();
        $buyer->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create([
            'name' => 'Concert prova',
        ]);

        $order = new Order;
        $order->user_id = $buyer->id;
        $order->event_id = $event->id;
        $order->state = Order::STATE_PAID;
        $order->currency = 'EUR';
        $order->total_amount = 40.00;
        $order->save();

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/analytics/summary?date_from='.$from.'&date_to='.$to)
            ->assertOk()
            ->assertJsonPath('total_revenue_eur', 40)
            ->assertJsonStructure(['total_revenue_eur', 'period']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/analytics/events?date_from='.$from.'&date_to='.$to)
            ->assertOk()
            ->assertJsonPath('events.0.event_id', $event->id)
            ->assertJsonPath('events.0.revenue_eur', 40);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/analytics/categories/occupancy?date_from='.$from.'&date_to='.$to)
            ->assertOk()
            ->assertJsonStructure(['categories']);
    }
}
