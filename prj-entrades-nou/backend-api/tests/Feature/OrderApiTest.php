<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Zone;
use App\Services\Ticket\JwtTicketService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.order.stub_unit_price' => 10.0]);
        config(['jwt.ticket_ttl_seconds' => 900]);
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_create_pending_payment_order_requires_auth(): void
    {
        $this->postJson('/api/orders', [
            'hold_id' => '550e8400-e29b-41d4-a716-446655440000',
            'anonymous_session_id' => 'x',
        ])->assertStatus(401);
    }

    public function test_create_pending_payment_order_from_hold(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador1',
            'email' => 'c1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);
        $s2 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id, $s2->id],
            'anonymous_session_id' => 'sess-order-1',
        ]);
        $hold->assertStatus(201);
        $holdId = $hold->json('hold_id');

        $order = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-order-1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $order->assertStatus(201);
        $order->assertJsonPath('state', Order::STATE_PENDING_PAYMENT);
        $order->assertJsonPath('currency', 'EUR');
        $order->assertJsonPath('total_amount', '20.00');
        $order->assertJsonStructure([
            'order_id',
            'payment' => ['provider', 'client_secret', 'status'],
            'lines',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->json('order_id'),
            'user_id' => $reg->json('user.id'),
            'event_id' => $event->id,
            'hold_uuid' => $holdId,
            'state' => Order::STATE_PENDING_PAYMENT,
        ]);
    }

    public function test_duplicate_order_same_hold_returns_409(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador2',
            'email' => 'c2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'sess-dup',
        ]);
        $holdId = $hold->json('hold_id');

        $headers = ['Authorization' => 'Bearer '.$token];
        $body = [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-dup',
        ];

        $this->postJson('/api/orders', $body, $headers)->assertStatus(201);
        $this->postJson('/api/orders', $body, $headers)->assertStatus(409);
    }

    public function test_wrong_anonymous_session_returns_403(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador3',
            'email' => 'c3@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'sess-ok',
        ]);
        $holdId = $hold->json('hold_id');

        $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-mal',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(403);
    }

    public function test_confirm_payment_marks_paid_and_seats_sold(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador4',
            'email' => 'c4@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'sess-confirm-ok',
        ]);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-confirm-ok',
        ], ['Authorization' => 'Bearer '.$token]);
        $orderId = $orderRes->json('order_id');

        $confirm = $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $confirm->assertOk();
        $confirm->assertJsonPath('state', Order::STATE_PAID);
        $confirm->assertJsonCount(1, 'tickets');
        $confirm->assertJsonPath('tickets.0.status', Ticket::STATUS_VENUDA);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'state' => Order::STATE_PAID]);

        $this->assertDatabaseHas('tickets', [
            'status' => Ticket::STATUS_VENUDA,
        ]);

        $tid = $confirm->json('tickets.0.id');
        $ticketRow = Ticket::query()->whereKey($tid)->first();
        $this->assertNotNull($ticketRow);
        $this->assertNotEmpty($ticketRow->public_uuid);

        $buyer = User::query()->findOrFail($reg->json('user.id'));
        $jwtSvc = app(JwtTicketService::class);
        $jwt = $jwtSvc->issueForTicket($ticketRow, $buyer, $event->id);
        $decoded = $jwtSvc->decode($jwt);
        $this->assertSame($ticketRow->id, $decoded->jti);
        $this->assertSame($ticketRow->public_uuid, $decoded->pub);
        $this->assertSame((string) $buyer->id, $decoded->sub);
        $this->assertSame($event->id, $decoded->evt);
        $this->assertSame('ticket', $decoded->typ);

        $s1->refresh();
        $this->assertSame('sold', $s1->status);
        $this->assertNull($s1->current_hold_id);
    }

    public function test_confirm_payment_seat_unavailable_releases_hold_and_exact_message(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador5',
            'email' => 'c5@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'sess-fail',
        ]);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-fail',
        ], ['Authorization' => 'Bearer '.$token]);
        $orderId = $orderRes->json('order_id');

        $s1->refresh();
        $this->assertSame('held', $s1->status);

        // Simula indisponibilitat (SoT): el seient ja no està en hold vàlid.
        $s1->update([
            'status' => 'available',
            'current_hold_id' => null,
            'held_until' => null,
        ]);

        $bad = $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $bad->assertStatus(409);
        $bad->assertJsonPath('message', 'Seient ja no disponible');
        $bad->assertJsonPath('reason', 'seat_unavailable');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'state' => Order::STATE_FAILED]);

        $s1->refresh();
        $this->assertSame('available', $s1->status);
    }

    public function test_confirm_payment_twice_second_invalid_state(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'comprador6',
            'email' => 'c6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'sess-twice',
        ]);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => 'sess-twice',
        ], ['Authorization' => 'Bearer '.$token]);
        $orderId = $orderRes->json('order_id');

        $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(409)->assertJsonPath('reason', 'invalid_state');
    }
}
