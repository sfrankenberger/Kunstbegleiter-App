<?php

namespace Database\Factories;

use App\Models\Epoch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Epoch>
 */
class EpochFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'from_year' => 1800,
            'to_year' => 1900,
            'sort_order' => 0,
        ];
    }
}
