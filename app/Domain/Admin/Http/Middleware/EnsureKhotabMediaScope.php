<?php

namespace App\Domain\Admin\Http\Middleware;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\KhotabMediaScopeGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the Khotab media scope to a route's own record before the controller runs, so a moderator without the
 * section cannot open the form either (OLD's `AvAdmin()` ran on the form pages as well as on the writes). The writes
 * are enforced again inside each action through AdminRecordAuthorizer.
 *
 * Applies only when the route is gated by exactly one scoped key (`admin.permission:<key>`) and carries a
 * section-bearing record ({item} / {group} / {series} / {mirror}, or {author} + {media}); otherwise a no-op.
 */
class EnsureKhotabMediaScope
{
    public function __construct(private readonly KhotabMediaScopeGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $admin = auth('admin')->user();
        if ($route === null || ! $admin instanceof AdminUser) {
            return $next($request);
        }

        // The rule lives in KhotabMediaScopeGate, shared with contextual-control visibility (AUTH-01, 2026-11-21).
        if (! $this->gate->allowsRoute($admin, $route)) {
            abort(403);
        }

        return $next($request);
    }
}
