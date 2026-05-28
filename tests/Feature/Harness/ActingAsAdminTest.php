<?php

namespace Tests\Feature\Harness;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_acting_as_admin_returns_a_persisted_user(): void
    {
        $user = $this->actingAsAdmin();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->exists, 'actingAsAdmin() must return a persisted user');
        $this->assertNotEmpty($user->email);
    }

    public function test_acting_as_admin_authenticates_sanctum_guard(): void
    {
        $user = $this->actingAsAdmin();

        $this->assertTrue(auth()->check(), 'Sanctum guard must be authenticated after actingAsAdmin()');
        $this->assertSame($user->id, auth()->id());
    }
}
