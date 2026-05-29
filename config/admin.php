<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Admin Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials for the single administrator account provisioned by the
    | DatabaseSeeder. These are read here (inside a config file) rather than
    | via env() at call sites so they keep working after `config:cache`.
    |
    | There are intentionally NO fallback defaults: the seeder fails closed
    | when the password is absent, so a misconfigured environment can never
    | provision an admin with a guessable password.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
