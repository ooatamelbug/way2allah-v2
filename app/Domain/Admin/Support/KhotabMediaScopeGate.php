<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\Constraints\KhotabMediaScopeConstraint;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The ONE implementation of the Khotab audio / video route scope (2026-11-17), shared by:
 *
 *  - `EnsureKhotabMediaScope` (the middleware that refuses the request), through `allowsRoute()`;
 *  - `ContextualAdmin::visible()` (which control a public page shows), through `allowsTarget()` — AUTH-01
 *    (2026-11-21): visibility used to check only the route's `admin.permission` / `admin.role`, so a moderator
 *    without the record's section saw Khotab controls that then answered 403.
 *
 * Rule (unchanged): it applies only when the route is gated by exactly one key and that key is section-scoped
 * (`KhotabMediaScopeConstraint::SCOPED`), and only to a section-bearing record — {item} / {group} / {series} /
 * {mirror}, or {author} + {media}. The record is then authorized through `AdminRecordAuthorizer` (super-admin
 * passes; otherwise `khotab.media_audio` / `khotab.media_video` for the record's section).
 */
final class KhotabMediaScopeGate
{
    public const MIDDLEWARE = 'admin.khotab-media';

    /**
     * Bound routes by route + parameters. Only the record binding is memoised (it does not depend on who asks); the
     * decision is re-evaluated on every call, so a permission granted or revoked is reflected at once even where this
     * instance outlives one request (tests, long-lived workers).
     *
     * @var array<string, Route|false|null>
     */
    private array $bound = [];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly Router $router,
    ) {}

    /** The route's single section-scoped key, or null when the scope does not apply to it. */
    public function scopedKey(Route $route): ?string
    {
        $keys = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(?:admin\.permission|.*EnsureAdminHasPermission):(.+)$/', $middleware, $m) === 1) {
                $keys = array_merge($keys, explode(',', $m[1]));
            }
        }
        $scoped = array_values(array_intersect($keys, KhotabMediaScopeConstraint::SCOPED));

        return count($keys) === 1 && count($scoped) === 1 ? $scoped[0] : null;
    }

    /** Would the scope let $admin through this already-bound route? (The middleware's decision.) */
    public function allowsRoute(AdminUser $admin, Route $route): bool
    {
        $scoped = $this->scopedKey($route);
        if ($scoped === null) {
            return true;
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

        return $record === null || $this->authorizer->allows($admin, $scoped, $record);
    }

    /**
     * Would the scope let $admin open route $routeName with $parameters? Used for visibility, before any request is
     * made: the route is bound exactly as the real request would bind it (same URL, same model bindings), then
     * `allowsRoute()` decides. Routes without the scope middleware are never affected.
     *
     * @param  array<array-key, mixed>  $parameters  the control's route parameters
     */
    public function allowsTarget(AdminUser $admin, string $routeName, array $parameters): bool
    {
        $route = $this->router->getRoutes()->getByName($routeName);
        if ($route === null || ! in_array(self::MIDDLEWARE, $route->gatherMiddleware(), true) || $this->scopedKey($route) === null) {
            return true;
        }
        if ($admin->hasRole('super-admin')) {
            return true; // AdminRecordAuthorizer's first rule — skip resolving the record
        }

        $key = $routeName.'|'.json_encode($parameters);
        $bound = $this->bound[$key] ??= $this->bind($route, $parameters);
        if ($bound === null) {
            return false; // the binding itself failed: fail closed, the real request would fail too
        }
        if ($bound === false) {
            // The record is gone: the real request would answer 404 before the scope runs. Visibility is left to the
            // route permission, exactly as before AUTH-01 (the scope has no record to judge).
            return true;
        }

        try {
            return $this->allowsRoute($admin, $bound);
        } catch (Throwable) {
            // e.g. the media permission definitions are not seeded: the real request would fail too.
            return false;
        }
    }

    /**
     * The route bound exactly as the real request would bind it (same URL, same model bindings), or false when its
     * record does not exist, or null when binding failed for any other reason.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    private function bind(Route $route, array $parameters): Route|false|null
    {
        try {
            $method = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD') ?? 'GET';
            $bound = clone $route; // never mutate the shared route collection's instance
            $bound->bind(Request::create(route((string) $route->getName(), $parameters, false), $method));
            $this->router->substituteBindings($bound);
            $this->router->substituteImplicitBindings($bound);

            return $bound;
        } catch (ModelNotFoundException|NotFoundHttpException|UrlGenerationException) {
            return false;
        } catch (Throwable) {
            return null;
        }
    }
}
