<?php

namespace App\Providers;

use App\Models\Absensi;
use App\Models\Izin;
use App\Observers\AbsensiObserver;
use App\Observers\IzinObserver;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Absensi::observe(AbsensiObserver::class);
        Izin::observe(IzinObserver::class);

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
            if ($frontendUrl === '') {
                $frontendUrl = rtrim((string) config('app.url'), '/');
            }

            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);

            return $frontendUrl . '/reset-password?' . $query;
        });
    }
}
