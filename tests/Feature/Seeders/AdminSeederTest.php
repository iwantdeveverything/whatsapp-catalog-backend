<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_admin_using_env_credentials(): void
    {
        config(['app.admin_email' => null]); // ensure clean
        putenv('ADMIN_EMAIL=admin@example.test');
        putenv('ADMIN_PASSWORD=secret-pass');

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.test')->first();

        $this->assertNotNull($admin, 'Admin user should be created by seeder');
        $this->assertTrue(Hash::check('secret-pass', $admin->password), 'Password should be hashed and match env value');
    }

    public function test_seeder_is_idempotent(): void
    {
        putenv('ADMIN_EMAIL=admin@example.test');
        putenv('ADMIN_PASSWORD=secret-pass');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            1,
            User::where('email', 'admin@example.test')->count(),
            'Running the seeder twice must not duplicate the admin row'
        );
    }
}
