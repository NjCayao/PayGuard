<?php

namespace App\Providers;

use App\Services\Demo\TrialSession;
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
        RateLimiter::for('trial', function (Request $request) {
            $key = $request->header(TrialSession::HEADER, '');

            return [
                Limit::perHour((int) config('payguard.demo.trial.per_hour'))->by("trial:{$key}|{$request->ip()}"),
                Limit::perHour((int) config('payguard.demo.trial.per_ip_per_hour'))->by("trial-ip:{$request->ip()}"),
            ];
        });
    }
}
