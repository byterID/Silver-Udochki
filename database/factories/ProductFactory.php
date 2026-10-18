<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => 'SU-T-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'description' => null,
            'price' => 1000,
            'stock' => 10,
            'icon' => '🐟',
            'tags' => [],
            'is_active' => true,
        ];
    }
}
