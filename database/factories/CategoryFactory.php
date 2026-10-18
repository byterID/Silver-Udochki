<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'icon' => '🪝',
            'description' => null,
            'sort' => 0,
        ];
    }
}
