<?php

namespace Tests\Feature\Api\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_a_product_and_regenerates_slug_if_name_changes(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'price' => 50,
        ]);

        $payload = [
            'name' => 'New Name',
        ];

        $response = $this->patchJson('/api/products/' . $product->slug, $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'New Name')
                 ->assertJsonPath('id', 'new-name')
                 ->assertJsonPath('price', 50); // unchanged field

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'price' => 50,
        ]);
    }

    public function test_it_partially_updates_without_changing_slug(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create([
            'name' => 'Stay Same',
            'slug' => 'stay-same',
            'price' => 50,
        ]);

        $payload = [
            'price' => 99.99,
        ];

        $response = $this->patchJson('/api/products/' . $product->slug, $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'Stay Same')
                 ->assertJsonPath('id', 'stay-same')
                 ->assertJsonPath('price', 99.99);
    }

    public function test_it_replaces_the_images_array(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create([
            'images' => ['https://example.com/old.jpg'],
        ]);

        $payload = [
            'images' => ['https://example.com/new1.jpg', 'https://example.com/new2.jpg'],
        ];

        $response = $this->patchJson('/api/products/' . $product->slug, $payload);

        $response->assertStatus(200);
        $this->assertEquals(
            ['https://example.com/new1.jpg', 'https://example.com/new2.jpg'],
            $response->json('images')
        );
    }

    public function test_it_fails_authorization_for_unauthenticated_users(): void
    {
        $product = Product::factory()->create();

        $response = $this->patchJson('/api/products/' . $product->slug, ['name' => 'Hacked']);

        $response->assertStatus(401);
    }
}
