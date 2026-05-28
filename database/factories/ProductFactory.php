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
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 999),
            'currency' => 'USD',
            'status' => 'active',
            'images' => [fake()->imageUrl()],
            'variant_options' => [],
            'item_group_id' => null,
        ];
    }
}
