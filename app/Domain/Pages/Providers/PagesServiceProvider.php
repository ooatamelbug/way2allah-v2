<?php

namespace App\Domain\Pages\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PagesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/pages.php'));
    }
}
