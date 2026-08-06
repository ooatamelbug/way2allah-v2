<?php

namespace App\Domain\Admin\Http\Middleware;

use App\Domain\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-group-level permission gating for the Admin domain (Blueprint v1.0
 * §7, Roadmap task 1.6) — ready for Wave 5's admincp features, nothing
 * uses it yet.
 *
 * Deliberately always checks the 'admin' guard specifically, rather than
 * being a thin pass-through to Spatie's own RoleMiddleware with a bare
 * `role:some-role` string. Spatie's middleware resolves against Laravel's
 * *default* guard unless a guard is explicitly appended
 * (`role:some-role,admin`) — forgetting that suffix on any one route would
 * silently check the wrong guard's user instead of failing loudly. Wrapping
 * it here makes that mistake structurally impossible for every admin route
 * at once instead of relying on every future route definition remembering
 * the suffix correctly.
 */
class EnsureAdminHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = Auth::guard('admin')->user();

        // The instanceof check (added while wiring up PHPStan, pre-Wave-4
        // decision #3) isn't just for the type-checker: 'admin' is always
        // registered with AdminGuard (config/auth.php), which always
        // resolves an AdminUser or null — but the Guard contract's return
        // type is the wider Authenticatable. This makes a future
        // misconfiguration (a different guard implementation registered
        // under 'admin') fail safely (401) instead of a fatal error on
        // ->hasAnyRole(), which only exists via AdminUser's HasRoles trait.
        if (! $admin instanceof AdminUser) {
            abort(401);
        }

        if (! empty($roles) && ! $admin->hasAnyRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
