<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_user_email_when_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/auth/me');

        // Flat, unwrapped shape — consistent with the login response's
        // `user` object (no `data` envelope).
        $response->assertStatus(200)
            ->assertExactJson([
                'email' => 'admin@example.test',
            ]);
    }

    public function test_me_returns_401_when_no_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_401_when_token_revoked(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $token = $user->createToken('admin')->plainTextToken;

        // Revoke
        $user->tokens()->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
