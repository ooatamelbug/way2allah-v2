<?php

namespace App\Domain\Admin\Http\Middleware;

use App\Domain\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained, per-feature gating (decision-log #9) — replaces
 * `sidebar.php`'s `unserialize($author->permissions)` nav-gating check
 * with real Spatie `Permission` rows, namespaced `{module}.{key}` to match
 * each legacy `menu.php`'s `$authorization[$module]` array exactly.
 *
 * A super-admin bypasses this check entirely — mirrors `radminsuper=1`'s
 * legacy behavior (full access, no per-permission check performed at all).
 * Checked directly here, not via `Gate::before`: Spatie's `hasAnyPermission()`
 * is a direct, DB-backed check on the model, not a call routed through
 * Laravel's `Gate` facade, so a `Gate::before` rule would never actually
 * intercept it — confirmed by reading Spatie's `HasPermissions` trait
 * before relying on it, not assumed.
 *
 * Same guard-explicitness reasoning as `EnsureAdminHasRole` — always checks
 * the 'admin' guard specifically, never a bare `can:` string that would
 * resolve against Laravel's default guard instead.
 */
class EnsureAdminHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof AdminUser) {
            abort(401);
        }

        if ($admin->hasRole('super-admin')) {
            return $next($request);
        }

        if (! empty($permissions) && ! $admin->hasAnyPermission($permissions)) {
            abort(403);
        }

        return $next($request);
    }
}
