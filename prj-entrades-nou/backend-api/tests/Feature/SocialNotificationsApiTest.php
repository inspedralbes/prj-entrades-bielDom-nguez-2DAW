<?php

namespace Tests\Feature;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\SocialNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class SocialNotificationsApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        Http::fake([
            'http://socket.test/*' => Http::response('', 204),
        ]);
        $this->seed(RoleSeeder::class);
    }

    public function test_friends_search_filters_by_q(): void
    {
        $a = $this->registerUser('alice_sn', 'alice_sn@example.com');
        $b = $this->registerUser('bobunique_sn', 'bobunique_sn@example.com');

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', ['receiver_id' => $b['id']]);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson("/api/social/friend-invites/{$inviteId}", ['action' => 'accept'])
            ->assertOk();

        $all = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/friends');
        $all->assertOk();
        $this->assertGreaterThanOrEqual(1, count($all->json('friends')));

        $filtered = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/friends?q=bobunique');
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('friends'));
        $this->assertSame($b['id'], $filtered->json('friends.0.id'));

        $none = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/friends?q=zzznope');
        $none->assertOk();
        $this->assertCount(0, $none->json('friends'));
    }

    public function test_share_event_creates_notifications(): void
    {
        $a = $this->registerUser('sender_se', 'sender_se@example.com');
        $b = $this->registerUser('recv_se', 'recv_se@example.com');

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', ['receiver_id' => $b['id']]);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson("/api/social/friend-invites/{$inviteId}", ['action' => 'accept'])
            ->assertOk();

        $event = Event::factory()->create(['hidden_at' => null]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/share-event', [
                'event_id' => $event->id,
                'to_user_id' => $b['id'],
            ]);
        $res->assertStatus(201);

        $this->assertGreaterThanOrEqual(2, SocialNotification::query()->where('user_id', $b['id'])->count());
        $this->assertGreaterThanOrEqual(2, SocialNotification::query()->where('user_id', $a['id'])->count());

        $listB = $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->getJson('/api/notifications');
        $listB->assertOk();
        $rows = $listB->json('notifications');
        $this->assertIsArray($rows);
        $eventShared = null;
        foreach ($rows as $row) {
            if (is_array($row) && ($row['type'] ?? '') === 'event_shared') {
                $eventShared = $row;
                break;
            }
        }
        $this->assertNotNull($eventShared, 'Cal una notificació event_shared per al destinatari.');
        $this->assertSame('received', $eventShared['payload']['direction']);
    }

    public function test_share_thread_requires_friendship(): void
    {
        $a = $this->registerUser('thread_a', 'thread_a@example.com');
        $b = $this->registerUser('thread_b', 'thread_b@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/users/'.$b['id'].'/share-thread')
            ->assertStatus(403);

        $inv = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/friend-invites', ['receiver_id' => $b['id']]);
        $inviteId = $inv->json('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$b['token']])
            ->patchJson('/api/social/friend-invites/'.$inviteId, ['action' => 'accept'])
            ->assertOk();

        $ok = $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->getJson('/api/social/users/'.$b['id'].'/share-thread');
        $ok->assertOk();
        $this->assertIsArray($ok->json('messages'));
        $this->assertArrayHasKey('thread_notifications_muted', $ok->json());
        $this->assertFalse($ok->json('thread_notifications_muted'));
    }

    public function test_share_event_forbidden_without_friendship(): void
    {
        $a = $this->registerUser('a_nf', 'a_nf@example.com');
        $b = $this->registerUser('b_nf', 'b_nf@example.com');
        $event = Event::factory()->create(['hidden_at' => null]);

        $this->withHeaders(['Authorization' => 'Bearer '.$a['token']])
            ->postJson('/api/social/share-event', [
                'event_id' => $event->id,
                'to_user_id' => $b['id'],
            ])
            ->assertStatus(403);
    }

    /**
     * @return array{token: string, id: int}
     */
    private function registerUser(string $username, string $email): array
    {
        $reg = $this->postJson('/api/auth/register', [
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
}
