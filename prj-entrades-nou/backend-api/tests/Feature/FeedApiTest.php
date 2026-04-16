<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class FeedApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    public function test_featured_returns_events(): void
    {
        $venue = Venue::factory()->create();
        $ev = Event::factory()->create([
            'venue_id' => $venue->id,
            'hidden_at' => null,
        ]);

        $res = $this->getJson('/api/feed/featured');
        $res->assertOk();
        $res->assertJsonPath('section', 'featured');
        $ids = collect($res->json('events'))->pluck('id')->all();
        $this->assertContains($ev->id, $ids);
    }

    public function test_for_you_requires_auth(): void
    {
        $this->getJson('/api/feed/for-you')->assertStatus(401);
    }

    public function test_for_you_ok_with_token(): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'username' => 'feed_u_'.uniqid(),
            'email' => uniqid('f', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $venue = Venue::factory()->create();
        Event::factory()->create(['venue_id' => $venue->id, 'hidden_at' => null]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/feed/for-you');
        $res->assertOk();
        $res->assertJsonPath('section', 'for_you');
        $this->assertNotEmpty($res->json('events'));
    }
}
