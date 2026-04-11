<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class TicketsListApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp (): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.order.stub_unit_price' => 10.0]);
        config(['jwt.ticket_ttl_seconds' => 900]);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Cache::flush();
    }

    public function test_list_requires_auth (): void
    {
        $this->getJson('/api/tickets')->assertStatus(401);
    }

    public function test_list_empty_for_new_user (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Sense entrades',
            'username' => 'sense_entrades',
            'email' => 'sense@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/tickets');

        $res->assertOk();
        $res->assertJsonPath('tickets', []);
    }

    public function test_list_returns_ticket_after_paid_order (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Amb entrada',
            'username' => 'amb_entrada',
            'email' => 'amb@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $event = Event::factory()->create([
            'hold_ttl_seconds' => 240,
            'name' => 'Concert de prova',
        ]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'external_seat_key' => 'A-12',
        ]);

        $sess = 'sess-list-1';
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

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/tickets');

        $list->assertOk();
        $list->assertJsonCount(1, 'tickets');
        $list->assertJsonPath('tickets.0.id', $ticketId);
        $list->assertJsonPath('tickets.0.status', Ticket::STATUS_VENUDA);
        $list->assertJsonPath('tickets.0.event.name', 'Concert de prova');
        $list->assertJsonPath('tickets.0.event.id', $event->id);
        $list->assertJsonPath('tickets.0.seat.key', 'A-12');
        $list->assertJsonPath('tickets.0.order_id', $orderId);
    }

    public function test_list_does_not_include_other_users_tickets (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Usuari A',
            'username' => 'user_a_list',
            'email' => 'a_list@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $tokenA = $reg->json('token');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $sess = 'sess-list-iso';
        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => $sess,
        ]);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => $sess,
        ], ['Authorization' => 'Bearer '.$tokenA]);
        $orderId = $orderRes->json('order_id');
        $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk();

        $regB = $this->postJson('/api/auth/register', [
            'name' => 'Usuari B',
            'username' => 'user_b_list',
            'email' => 'b_list@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $tokenB = $regB->json('token');

        $listB = $this->withHeaders(['Authorization' => 'Bearer '.$tokenB])
            ->getJson('/api/tickets');
        $listB->assertOk();
        $listB->assertJsonCount(0, 'tickets');
    }
}
