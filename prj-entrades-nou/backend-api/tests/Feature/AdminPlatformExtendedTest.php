<?php

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\Event;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class AdminPlatformExtendedTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp (): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => '']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_presence_ping_requires_auth (): void
    {
        $this->postJson('/api/presence/ping')->assertStatus(401);
    }

    public function test_presence_ping_ok (): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $u = User::factory()->create();
        $u->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($u->fresh());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/presence/ping')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_admin_users_list (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_admin_delete_user_writes_audit_log (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $victim = User::factory()->create();
        $victim->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());
        $victimId = (int) $victim->id;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/api/admin/users/'.$victimId)
            ->assertOk();

        $row = AdminLog::query()
            ->where('action', 'user_deleted')
            ->where('entity_type', 'User')
            ->where('entity_id', $victimId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $admin->id, (int) $row->admin_user_id);
        $this->assertStringContainsString((string) $victim->name, (string) $row->summary);
    }

    public function test_admin_create_user_writes_audit_log (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/users', [
                'name' => 'Usuari prova audit',
                'email' => 'audit_create_'.uniqid('', true).'@test.local',
                'password' => 'password123',
                'role' => 'user',
            ]);
        $res->assertStatus(201);
        $newId = (int) $res->json('id');

        $row = AdminLog::query()
            ->where('action', 'user_created')
            ->where('entity_type', 'User')
            ->where('entity_id', $newId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $admin->id, (int) $row->admin_user_id);
        $this->assertStringContainsString('Usuari prova audit', (string) $row->summary);
    }

    public function test_admin_update_user_writes_audit_log (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $target = User::factory()->create();
        $target->assignRole('user');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());
        $targetId = (int) $target->id;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/users/'.$targetId, [
                'name' => 'Nom actualitzat audit',
            ])
            ->assertOk();

        $row = AdminLog::query()
            ->where('action', 'user_updated')
            ->where('entity_type', 'User')
            ->where('entity_id', $targetId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $admin->id, (int) $row->admin_user_id);
        $this->assertStringContainsString('actualitzat', strtolower((string) $row->summary));
    }

    public function test_admin_monitor_returns_json (): void
    {
        Http::fake([
            'http://socket.test/internal/emit' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/events/'.$event->id.'/monitor')
            ->assertOk()
            ->assertJsonPath('event_id', $event->id);
    }

    public function test_admin_delete_event_soft_hides (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $event = Event::factory()->create([
            'hidden_at' => null,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/api/admin/events/'.$event->id)
            ->assertOk();

        $event->refresh();
        $this->assertNotNull($event->hidden_at);
    }

    public function test_admin_discovery_search_requires_keyword (): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = app(JwtTokenService::class)->issueForUser($admin->fresh());

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/discovery/search')
            ->assertStatus(422);
    }
}
