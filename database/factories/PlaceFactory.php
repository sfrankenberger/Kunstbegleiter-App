<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->streetName().'-Denkmal',
            'kind' => 'monument',
            'wikidata_id' => 'Q'.fake()->unique()->numberBetween(100000, 99999999),
            'lat' => 48.2 + fake()->randomFloat(4, 0, 0.02),
            'lng' => 16.36 + fake()->randomFloat(4, 0, 0.02),
            'facts' => [],
        ];
    }
}
