<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use App\Models\User;
use App\Policies\CategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_abilities_return_true_for_authenticated_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->make();
        $policy = new CategoryPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $category));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $category));
        $this->assertTrue($policy->delete($admin, $category));
    }

    public function test_policy_is_resolved_for_the_category_model(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('viewAny', Category::class));
        $this->assertTrue($admin->can('create', Category::class));
    }
}
