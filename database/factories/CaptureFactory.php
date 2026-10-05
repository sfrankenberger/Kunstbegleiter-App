<?php

namespace Database\Factories;

use App\Enums\CaptureStatus;
use App\Enums\GuideLength;
use App\Models\Artwork;
use App\Models\Capture;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Capture>
 */
class CaptureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'user_id' => fn (array $attributes) => Visit::query()->find($attributes['visit_id'])?->user_id ?? User::factory(),
            'artwork_id' => null,
            'status' => CaptureStatus::Uploaded,
            'length' => GuideLength::Normal,
            'recognition' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => CaptureStatus::Done, 'artwork_id' => Artwork::factory(), 'confirmed_at' => now()]);
    }
}
