<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artwork>
 */
class ArtworkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'museum_id' => Museum::factory(),
            'title' => fake()->sentence(3),
            'dating' => (string) fake()->numberBetween(1500, 1950),
            'technique' => 'Öl auf Leinwand',
            'dimensions' => '80 x 60 cm',
            'inventory_number' => 'GG '.fake()->numberBetween(100, 9999),
            'facts' => [],
            'sources' => [],
        ];
    }
}
