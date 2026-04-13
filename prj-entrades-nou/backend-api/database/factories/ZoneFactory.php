<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition (): array
    {
        return [
            'event_id' => Event::factory(),
            'external_zone_key' => null,
            'label' => 'Zona '.$this->faker->randomLetter(),
            'sort_order' => 0,
        ];
    }
}
