<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class TicketQrApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.order.stub_unit_price' => 10.0]);
        config(['jwt.ticket_ttl_seconds' => 900]);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_qr_requires_auth(): void
    {
        $this->getJson('/api/tickets/550e8400-e29b-41d4-a716-446655440000/qr')
            ->assertStatus(401);
    }

    public function test_qr_returns_svg_for_owner(): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response(
                '<svg xmlns="http://www.w3.org/2000/svg"><rect /></svg>',
                200,
                ['Content-Type' => 'image/svg+xml'],
            ),
        ]);

        [$token, $ticketId] = $this->createPaidOrderWithTicket();

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get("/api/tickets/{$ticketId}/qr");

        $res->assertOk();
        $res->assertHeader('content-type', 'image/svg+xml; charset=utf-8');
        $this->assertStringContainsString('<svg', $res->getContent());
    }

    public function test_qr_404_for_other_user(): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"/>', 200, ['Content-Type' => 'image/svg+xml']),
        ]);

        [$token, $ticketId] = $this->createPaidOrderWithTicket();

        $other = $this->postJson('/api/auth/register', [
            'username' => 'altre_qr',
            'email' => 'altre_qr@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $other->assertStatus(201);
        $otherToken = $other->json('token');

        $this->withHeaders(['Authorization' => 'Bearer '.$otherToken])
            ->get("/api/tickets/{$ticketId}/qr")
            ->assertStatus(404);
    }

    public function test_qr_409_when_ticket_used(): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"/>', 200),
        ]);

        [$token, $ticketId] = $this->createPaidOrderWithTicket();

        $t = Ticket::query()->whereKey($ticketId)->firstOrFail();
        $t->status = Ticket::STATUS_UTILITZADA;
        $t->save();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get("/api/tickets/{$ticketId}/qr")
            ->assertStatus(409);
    }

    /**
     * @return array{0: string, 1: string} token, ticket uuid
     */
    private function createPaidOrderWithTicket(): array
    {
        $reg = $this->postJson('/api/auth/register', [
            'username' => 'comp_qr_'.uniqid(),
            'email' => uniqid('c', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $sess = 'sess-qr-'.uniqid();
        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => $sess,
        ]);
        $hold->assertStatus(201);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => $sess,
        ], ['Authorization' => 'Bearer '.$token]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('order_id');

        $confirm = $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $confirm->assertOk();
        $ticketId = $confirm->json('tickets.0.id');
        $this->assertNotEmpty($ticketId);

        return [$token, $ticketId];
    }
}
