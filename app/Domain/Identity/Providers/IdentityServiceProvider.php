<?php

namespace App\Domain\Identity\Providers;

use App\Domain\Identity\Guards\AdminGuard;
use App\Domain\Identity\Guards\VbulletinSessionGuard;
use App\Domain\Identity\Services\LegacyPasswordVerifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Identity domain's two Guards (ADR-0011).
 */
class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::extend('vbulletin-session', function (Application $app) {
            return new VbulletinSessionGuard($app->make('request'));
        });

        Auth::extend('admin-guard', function (Application $app) {
            return new AdminGuard($app->make('request'), new LegacyPasswordVerifier);
        });
    }
}
