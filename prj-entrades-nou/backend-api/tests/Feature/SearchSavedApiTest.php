<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SavedEvent;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class SearchSavedApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    protected function setUp (): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test_jwt_secret_minimum_32_chars_long_xx']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Cache::flush();
    }

    public function test_search_events_by_query (): void
    {
        $venue = Venue::factory()->create();
        Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Concert Rock Unic',
            'hidden_at' => null,
        ]);

        $res = $this->getJson('/api/search/events?q=Rock');
        $res->assertOk();
        $this->assertNotEmpty($res->json('events'));
    }

    public function test_saved_events_roundtrip (): void
    {
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'U',
            'username' => 'save_u_'.uniqid(),
            'email' => uniqid('s', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $token = $reg->json('token');

        $venue = Venue::factory()->create();
        $ev = Event::factory()->create(['venue_id' => $venue->id, 'hidden_at' => null]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/saved-events', ['event_id' => $ev->id])
            ->assertStatus(201);

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/saved-events');
        $list->assertOk();
        $this->assertNotEmpty($list->json('events'));

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->delete('/api/saved-events/'.$ev->id)
            ->assertOk();

        $this->assertSame(0, SavedEvent::query()->where('event_id', $ev->id)->count());
    }
}
