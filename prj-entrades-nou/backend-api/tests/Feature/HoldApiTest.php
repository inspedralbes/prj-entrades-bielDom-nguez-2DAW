<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class HoldApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        Cache::flush();
    }

    public function test_create_hold_and_conflict(): void
    {
        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);
        $s2 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $r1 = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id, $s2->id],
            'anonymous_session_id' => 'anon-a',
        ]);

        $r1->assertStatus(201);
        $r1->assertJsonStructure(['hold_id', 'expires_at', 'anonymous_session_id', 'seat_ids']);

        $r2 = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'anon-b',
        ]);

        $r2->assertStatus(409);
    }

    public function test_delete_hold(): void
    {
        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $r1 = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'anon-del',
        ]);
        $holdId = $r1->json('hold_id');

        $del = $this->deleteJson("/api/holds/{$holdId}", [
            'anonymous_session_id' => 'anon-del',
        ]);
        $del->assertOk();

        $s1->refresh();
        $this->assertSame('available', $s1->status);
    }

    public function test_login_grace_once(): void
    {
        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $r1 = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'anon-grace',
        ]);
        $holdId = $r1->json('hold_id');

        $g1 = $this->postJson("/api/holds/{$holdId}/login-grace", [
            'anonymous_session_id' => 'anon-grace',
        ]);
        $g1->assertOk();

        $g2 = $this->postJson("/api/holds/{$holdId}/login-grace", [
            'anonymous_session_id' => 'anon-grace',
        ]);
        $g2->assertStatus(403);
    }

    public function test_hold_time_endpoint(): void
    {
        $event = Event::factory()->create(['hold_ttl_seconds' => 240]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        $s1 = Seat::factory()->create(['event_id' => $event->id, 'zone_id' => $zone->id]);

        $r1 = $this->postJson("/api/events/{$event->id}/holds", [
            'seat_ids' => [$s1->id],
            'anonymous_session_id' => 'anon-time',
        ]);
        $holdId = $r1->json('hold_id');

        $t = $this->getJson("/api/holds/{$holdId}/time");
        $t->assertOk();
        $t->assertHeader('X-Server-Time');
        $t->assertJsonStructure(['expires_at', 'server_time']);
    }
}
