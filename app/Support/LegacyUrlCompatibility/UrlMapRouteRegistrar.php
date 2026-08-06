<?php

namespace App\Support\LegacyUrlCompatibility;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;

/**
 * Turns config/legacy-url-map.php entries into real Laravel routes
 * (Blueprint v1.0 §11, Roadmap task 0.7). Kept as a directly-callable class
 * rather than inline route-file logic so a single rule can be registered
 * and exercised in isolation (see UrlMapRouteRegistrarTest), independent of
 * whatever the full map currently contains.
 */
class UrlMapRouteRegistrar
{
    public static function registerAll(array $map): void
    {
        foreach ($map as $legacyPath => $rule) {
            static::registerRule($legacyPath, $rule);
        }
    }

    public static function registerRule(string $legacyPath, array $rule): Route
    {
        return match ($rule['type'] ?? null) {
            'redirect' => Router::redirect($legacyPath, $rule['to'], $rule['status'] ?? 301),
            // self::, not static:: — registerPassThrough() is private, so it
            // can never actually be overridden by a subclass; static:: would
            // misleadingly imply otherwise (flagged by PHPStan, decision #3).
            'pass-through' => self::registerPassThrough($legacyPath, $rule),
            default => throw new InvalidArgumentException(
                "Unknown legacy-url-map rule type for '{$legacyPath}' — expected 'redirect' or 'pass-through'."
            ),
        };
    }

    private static function registerPassThrough(string $legacyPath, array $rule): Route
    {
        if (! isset($rule['to'])) {
            throw new InvalidArgumentException("Missing 'to' for pass-through rule '{$legacyPath}'.");
        }

        $route = Router::get($legacyPath, $rule['to']);

        if (! empty($rule['name'])) {
            $route->name($rule['name']);
        }

        return $route;
    }
}
