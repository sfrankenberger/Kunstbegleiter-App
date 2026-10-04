<?php

namespace Database\Factories;

use App\Models\AudioGuide;
use App\Models\Capture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioGuide>
 */
class AudioGuideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'capture_id' => Capture::factory(),
            'script' => [['role' => 'narrator', 'text' => fake()->paragraph()]],
            'audio_path' => null,
            'duration_seconds' => 180,
            'word_count' => 400,
            'model' => 'claude-sonnet-5-5',
            'tts_provider' => 'fake',
            'tts_characters' => 2500,
            'cost_cents' => 5,
        ];
    }
}
