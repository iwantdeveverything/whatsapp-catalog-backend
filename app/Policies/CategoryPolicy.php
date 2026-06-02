<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * v1 authorization for categories (INF-06, ADR-10).
 *
 * Every ability returns true for any authenticated user. Sanctum's
 * `auth:sanctum` middleware rejects unauthenticated requests with 401 before
 * a policy method is ever reached. Role-based gating, when introduced, changes
 * only this class — controllers stay untouched.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return true;
    }

    public function delete(User $user, Category $category): bool
    {
        return true;
    }
}
