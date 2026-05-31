<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the configured admin user.
     *
     * Reads credentials from config('admin.*') (backed by ADMIN_EMAIL /
     * ADMIN_PASSWORD) so they survive `config:cache`. Fails closed: if either
     * value is missing the seed aborts rather than provisioning an admin with
     * a guessable fallback password.
     *
     * Idempotent: re-running never duplicates the admin row. The password is
     * always re-hashed from the current config value, so rotating
     * ADMIN_PASSWORD and reseeding updates the stored hash in place.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'Admin credentials are not configured. Set ADMIN_EMAIL and ADMIN_PASSWORD before seeding.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
