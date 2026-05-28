<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $token = $user->createToken('admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertStatus(204);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_returns_401_when_no_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
