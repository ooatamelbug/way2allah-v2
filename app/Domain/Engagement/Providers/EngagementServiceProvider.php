<?php

namespace App\Domain\Engagement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Engagement domain's route file — Roadmap task 3.4, the
 * first real Engagement-domain capability with actual legacy code behind
 * it (Blueprint §1's domain list named this folder from Wave 0, but
 * nothing populated it until now).
 */
class EngagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/engagement.php'));
    }
}
