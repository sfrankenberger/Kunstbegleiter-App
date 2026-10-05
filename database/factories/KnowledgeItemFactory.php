<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeItem>
 */
class KnowledgeItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_type' => Artist::class,
            'subject_id' => Artist::factory(),
            'summary' => fake()->sentence(),
            'told_at' => now(),
        ];
    }
}
