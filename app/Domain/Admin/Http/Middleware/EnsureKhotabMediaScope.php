<?php

namespace App\Domain\Admin\Http\Middleware;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\Constraints\KhotabMediaScopeConstraint;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
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
    public function __construct(private readonly AdminRecordAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $admin = auth('admin')->user();
        if ($route === null || ! $admin instanceof AdminUser) {
            return $next($request);
        }

        $keys = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(?:admin\.permission|.*EnsureAdminHasPermission):(.+)$/', $middleware, $m) === 1) {
                $keys = array_merge($keys, explode(',', $m[1]));
            }
        }
        $scoped = array_values(array_intersect($keys, KhotabMediaScopeConstraint::SCOPED));
        if (count($keys) !== 1 || count($scoped) !== 1) {
            return $next($request);
        }

        $record = null;
        foreach (['item', 'group', 'series', 'mirror'] as $name) {
            if (is_object($route->parameter($name))) {
                $record = $route->parameter($name);
                break;
            }
        }
        if ($record === null && is_string($route->parameter('media'))) {
            $record = KhotabMediaTarget::fromMedia($route->parameter('media'));
        }
        if ($record !== null && ! $this->authorizer->allows($admin, $scoped[0], $record)) {
            abort(403);
        }

        return $next($request);
    }
}
