<?php

namespace App\Support\LegacyUrlCompatibility;

use Illuminate\Support\ServiceProvider;

class UrlMapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/legacy-compat.php'));
    }
}
