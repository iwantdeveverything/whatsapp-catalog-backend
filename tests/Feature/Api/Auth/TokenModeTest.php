<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_stateful_array_is_empty(): void
    {
        $this->assertSame([], config('sanctum.stateful'));
    }

    public function test_valid_bearer_token_authenticates(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $token = $user->createToken('admin')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        // Unwrapped shape — no `data` envelope (matches the frontend contract).
        $response->assertStatus(200)
            ->assertJsonPath('email', 'admin@example.test');
    }

    public function test_session_cookie_without_bearer_does_not_authenticate_api(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        // Simulate a session-authenticated request without any Bearer token.
        // Because `sanctum.stateful` is empty, Sanctum must NOT honor the
        // session cookie for /api routes — the request must be rejected with 401.
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
