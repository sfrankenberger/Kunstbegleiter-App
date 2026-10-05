<?php

namespace Database\Factories;

use App\Enums\AiPurpose;
use App\Models\AiCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCall>
 */
class AiCallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'capture_id' => null,
            'purpose' => AiPurpose::Script,
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 1200,
            'output_tokens' => 600,
            'characters' => 0,
            'cost_cents' => 3,
            'duration_ms' => 4000,
            'succeeded' => true,
        ];
    }
}
