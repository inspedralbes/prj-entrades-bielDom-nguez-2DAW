<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition (): array
    {
        return [
            'external_tm_id' => null,
            'name' => $this->faker->sentence(3),
            'hold_ttl_seconds' => 240,
            'venue_id' => Venue::factory(),
            'starts_at' => now()->addDays(7),
            'hidden_at' => null,
            'category' => 'música',
            'seat_layout' => null,
            'tm_sync_paused' => false,
        ];
    }
}
