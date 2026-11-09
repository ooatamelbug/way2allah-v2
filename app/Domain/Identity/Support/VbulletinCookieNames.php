<?php

namespace App\Domain\Identity\Support;

/**
 * The single source of the forum-cookie names (N1 / R14-A, 2026-11-08) — read by
 * VbulletinSessionGuard and by the EncryptCookies exemption, so the two can never
 * drift apart.
 *
 * vBulletin builds every auth cookie as `cookie_prefix . name` (vB5
 * `includes/vb5/cookie.php` set() → setInternal(..., addPrefix: true); auth.php
 * sets `sessionhash`, `userid`, `password` that way), so one prefix describes all
 * three. The default `bb_` keeps the names Gen 2's check_login() reads
 * (`w2a_config.php:279-294`); the production forum's real prefix is UNKNOWN and is
 * set through VBULLETIN_COOKIE_PREFIX after owner verification. Only one prefix is
 * ever accepted — no alternative naming is tried.
 *
 * A prefix outside [A-Za-z0-9_] is treated as a misconfiguration: no names are
 * returned, so nothing is exempted from encryption and nobody is authenticated
 * (fail closed).
 */
final class VbulletinCookieNames
{
    public const SESSION_HASH = 'sessionhash';

    public const USER_ID = 'userid';

    public const PASSWORD = 'password';

    public static function prefix(): ?string
    {
        $prefix = config('services.vbulletin.cookie_prefix', 'bb_');

        return is_string($prefix) && preg_match('/^[A-Za-z0-9_]*$/', $prefix) === 1 ? $prefix : null;
    }

    public static function sessionHash(): ?string
    {
        return self::name(self::SESSION_HASH);
    }

    public static function userId(): ?string
    {
        return self::name(self::USER_ID);
    }

    public static function password(): ?string
    {
        return self::name(self::PASSWORD);
    }

    /** @return list<string> the exact cookie names exempted from Laravel's cookie encryption */
    public static function all(): array
    {
        return array_values(array_filter([self::sessionHash(), self::userId(), self::password()], 'is_string'));
    }

    private static function name(string $suffix): ?string
    {
        $prefix = self::prefix();

        return $prefix === null ? null : $prefix.$suffix;
    }
}
