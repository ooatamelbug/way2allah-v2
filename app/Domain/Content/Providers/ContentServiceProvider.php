<?php

namespace App\Domain\Content\Providers;

use App\Domain\Content\Events\ContentViewed;
use App\Domain\Content\Listeners\RecordsView;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Content domain's event/listener map and routes. Explicit
 * event registration rather than Laravel's app/Listeners auto-discovery,
 * since Content's listeners live under app/Domain/Content/Listeners
 * (Blueprint v1.0 §1), outside the directory auto-discovery scans by
 * default.
 */
class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ContentViewed::class, RecordsView::class);

        Route::middleware('web')->group(base_path('routes/content.php'));
    }
}
