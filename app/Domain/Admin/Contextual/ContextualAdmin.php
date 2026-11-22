<?php

namespace App\Domain\Admin\Contextual;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\KhotabMediaScopeGate;
use App\Support\Http\AdminViewer;
use Illuminate\Support\Facades\Auth;

/**
 * Per-request entry point for contextual administration on public pages.
 *
 * `active()` is false for a visitor without touching the database: the admin
 * guard only reads its session key, and the `@contextualAdmin` Blade guard
 * skips building any control at all. For an admin, `visible()` keeps only the
 * controls whose target route the admin would actually pass: the route's
 * `admin.permission` / `admin.role` (`AdminRouteAccess`) AND, for Khotab routes,
 * the audio / video scope on the control's own record (`KhotabMediaScopeGate`,
 * the same code `EnsureKhotabMediaScope` runs — AUTH-01, 2026-11-21).
 *
 * Code outside the Admin domain reaches this same per-request instance through
 * `AdminViewer` (aliased in `ContextualAdminServiceProvider`).
 */
class ContextualAdmin implements AdminViewer
{
    private bool $rendered = false;

    public function __construct(
        private readonly AdminRouteAccess $access,
        private readonly KhotabMediaScopeGate $mediaScope,
        private readonly AdminRecordAuthorizer $records,
    ) {}

    public function admin(): ?AdminUser
    {
        $guard = Auth::guard('admin');

        // Outside a session-bearing request (console, API) there is no admin
        // to find — and the admin guard needs a session to look.
        if (! $guard->hasUser() && ! request()->hasSession()) {
            return null;
        }

        $admin = $guard->user();

        return $admin instanceof AdminUser ? $admin : null;
    }

    public function active(): bool
    {
        return $this->admin() !== null;
    }

    public function can(string $permission): bool
    {
        $admin = $this->admin();

        return $admin !== null && ($admin->hasRole('super-admin') || $admin->hasAnyPermission([$permission]));
    }

    public function adminId(): ?int
    {
        $admin = $this->admin();

        return $admin?->getKey() !== null ? (int) $admin->getKey() : null;
    }

    /**
     * @param  iterable<ContextualControl|null>  $controls  null entries are skipped
     * @return list<ContextualControl>
     */
    public function visible(iterable $controls): array
    {
        $admin = $this->admin();

        if ($admin === null) {
            return [];
        }

        $visible = [];

        foreach ($controls as $control) {
            if ($control === null) {
                continue;
            }
            foreach ($control->authorizingRoutes() as $routeName) {
                if (! $this->access->allows($admin, $routeName)
                    || ! $this->mediaScope->allowsTarget($admin, $routeName, $control->parameters)) {
                    continue 2;
                }
            }
            foreach ($control->recordChecks as [$permission, $record]) {
                if (! $this->records->allows($admin, $permission, $record)) {
                    continue 2;
                }
            }
            $visible[] = $control;
        }

        return $visible;
    }

    /** Called by the component when it outputs controls, so the response is kept private. */
    public function markRendered(): void
    {
        $this->rendered = true;
    }

    public function rendered(): bool
    {
        return $this->rendered;
    }
}
