<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Dades mínimes per a Cypress (T025): esdeveniment + 2 seients.
 * Escriu storage/app/cypress-order-demo.json amb { "eventId": <int> }.
 */
class CypressOrderFlowSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $event = Event::factory()->create([
            'hold_ttl_seconds' => 240,
            'external_tm_id' => null,
            'seat_layout' => [
                'snapshotImageUrl' => 'https://example.org/cypress.png',
            ],
        ]);
        $zone = Zone::factory()->create(['event_id' => $event->id]);
        Seat::factory()->count(2)->create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'status' => 'available',
        ]);

        $path = storage_path('app/cypress-order-demo.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['eventId' => $event->id], JSON_THROW_ON_ERROR));
    }
}
