<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * v1 read authorization for products (INF-06, ADR-10).
 *
 * Only the view abilities ship in PR #5a (read endpoints). Every method
 * returns true for any authenticated user; Sanctum's `auth:sanctum`
 * middleware rejects unauthenticated requests with 401 before a policy
 * method is ever reached. Write abilities (create/update/delete) land with
 * the write endpoints in PR #5b. Role-based gating, when introduced, changes
 * only this class — controllers stay untouched.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return true;
    }

    public function delete(User $user, Product $product): bool
    {
        return true;
    }
}
