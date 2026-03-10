<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\StudentObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
            return [
                // Max 5 attempts per email per minute
                Limit::perMinute(5)->by($request->input('email')),
                // Max 20 attempts per IP per minute (catches credential stuffing)
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
        // User::observe(StudentObserver::class);
    }
}
