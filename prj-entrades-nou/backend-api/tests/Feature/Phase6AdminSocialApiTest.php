<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FriendInvite;
use App\Models\Seat;
use App\Models\User;
use App\Models\Zone;
use App\Services\Auth\JwtTokenService;
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
        $res->assertJsonPath('stub', false);
        $res->assertJsonStructure([
            'revenue_today',
            'pending_payment_count',
            'sync_alerts',
            'online_users',
            'events_total',
            'orders_paid',
        ]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'internal/emit'));
    }

    public function test_admin_patch_event_updates_tm_sync_and_hidden (): void
    {
        $admin = User::factory()->create([
            'username' => 'adm_patch_'.uniqid(),
            'email' => uniqid('adm_patch', true).'@example.com',
        ]);
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create([
            'tm_sync_paused' => false,
            'hidden_at' => null,
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/'.$event->id, [
                'tm_sync_paused' => true,
                'hidden_at' => '2026-12-01T10:00:00Z',
            ]);

        $res->assertOk();
        $res->assertJsonPath('id', $event->id);
        $res->assertJsonPath('tm_sync_paused', true);

        $event->refresh();
        $this->assertTrue($event->tm_sync_paused);
        $this->assertNotNull($event->hidden_at);
    }

    public function test_admin_patch_event_updates_price (): void
    {
        $admin = User::factory()->create([
            'username' => 'adm_price_'.uniqid(),
            'email' => uniqid('adm_price', true).'@example.com',
        ]);
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create([
            'price' => 15.0,
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/'.$event->id, [
                'price' => 42.5,
            ]);

        $res->assertOk();
        $this->assertEquals(42.5, (float) $res->json('price'));

        $event->refresh();
        $this->assertEquals(42.5, (float) $event->price);
    }

    public function test_admin_patch_event_clear_hidden (): void
    {
        $admin = User::factory()->create([
            'username' => 'adm_patch2_'.uniqid(),
            'email' => uniqid('adm_patch2', true).'@example.com',
        ]);
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create([
            'hidden_at' => now()->addDay(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/'.$event->id, [
                'hidden_at' => null,
            ])
            ->assertOk();

        $event->refresh();
        $this->assertNull($event->hidden_at);
    }

    public function test_admin_patch_event_forbidden_for_non_admin (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Usuari',
            'username' => 'u_patch_'.uniqid(),
            'email' => uniqid('u_patch', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');
        $event = Event::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/'.$event->id, ['tm_sync_paused' => true])
            ->assertStatus(403);
    }

    public function test_admin_patch_event_404 (): void
    {
        $admin = User::factory()->create([
            'username' => 'adm_404_'.uniqid(),
            'email' => uniqid('adm_404', true).'@example.com',
        ]);
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/999999', ['tm_sync_paused' => true])
            ->assertStatus(404);
    }

    public function test_admin_patch_event_requires_at_least_one_field (): void
    {
        $admin = User::factory()->create([
            'username' => 'adm_422_'.uniqid(),
            'email' => uniqid('adm_422', true).'@example.com',
        ]);
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());
        $event = Event::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/events/'.$event->id, [])
            ->assertStatus(422);
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

    public function test_ticket_transfer_splits_order_with_multiple_lines (): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"/>', 200),
        ]);

        $a = $this->registerUser('owner_m2', 'owner_m2@example.com');
        $b = $this->registerUser('dest_m2', 'dest_m2@example.com');

        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);
        $s2 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $sess = 'sess-m2-'.uniqid();
        $hold = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id, $s2->id],
            'anonymous_session_id' => $sess,
        ]);
        $hold->assertStatus(201);

        $orderRes = $this->postJson('/api/orders', [
            'hold_id' => $hold->json('hold_id'),
            'anonymous_session_id' => $sess,
        ], ['Authorization' => 'Bearer '.$a['token']]);
        $orderRes->assertStatus(201);
        $orderId = $orderRes->json('order_id');

        $this->postJson("/api/orders/{$orderId}/confirm-payment", [], [
            'Authorization' => 'Bearer '.$a['token'],
        ])->assertStatus(200);

        $ticketsRes = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/tickets');
        $ticketsRes->assertOk();
        $ticketId = $ticketsRes->json('tickets.0.id');

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', ['receiver_id' => $b['id']]);
        $inv->assertStatus(201);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson("/api/social/friend-invites/{$inviteId}", ['action' => 'accept'])
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson("/api/tickets/{$ticketId}/transfer", [
                'to_user_id' => $b['id'],
            ])
            ->assertOk();

        $aTickets = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/tickets');
        $aTickets->assertOk();
        $aTickets->assertJsonCount(1, 'tickets');

        $bTickets = $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->getJson('/api/tickets');
        $bTickets->assertOk();
        $bTickets->assertJsonCount(1, 'tickets');
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
