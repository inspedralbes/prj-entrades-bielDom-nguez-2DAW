<?php

namespace Database\Factories;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name' => 'Sala '.$this->faker->unique()->streetName(),
        ];
    }
}
