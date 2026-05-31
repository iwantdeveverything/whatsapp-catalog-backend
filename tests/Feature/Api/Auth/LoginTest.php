<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials_and_returns_token_plus_user(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['email'],
            ])
            ->assertJsonPath('user.email', 'admin@example.test');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'Invalid email or password']);

        $this->assertSame(0, \DB::table('personal_access_tokens')->count());
    }

    public function test_login_returns_401_on_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'Invalid email or password']);
    }

    public function test_login_returns_422_when_email_missing(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'secret-pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_422_when_password_missing(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
