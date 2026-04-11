<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Zone;
use App\Services\Auth\JwtTokenService;
use App\Services\Ticket\JwtTicketService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class ValidationScanApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp (): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.order.stub_unit_price' => 10.0]);
        config(['jwt.ticket_ttl_seconds' => 900]);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Cache::flush();
    }

    public function test_scan_requires_auth (): void
    {
        $this->postJson('/api/validation/scan', ['token' => 'x'])->assertStatus(401);
    }

    public function test_scan_forbidden_for_buyer_role (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador',
            'username' => 'buyer_val_'.uniqid(),
            'email' => uniqid('b', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/validation/scan', ['token' => 'eyJhbGciOiJIUzI1NiJ9.e30.x'])
            ->assertStatus(403);
    }

    public function test_scan_success_then_second_scan_400 (): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        [$buyerToken, $ticketJwt, $validatorToken] = $this->createBuyerWithTicketAndValidator();

        $res1 = $this->withHeaders(['Authorization' => 'Bearer '.$validatorToken])
            ->postJson('/api/validation/scan', ['token' => $ticketJwt]);

        $res1->assertOk();
        $res1->assertJsonPath('status', Ticket::STATUS_UTILITZADA);

        $res2 = $this->withHeaders(['Authorization' => 'Bearer '.$validatorToken])
            ->postJson('/api/validation/scan', ['token' => $ticketJwt]);

        $res2->assertStatus(400);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://socket.test/internal/emit'
                && str_contains($request->body(), 'ticket:validated')
                && str_contains($request->body(), 'user:');
        });
    }

    public function test_scan_400_on_garbage_token (): void
    {
        $validatorToken = $this->createValidatorToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$validatorToken])
            ->postJson('/api/validation/scan', ['token' => 'no.un.jwt'])
            ->assertStatus(400);
    }

    /**
     * @return array{0: string, 1: string, 2: string} buyer API token, ticket JWT, validator API token
     */
    private function createBuyerWithTicketAndValidator (): array
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Comprador V',
            'username' => 'buy_v_'.uniqid(),
            'email' => uniqid('bv', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $buyerToken = $reg->json('token');
        $buyer = User::query()->where('email', $reg->json('user.email'))->firstOrFail();

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $sess = 'sess-val-'.uniqid();
        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => $sess,
        ]);
        $hold->assertStatus(201);
        $holdId = $hold->json('hold_id');

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $holdId,
            'anonymous_session_id' => $sess,
        ], ['Authorization' => 'Bearer '.$buyerToken]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('order_id');

        $confirm = $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$buyerToken,
        ]);
        $confirm->assertStatus(200);
        $ticketId = $confirm->json('tickets.0.id');

        $ticket = Ticket::query()->findOrFail($ticketId);
        $jwtTicket = app(JwtTicketService::class)->issueForTicket($ticket->fresh(), $buyer, (int) $event->id);

        $validator = User::factory()->create([
            'username' => 'val_'.uniqid(),
            'email' => uniqid('val', true).'@example.com',
        ]);
        $validator->assignRole('validator');
        $validatorToken = app(JwtTokenService::class)->issueForUser($validator->fresh());

        return [$buyerToken, $jwtTicket, $validatorToken];
    }

    private function createValidatorToken (): string
    {
        $validator = User::factory()->create([
            'username' => 'val_g_'.uniqid(),
            'email' => uniqid('vg', true).'@example.com',
        ]);
        $validator->assignRole('validator');

        return app(JwtTokenService::class)->issueForUser($validator->fresh());
    }
}
