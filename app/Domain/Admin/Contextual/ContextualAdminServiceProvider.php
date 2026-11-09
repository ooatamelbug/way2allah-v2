<?php

namespace App\Domain\Admin\Contextual;

use App\Support\Http\AdminViewer;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * OLD Admin Migration — contextual administration surface: the controls an
 * authenticated admin sees on normal public pages, pointing at the existing
 * Phase 0–7 admin routes.
 *
 * Registers the per-request service, the `@contextualAdmin` Blade guard
 * (false for visitors, so no control is even built), and two `web`
 * middleware: the validated return flow and the private-cache header.
 */
class ContextualAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AdminRouteAccess::class);
        $this->app->scoped(ContextualAdmin::class);

        // An alias, not a second binding: `AdminViewer` resolves to the very
        // instance above, so a Content controller's markRendered() reaches
        // the state KeepContextualPagesPrivate reads.
        $this->app->alias(ContextualAdmin::class, AdminViewer::class);
    }

    public function boot(): void
    {
        Blade::if('contextualAdmin', fn (): bool => app(ContextualAdmin::class)->active());

        // Through the HTTP kernel, not the router: the kernel re-syncs its own
        // middleware groups onto the router when it bootstraps, which would
        // silently drop anything pushed onto the router directly.
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', HonorContextualReturn::class);
        $kernel->appendMiddlewareToGroup('web', KeepContextualPagesPrivate::class);
    }
}
