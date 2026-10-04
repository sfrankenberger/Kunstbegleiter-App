<?php

namespace Database\Factories;

use App\Models\Museum;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'museum_id' => Museum::factory(),
            'city_id' => fn (array $attributes) => Museum::query()->find($attributes['museum_id'])?->city_id,
            'lat' => 48.2037,
            'lng' => 16.3616,
            'accuracy_m' => 20,
            'started_at' => now(),
            'valid_until' => now()->addMinutes(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['started_at' => now()->subHours(2), 'valid_until' => now()->subHour()]);
    }
}
