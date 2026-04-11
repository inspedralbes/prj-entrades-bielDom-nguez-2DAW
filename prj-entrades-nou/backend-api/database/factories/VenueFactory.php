<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition (): array
    {
        return [
            'name' => 'Sala '.$this->faker->unique()->streetName(),
        ];
    }
}
