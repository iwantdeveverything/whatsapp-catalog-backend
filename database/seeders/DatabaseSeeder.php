<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the admin user defined in .env.
     *
     * Idempotent: re-running this seeder never creates a duplicate admin row.
     * The password is always hashed from the current env value, so rotating
     * ADMIN_PASSWORD and re-seeding updates the stored hash in place.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.test');
        $password = env('ADMIN_PASSWORD', 'secret-pass');

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
