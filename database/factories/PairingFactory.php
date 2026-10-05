<?php

namespace Database\Factories;

use App\Enums\PairingStatus;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pairing>
 */
class PairingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_a_id' => User::factory(),
            'user_b_id' => User::factory(),
            'requested_by_id' => fn (array $attributes) => $attributes['user_a_id'],
            'status' => PairingStatus::Requested,
            'token' => Str::random(40),
            'accepted_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => PairingStatus::Active, 'accepted_at' => now(), 'token' => null]);
    }
}
