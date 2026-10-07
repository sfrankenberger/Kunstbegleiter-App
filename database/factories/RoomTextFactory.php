<?php

namespace Database\Factories;

use App\Models\RoomText;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoomText> */
class RoomTextFactory extends Factory
{
    protected $model = RoomText::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => 'room-texts/test.jpg',
            'width' => 800,
            'height' => 600,
            'title' => 'Saal '.fake()->numberBetween(1, 30),
            'text' => fake()->paragraph(),
        ];
    }
}
