<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_ATTEMPTS = 5;

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('secret-pass'),
        ]);

        // Exhaust the allowed attempts with wrong credentials.
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        // The next attempt must be rejected by the rate limiter, even
        // when the credentials are correct.
        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'secret-pass',
        ])->assertStatus(429);
    }

    public function test_throttle_is_keyed_per_email(): void
    {
        // Burn the limit for one email...
        for ($i = 0; $i < self::MAX_ATTEMPTS + 1; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'victim@example.test',
                'password' => 'wrong-password',
            ]);
        }

        // ...a different email must still be allowed through (not 429).
        $response = $this->postJson('/api/auth/login', [
            'email' => 'someone-else@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }
}
