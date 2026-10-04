<?php

namespace Database\Factories;

use App\Enums\TipKind;
use App\Models\CityTip;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CityTip>
 */
class CityTipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'title' => fake()->sentence(3),
            'kind' => TipKind::Exhibition,
            'url' => fake()->url(),
            'reason' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
