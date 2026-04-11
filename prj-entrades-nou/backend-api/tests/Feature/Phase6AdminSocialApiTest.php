<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FriendInvite;
use App\Models\Seat;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class Phase6AdminSocialApiTest extends TestCase
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

    public function test_admin_summary_forbidden_for_non_admin (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Usuari',
            'username' => 'u_adm_'.uniqid(),
            'email' => uniqid('u', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/summary')
            ->assertStatus(403);
    }

    public function test_admin_summary_ok_and_emits_socket (): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create([
            'username' => 'adm_'.uniqid(),
            'email' => uniqid('adm', true).'@example.com',
        ]);
        $admin->assignRole('admin');

        $token = app(\App\Services\Auth\JwtTokenService::class)->issueForUser($admin->fresh());

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/summary');

        $res->assertOk();
        $res->assertJsonPath('stub', true);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'internal/emit'));
    }

    public function test_social_invite_accept_and_friends (): void
    {
        $a = $this->registerUser('alice6', 'alice6@example.com');
        $b = $this->registerUser('bob6', 'bob6@example.com');

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', [
                'receiver_id' => $b['id'],
            ]);
        $inv->assertStatus(201);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson("/api/social/friend-invites/{$inviteId}", [
                'action' => 'accept',
            ])
            ->assertOk()
            ->assertJsonPath('status', FriendInvite::STATUS_ACCEPTED);

        $friendsA = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/friends');
        $friendsA->assertOk();
        $friendsA->assertJsonCount(1, 'friends');
        $friendsA->assertJsonPath('friends.0.id', $b['id']);
    }

    public function test_ticket_transfer_requires_friendship (): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"/>', 200),
        ]);

        $a = $this->registerUser('owner_tr', 'owner_tr@example.com');
        $b = $this->registerUser('dest_tr', 'dest_tr@example.com');

        [$ticketId] = $this->createSingleTicketOrderForUser($a['token']);

        $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson("/api/tickets/{$ticketId}/transfer", [
                'to_user_id' => $b['id'],
            ])
            ->assertStatus(403);

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', ['receiver_id' => $b['id']]);
        $inv->assertStatus(201);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson("/api/social/friend-invites/{$inviteId}", ['action' => 'accept'])
            ->assertOk();

        $ok = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson("/api/tickets/{$ticketId}/transfer", [
                'to_user_id' => $b['id'],
            ]);
        $ok->assertOk();
        $ok->assertJsonPath('ticket_id', $ticketId);
    }

    /**
     * @return array{token: string, id: int}
     */
    private function registerUser (string $username, string $email): array
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => $username,
            'username' => $username,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);

        return [
            'token' => $reg->json('token'),
            'id' => (int) $reg->json('user.id'),
        ];
    }

    /**
     * @return array{0: string} ticket id
     */
    private function createSingleTicketOrderForUser (string $token): array
    {
        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $sess = 'sess-tr-'.uniqid();
        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => $sess,
        ]);
        $hold->assertStatus(201);

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $hold->json('hold_id'),
            'anonymous_session_id' => $sess,
        ], ['Authorization' => 'Bearer '.$token]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('order_id');

        $confirm = $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $confirm->assertStatus(200);

        return [(string) $confirm->json('tickets.0.id')];
    }
}
