<?php

namespace App\Domain\Admin\Support;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;

/**
 * Brute-force protection for `POST /admincp/login` (N2, owner-approved 2026-11-08),
 * on Laravel's built-in RateLimiter. Two counters, both of FAILED credential
 * checks only, each over a 15-minute window:
 *
 *  - per client IP:              10 failures;
 *  - per username + client IP:    5 failures.
 *
 * There is no username-only counter and no permanent lockout. A success clears
 * only the username + IP counter — never the IP counter, so one working account
 * cannot reset an IP's budget.
 *
 * Usernames are normalised (trim, Unicode lower-case) so case and surrounding
 * whitespace cannot open a new counter; AdminGuard's own lookup is unchanged.
 * Cache keys and log context hold HMACs (app key) of the IP and
 * username + IP — never the raw values.
 *
 * Window semantics are the framework limiter's: a fixed 15-minute window that
 * starts at a counter's first failure. Counting is strictly atomic only on a store
 * with atomic increments (database, Redis, memcached); on the default `file` store
 * concurrent failures may be under-counted slightly. The client IP is
 * `$request->ip()` — behind a reverse proxy, trusted-proxy configuration is
 * required, or every visitor shares the proxy's IP counter.
 */
final class AdminLoginThrottle
{
    public const IP_MAX_FAILURES = 10;

    public const USERNAME_IP_MAX_FAILURES = 5;

    public const DECAY_SECONDS = 900;

    /** Generic — identical for existing and unknown usernames. */
    public const MESSAGE = 'محاولات دخول كثيرة. من فضلك حاول مرة أخرى بعد قليل.';

    public function __construct(private readonly RateLimiter $limiter) {}

    public function tooManyAttempts(string $clientIp, string $username): bool
    {
        return $this->limiter->tooManyAttempts(self::ipKey($clientIp), self::IP_MAX_FAILURES)
            || $this->limiter->tooManyAttempts(self::usernameIpKey($clientIp, $username), self::USERNAME_IP_MAX_FAILURES);
    }

    /** Seconds until every exceeded counter allows attempts again (the Retry-After value). */
    public function availableIn(string $clientIp, string $username): int
    {
        $seconds = 0;

        if ($this->limiter->tooManyAttempts(self::ipKey($clientIp), self::IP_MAX_FAILURES)) {
            $seconds = max($seconds, $this->limiter->availableIn(self::ipKey($clientIp)));
        }

        if ($this->limiter->tooManyAttempts(self::usernameIpKey($clientIp, $username), self::USERNAME_IP_MAX_FAILURES)) {
            $seconds = max($seconds, $this->limiter->availableIn(self::usernameIpKey($clientIp, $username)));
        }

        return max(1, $seconds);
    }

    /** One failed credential check — one hit on each counter. */
    public function recordFailure(string $clientIp, string $username): void
    {
        $this->limiter->hit(self::ipKey($clientIp), self::DECAY_SECONDS);
        $this->limiter->hit(self::usernameIpKey($clientIp, $username), self::DECAY_SECONDS);
    }

    /** After a successful login: the username + IP counter only. */
    public function clearUsername(string $clientIp, string $username): void
    {
        $this->limiter->clear(self::usernameIpKey($clientIp, $username));
    }

    public function logThrottled(string $clientIp, string $username): void
    {
        Log::warning('admin-login: throttled', [
            'ip_key' => self::digest($clientIp),
            'username_ip_key' => self::digest(self::normalizeUsername($username).'|'.$clientIp),
        ]);
    }

    public static function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username), 'UTF-8');
    }

    public static function ipKey(string $clientIp): string
    {
        return 'admin-login:ip:'.self::digest($clientIp);
    }

    public static function usernameIpKey(string $clientIp, string $username): string
    {
        return 'admin-login:user-ip:'.self::digest(self::normalizeUsername($username).'|'.$clientIp);
    }

    private static function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
