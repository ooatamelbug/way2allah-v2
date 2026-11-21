<?php

namespace App\Providers;

use App\Support\Downloads\Telemetry\DownloadTelemetry;
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
        // Phase 3A download telemetry recorder: one per request (off unless DOWNLOAD_TELEMETRY_EVENTS=on and the spool is enabled).
        $this->app->scoped(DownloadTelemetry::class, fn () => new DownloadTelemetry);
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
