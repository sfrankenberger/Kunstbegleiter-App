<?php

namespace Database\Factories;

use App\Enums\PhotoType;
use App\Models\Capture;
use App\Models\CapturePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapturePhoto>
 */
class CapturePhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'capture_id' => Capture::factory(),
            'path' => 'captures/test/'.fake()->uuid().'.jpg',
            'type' => PhotoType::Artwork,
            'width' => 2000,
            'height' => 1500,
            'sort_order' => 0,
        ];
    }
}
