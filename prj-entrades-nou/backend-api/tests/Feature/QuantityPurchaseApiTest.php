<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class QuantityPurchaseApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
    }

    public function test_create_quantity_order_requires_auth(): void
    {
        $event = Event::factory()->create();

        $response = $this->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(401);
    }

    public function test_create_quantity_order_validates_quantity_range(): void
    {
        $user = $this->createUserWithToken();
        $event = Event::factory()->create();

        $response = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.quantity.0', 'The quantity field must be at least 1.');
    }

    public function test_create_quantity_order_max_6(): void
    {
        $user = $this->createUserWithToken();
        $event = Event::factory()->create();

        $response = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 7,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.quantity.0', 'The quantity field must not be greater than 6.');
    }

    public function test_create_quantity_order_creates_order(): void
    {
        $user = $this->createUserWithToken();
        $event = Event::factory()->create();

        $response = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('quantity', 3);
        $response->assertJsonStructure([
            'order_id',
            'state',
            'event_id',
            'quantity',
            'currency',
            'total_amount',
            'payment',
        ]);
    }

    public function test_create_quantity_order_calculates_total_correctly(): void
    {
        config(['services.order.stub_unit_price' => 25.0]);
        $user = $this->createUserWithToken();
        $event = Event::factory()->create();

        $response = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 4,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('total_amount', '100.00');
    }

    public function test_create_quantity_order_fails_for_nonexistent_event(): void
    {
        $user = $this->createUserWithToken();

        $response = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => 99999,
            'quantity' => 2,
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('reason', 'event_not_found');
    }

    public function test_order_confirmation_generates_multiple_tickets(): void
    {
        config(['services.order.stub_unit_price' => 25.0]);
        $user = $this->createUserWithToken();
        $event = Event::factory()->create();

        $orderResponse = $this->withToken($user['token'])->postJson('/api/orders/quantity', [
            'event_id' => $event->id,
            'quantity' => 3,
        ]);

        $orderId = $orderResponse->json('order_id');

        $confirmResponse = $this->withToken($user['token'])
            ->postJson("/api/orders/{$orderId}/confirm-payment");

        $confirmResponse->assertStatus(200);

        $ticketCount = Ticket::query()
            ->whereHas('orderLine', static function ($q) use ($orderId) {
                $q->where('order_id', $orderId);
            })
            ->count();

        $this->assertSame(3, $ticketCount);
    }

    private function createUserWithToken(): array
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        return ['user' => $user, 'token' => $token];
    }
}
