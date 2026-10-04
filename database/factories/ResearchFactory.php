<?php

namespace Database\Factories;

use App\Models\Artwork;
use App\Models\Research;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Research>
 */
class ResearchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'artwork_id' => Artwork::factory(),
            'summary' => fake()->paragraph(),
            'sources' => [['url' => fake()->url(), 'title' => fake()->sentence(3), 'kind' => 'collection']],
            'existing_guides' => [],
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cost_cents' => 2,
        ];
    }
}
