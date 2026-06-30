<?php

namespace Tests\Feature\Api\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_product_with_auto_generated_slug(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'category_id' => $category->id,
            'name' => 'Awesome New Product',
            'description' => 'This is an awesome product.',
            'price' => 100.50,
            'currency' => 'USD',
            'status' => 'active',
            'images' => ['https://example.com/image1.jpg'],
        ];

        $this->actingAsAdmin();
        $response = $this->postJson('/api/products', $payload);


        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Awesome New Product')
                 ->assertJsonPath('id', 'awesome-new-product')
                 ->assertJsonPath('category', $category->name);

        $this->assertDatabaseHas('products', [
            'name' => 'Awesome New Product',
            'slug' => 'awesome-new-product',
            'price' => 100.50,
        ]);
    }

    public function test_it_fails_authorization_for_unauthenticated_users(): void
    {
        $payload = [
            'name' => 'Should fail',
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(401);
    }
    
    public function test_it_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/products', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'category_id', 'price', 'currency', 'status']);
    }
}
