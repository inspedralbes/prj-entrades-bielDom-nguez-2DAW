<?php

namespace Database\Factories;

use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cal passar sempre `event_id` i `zone_id` coherents (mateix esdeveniment).
 *
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

    public function definition(): array
    {
        return [
            'external_seat_key' => 'S-'.$this->faker->unique()->numerify('####'),
            'status' => 'available',
        ];
    }
}
