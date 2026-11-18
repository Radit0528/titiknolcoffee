<?php

namespace Database\Factories;

use App\Models\MenuVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuVideo>
 */
class MenuVideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'caption' => fake()->sentence(),
            'video_path' => 'menu-videos/test.mp4',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
