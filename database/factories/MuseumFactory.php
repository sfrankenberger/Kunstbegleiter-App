<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Museum>
 */
class MuseumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'name' => fake()->company().' Museum',
            'place_id' => null,
            'website' => fake()->url(),
            'address' => fake()->streetAddress(),
            'lat' => fake()->latitude(48.1, 48.3),
            'lng' => fake()->longitude(16.2, 16.5),
        ];
    }
}
