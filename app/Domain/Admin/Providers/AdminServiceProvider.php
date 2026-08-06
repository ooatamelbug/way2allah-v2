<?php

namespace App\Domain\Admin\Providers;

use App\Domain\Admin\Http\Middleware\EnsureAdminHasRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Admin domain's route file and its permission-middleware
 * alias (Roadmap task 1.6). 'admin.role' is a route-level alias, not
 * global middleware — it only applies where a route/group explicitly
 * requests it.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::aliasMiddleware('admin.role', EnsureAdminHasRole::class);

        // Explicit 'web' group, not loadRoutesFrom() alone — routes loaded
        // from a ServiceProvider don't get web.php's session/cookie/CSRF
        // middleware automatically, and AdminGuard needs a working session
        // to store the logged-in admin's id (found by actually running the
        // route through a test request, not by inspection).
        Route::middleware('web')->group(base_path('routes/admin.php'));
    }
}
