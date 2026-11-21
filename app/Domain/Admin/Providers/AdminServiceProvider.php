<?php

namespace App\Domain\Admin\Providers;

use App\Domain\Admin\Http\Middleware\EnsureAdminHasPermission;
use App\Domain\Admin\Http\Middleware\EnsureAdminHasRole;
use App\Domain\Admin\Http\Middleware\EnsureKhotabMediaScope;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminDashboardModules;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\Constraints\KhotabMediaScopeConstraint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

/**
 * Registers the Admin domain's route file and its permission-middleware
 * aliases (Roadmap task 1.6, decision-log #9). 'admin.role'/'admin.permission'
 * are route-level aliases, not global middleware — they only apply where a
 * route/group explicitly requests them. The super-admin bypass for
 * 'admin.permission' is checked directly inside `EnsureAdminHasPermission`,
 * not registered here — see that class's own docblock for why a
 * `Gate::before` rule wouldn't actually work for this.
 *
 * `register()` (OLD Admin Migration Phase 0) binds
 * `AdminRecordAuthorizer` as a singleton so its constraint list has one
 * definition per request. The list is empty and stays empty for Mirror
 * Management: decision D1b' is closed (owner, 2026-10-12) — OLD's
 * audio/video moderator division is not reproduced, so mirror
 * authorization is flat Spatie permissions with no record constraint. See
 * `AdminRecordConstraint`'s docblock.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminRecordAuthorizer::class, fn () => new AdminRecordAuthorizer(
            // OLD Khotab audio / video moderator scope — owner instruction
            // 2026-11-17 (strict OLD parity) supersedes D1b'.
            constraints: [new KhotabMediaScopeConstraint],
        ));
    }

    public function boot(): void
    {
        Route::aliasMiddleware('admin.role', EnsureAdminHasRole::class);
        Route::aliasMiddleware('admin.permission', EnsureAdminHasPermission::class);
        Route::aliasMiddleware('admin.khotab-media', EnsureKhotabMediaScope::class);

        // Explicit 'web' group, not loadRoutesFrom() alone — routes loaded
        // from a ServiceProvider don't get web.php's session/cookie/CSRF
        // middleware automatically, and AdminGuard needs a working session
        // to store the logged-in admin's id (found by actually running the
        // route through a test request, not by inspection).
        Route::middleware('web')->group(base_path('routes/admin.php'));

        // AdminCP Full Visual/Layout Parity Reconstruction (2026-08-22):
        // every one of the ~15 feature controllers renders a view that
        // `@extends('layouts.admin')` for its shell/sidebar/header — a view
        // composer here is the one place that supplies the authenticated
        // admin + permission-filtered sidebar module list, instead of every
        // controller repeating `Auth::guard('admin')->user()` +
        // `AdminDashboardModules::visibleFor()` in its own action.
        View::composer('layouts.admin', function (ViewInstance $view) {
            $admin = Auth::guard('admin')->user();

            $view->with('adminUser', $admin);
            $view->with('sidebarModules', $admin instanceof AdminUser
                ? app(AdminDashboardModules::class)->visibleFor($admin)
                : []);
        });
    }
}
