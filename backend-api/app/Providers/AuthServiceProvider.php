<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\RequestGuard;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define a custom composite guard that tries Sanctum first, then JWT.
        Auth::extend('sanctum-jwt', function ($app, $name, array $config) {
            return new RequestGuard(function ($request) {
                try {
                    // Try to authenticate with Sanctum guard
                    $sanctumUser = Auth::guard('sanctum')->user();
                    if ($sanctumUser) {
                        return $sanctumUser;
                    }

                    // If Sanctum fails, try to authenticate with JWT guard
                    return Auth::guard('jwt')->user();
                } catch (\Exception $e) {
                    // Log the error for debugging
                    Log::warning('Authentication error in sanctum-jwt guard: ' . $e->getMessage());
                    return null;
                }
                
            }, $app['request'], $app['auth']->createUserProvider($config['provider'] ?? null));
        });
    }
}
