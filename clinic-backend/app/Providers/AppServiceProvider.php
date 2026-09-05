<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
        foreach (['public' => [120, 1], 'booking' => [5, 10], 'enquiry' => [3, 10], 'manage' => [10, 10], 'lookup' => [30, 1], 'login' => [5, 1]] as $name => [$attempts,$minutes]) {
            RateLimiter::for($name, fn ($request) => Limit::perMinutes($minutes, $attempts)->by($name.':'.$request->ip()));
        }
    }
}
