<?php

namespace Tests\Feature\Api\Products;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_abilities_return_true_for_authenticated_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->make();
        $policy = new ProductPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $product));
    }

    public function test_write_abilities_return_true_for_authenticated_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->make();
        $policy = new ProductPolicy;

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $product));
        $this->assertTrue($policy->delete($admin, $product));
    }

    public function test_policy_is_resolved_for_the_product_model(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->assertTrue($admin->can('viewAny', Product::class));
        $this->assertTrue($admin->can('view', $product));
        $this->assertTrue($admin->can('create', Product::class));
        $this->assertTrue($admin->can('update', $product));
        $this->assertTrue($admin->can('delete', $product));
    }
}
