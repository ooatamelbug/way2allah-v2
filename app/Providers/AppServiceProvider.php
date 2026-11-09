<?php

namespace App\Providers;

use App\Support\Http\CanonicalUrl;
use Illuminate\Contracts\Routing\UrlGenerator;
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
        // N3 (2026-11-08): canonical absolute URLs from APP_URL when APP_FORCE_ROOT_URL is on.
        CanonicalUrl::apply($this->app->make(UrlGenerator::class), $this->app->runningInConsole());
    }
}
