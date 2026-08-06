<?php

namespace App\Domain\Admin\Providers;

use App\Domain\Admin\Http\Middleware\EnsureAdminHasPermission;
use App\Domain\Admin\Http\Middleware\EnsureAdminHasRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Admin domain's route file and its permission-middleware
 * aliases (Roadmap task 1.6, decision-log #9). 'admin.role'/'admin.permission'
 * are route-level aliases, not global middleware — they only apply where a
 * route/group explicitly requests them. The super-admin bypass for
 * 'admin.permission' is checked directly inside `EnsureAdminHasPermission`,
 * not registered here — see that class's own docblock for why a
 * `Gate::before` rule wouldn't actually work for this.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::aliasMiddleware('admin.role', EnsureAdminHasRole::class);
        Route::aliasMiddleware('admin.permission', EnsureAdminHasPermission::class);

        // Explicit 'web' group, not loadRoutesFrom() alone — routes loaded
        // from a ServiceProvider don't get web.php's session/cookie/CSRF
        // middleware automatically, and AdminGuard needs a working session
        // to store the logged-in admin's id (found by actually running the
        // route through a test request, not by inspection).
        Route::middleware('web')->group(base_path('routes/admin.php'));
    }
}
