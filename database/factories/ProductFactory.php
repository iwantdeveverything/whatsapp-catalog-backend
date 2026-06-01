<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 999),
            'currency' => 'USD',
            'whatsapp' => fake()->optional()->numerify('549###########'),
            'phone' => fake()->optional()->phoneNumber(),
            'is_active' => true,
            'images' => [fake()->imageUrl()],
            'variant_options' => [],
            'item_group_id' => null,
        ];
    }
}
