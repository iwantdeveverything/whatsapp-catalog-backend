<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate the current request as a freshly created admin user
     * using Sanctum's actingAs() helper.
     *
     * Returns the persisted admin so individual tests can assert on the
     * authenticated identity without re-querying the database.
     */
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }
}
