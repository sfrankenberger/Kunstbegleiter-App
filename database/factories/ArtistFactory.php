<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    public function definition(): array
    {
        $born = fake()->numberBetween(1400, 1950);

        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'born_year' => $born,
            'died_year' => $born + fake()->numberBetween(30, 90),
            'short_bio' => fake()->sentence(),
        ];
    }
}
