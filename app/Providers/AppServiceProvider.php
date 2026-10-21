<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            // Limit per account (blunts password spraying across many IPs) and
            // per IP (blunts credential stuffing against many accounts).
            $limits = [Limit::perMinute(20)->by('login:ip:' . $request->ip())];

            if ($email !== '') {
                $limits[] = Limit::perMinute(5)->by('login:email:' . $email);
                $limits[] = Limit::perHour(30)->by('login:email-hour:' . $email);
            }

            return $limits;
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }
}