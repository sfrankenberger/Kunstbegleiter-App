<?php

namespace Database\Factories;

use App\Models\Exhibition;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exhibition>
 */
class ExhibitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'museum_id' => Museum::factory(),
            'title' => fake()->sentence(3),
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'source_url' => fake()->url(),
            'fetched_at' => now(),
        ];
    }
}
