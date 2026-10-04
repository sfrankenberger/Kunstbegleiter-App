<?php

namespace Database\Factories;

use App\Models\Capture;
use App\Models\FactSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactSheet>
 */
class FactSheetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'capture_id' => Capture::factory(),
            'key_facts' => ['Technik' => 'Öl auf Leinwand'],
            'key_statements' => [fake()->sentence()],
            'guest_ideas' => ['opener' => fake()->sentence(), 'question' => fake()->sentence()],
            'cross_references' => [],
        ];
    }
}
