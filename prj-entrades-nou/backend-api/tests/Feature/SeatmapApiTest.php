<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Zone;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabaseFromSql;
use Tests\TestCase;

class SeatmapApiTest extends TestCase
{
    use RefreshDatabaseFromSql;

    public function test_seatmap_fallback_from_postgres(): void
    {
        $event = Event::factory()->create([
            'external_tm_id' => null,
            'seat_layout' => [],
            'image_url' => 'https://example.org/map.png',
        ]);
        $zone = Zone::factory()->create([
            'event_id' => $event->id,
            'label' => 'Platea',
            'sort_order' => 1,
        ]);
        Seat::factory()->create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'external_seat_key' => 'A-1',
            'status' => 'available',
        ]);

        $res = $this->getJson('/api/events/'.$event->id.'/seatmap');

        $res->assertOk();
        $res->assertJsonPath('snapshotImageUrl', 'https://example.org/map.png');
        $res->assertJsonCount(1, 'zones');
        $res->assertJsonPath('zones.0.label', 'Platea');
        $res->assertJsonCount(1, 'seats');
        $res->assertJsonPath('seats.0.key', 'A-1');
        $res->assertJsonPath('seats.0.status', 'available');
    }

    public function test_seatmap_merges_ticketmaster_snapshot_with_pg_zones(): void
    {
        config(['services.ticketmaster.key' => 'fake-key']);

        Http::fake([
            'app.ticketmaster.com/*' => Http::response([
                'seatmap' => [
                    'staticUrl' => 'https://ticketmaster.example/seatmap.png',
                ],
            ], 200),
        ]);

        $event = Event::factory()->create([
            'external_tm_id' => 'tm123',
        ]);
        $zone = Zone::factory()->create([
            'event_id' => $event->id,
            'label' => 'Lateral',
            'sort_order' => 1,
        ]);
        Seat::factory()->create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
        ]);

        $res = $this->getJson('/api/events/'.$event->id.'/seatmap');

        $res->assertOk();
        $res->assertJsonPath('snapshotImageUrl', 'https://ticketmaster.example/seatmap.png');
        $res->assertJsonCount(1, 'zones');
        $res->assertJsonPath('zones.0.label', 'Lateral');
        $res->assertJsonCount(1, 'seats');
    }
}
