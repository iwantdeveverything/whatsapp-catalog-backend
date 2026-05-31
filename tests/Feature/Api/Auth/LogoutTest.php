<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_returns_204_and_revokes_current_token(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $token = $user->createToken('admin')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(204);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_logout_then_me_returns_401_with_revoked_token(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $plainToken = $user->createToken('admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/auth/logout')
            ->assertStatus(204);

        $this->assertSame(0, PersonalAccessToken::count(), 'Token row should be gone after logout');

        // Clear the auth guard cache so the next request re-resolves
        // the user from the (now revoked) Bearer token.
        Auth::forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_returns_401_when_no_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
