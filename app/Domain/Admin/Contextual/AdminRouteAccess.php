<?php

namespace App\Domain\Admin\Contextual;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Routing\Router;
use InvalidArgumentException;

/**
 * Answers "would this admin get past the target route's own authorization
 * middleware?" by reading that route's `admin.role` / `admin.permission`
 * entries and applying them exactly as `EnsureAdminHasRole` and
 * `EnsureAdminHasPermission` do:
 *
 *   - `admin.role`            any authenticated admin;
 *   - `admin.role:a,b`        must hold one of the roles;
 *   - `admin.permission:a,b`  super-admin passes; otherwise one of the permissions.
 *
 * Every entry must pass. The route is therefore the single source of truth
 * for who sees a contextual control — no permission name is repeated in the
 * control definitions. A route with no admin middleware, or an unknown route
 * name, is a programming error and fails loudly rather than rendering a
 * control that would lead nowhere or bypass a check.
 */
class AdminRouteAccess
{
    /** @var array<string, list<array{0: string, 1: list<string>}>> */
    private array $requirements = [];

    public function __construct(private readonly Router $router) {}

    public function allows(AdminUser $admin, string $routeName): bool
    {
        foreach ($this->requirementsFor($routeName) as [$kind, $values]) {
            $passes = $kind === 'role'
                ? ($values === [] || $admin->hasAnyRole($values))
                : ($admin->hasRole('super-admin') || $values === [] || $admin->hasAnyPermission($values));

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{0: string, 1: list<string>}> */
    public function requirementsFor(string $routeName): array
    {
        if (isset($this->requirements[$routeName])) {
            return $this->requirements[$routeName];
        }

        $route = $this->router->getRoutes()->getByName($routeName)
            ?? throw new InvalidArgumentException("Contextual control targets an unknown route [{$routeName}].");

        $requirements = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name, $arguments] = array_pad(explode(':', $middleware, 2), 2, '');

            if ($name === 'admin.role' || $name === 'admin.permission') {
                $requirements[] = [
                    $name === 'admin.role' ? 'role' : 'permission',
                    array_values(array_filter(explode(',', $arguments), fn (string $v) => $v !== '')),
                ];
            }
        }

        if ($requirements === []) {
            throw new InvalidArgumentException("Contextual control targets [{$routeName}], which has no admin authorization middleware.");
        }

        return $this->requirements[$routeName] = $requirements;
    }
}
