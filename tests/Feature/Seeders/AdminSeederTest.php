<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Credentials used by these tests are deliberately DIFFERENT from any
     * fallback the production code might carry, so the assertions can only
     * pass if the seeder genuinely reads configuration.
     */
    private const ADMIN_EMAIL = 'rotated-admin@corp.test';

    private const ADMIN_PASSWORD = 'rotated-pw-123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.email' => self::ADMIN_EMAIL,
            'admin.password' => self::ADMIN_PASSWORD,
        ]);
    }

    public function test_seeder_creates_admin_using_configured_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', self::ADMIN_EMAIL)->first();

        $this->assertNotNull($admin, 'Admin user should be created by seeder');
        $this->assertTrue(
            Hash::check(self::ADMIN_PASSWORD, $admin->password),
            'Password should be hashed and match the configured value'
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            1,
            User::where('email', self::ADMIN_EMAIL)->count(),
            'Running the seeder twice must not duplicate the admin row'
        );
    }

    public function test_reseeding_rotates_the_admin_password_in_place(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Rotate the configured password and reseed.
        config(['admin.password' => 'a-brand-new-password']);
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();

        $this->assertTrue(
            Hash::check('a-brand-new-password', $admin->password),
            'Reseeding must update the stored hash to the new password'
        );
        $this->assertFalse(
            Hash::check(self::ADMIN_PASSWORD, $admin->password),
            'The previous password must no longer verify after rotation'
        );
    }

    public function test_seeder_fails_closed_when_admin_password_is_missing(): void
    {
        config(['admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            0,
            User::where('email', self::ADMIN_EMAIL)->count(),
            'No admin must be created when the password is not configured'
        );
    }
}
