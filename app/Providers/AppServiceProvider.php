<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The frontend contract (API_CONTRACT.md) defines no `data` envelope on
        // any endpoint. Disable JsonResource's default wrapping so every resource
        // serializes to a flat shape consistent with login's `user` object.
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters.
     *
     * The `login` limiter caps credential attempts per email + IP so a single
     * known admin account cannot be brute forced. It is applied to the public
     * POST /auth/login route via the `throttle:login` middleware.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
