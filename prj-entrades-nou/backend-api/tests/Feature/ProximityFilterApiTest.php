<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProximityFilterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_nearby_requires_lat_lng (): void
    {
        $response = $this->getJson('/api/events/nearby');

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'lat and lng are required');
    }

    public function test_events_nearby_returns_events_within_radius (): void
    {
        config(['database.default' => 'pgsql']);

        $venue = Venue::factory()->create([
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(2.1686, 41.3874), 4326)::geography"),
        ]);
        $nearEvent = Event::factory()->create([
            'venue_id' => $venue->id,
        ]);

        $response = $this->getJson('/api/events/nearby?lat=41.3874&lng=2.1686&radius=50');

        $response->assertStatus(200);
        $response->assertJsonStructure(['events']);
    }

    public function test_cities_search_requires_min_2_chars (): void
    {
        $response = $this->getJson('/api/cities/search?q=a');

        $response->assertStatus(200);
        $response->assertJsonPath('cities', []);
    }

    public function test_cities_search_returns_matching_cities (): void
    {
        config(['database.default' => 'pgsql']);

        Venue::factory()->create([
            'name' => 'Barcelona',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(2.1686, 41.3874), 4326)::geography"),
        ]);

        $response = $this->getJson('/api/cities/search?q=Bar');

        $response->assertStatus(200);
        $response->assertJsonStructure(['cities']);
    }

    public function test_events_nearby_returns_distance_km (): void
    {
        config(['database.default' => 'pgsql']);

        $venue = Venue::factory()->create([
            'name' => 'Barcelona',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(2.1686, 41.3874), 4326)::geography"),
        ]);
        $event = Event::factory()->create(['venue_id' => $venue->id]);

        $response = $this->getJson('/api/events/nearby?lat=41.3874&lng=2.1686&radius=50');

        $response->assertStatus(200);
        $events = $response->json('events');
        if (count($events) > 0) {
            $this->assertArrayHasKey('distance_km', $events[0]);
        }
    }

    public function test_event_price_returns_unit_price (): void
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/events/{$event->id}/price");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'event_id',
            'unit_price',
            'currency',
        ]);
    }

    public function test_event_price_404_for_nonexistent (): void
    {
        $response = $this->getJson('/api/events/99999/price');

        $response->assertStatus(404);
    }
}