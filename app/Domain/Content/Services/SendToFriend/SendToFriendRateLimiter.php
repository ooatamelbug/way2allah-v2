<?php

namespace App\Domain\Content\Services\SendToFriend;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * One rate limiter for all three send-to-friend endpoints (#106, owner-approved
 * 2026-11-08) — Khotab, Anasheed and Fatwa share both quotas, so switching module
 * does not reset anything:
 *
 *  - per client IP:            at most 3 sends in any rolling 10 minutes;
 *  - per normalised recipient: at most 10 sends in any rolling 24 hours.
 *
 * Accounting: attempt() is called only after a request passed CSRF, input
 * validation and the item lookup, and before any mail is dispatched. An allowed
 * call records one attempt against BOTH quotas; a refused call records nothing
 * (so a refusal by one limit never consumes the other). A later mail-transport
 * failure does not refund the attempt.
 *
 * Mechanism: a per-subject log of attempt timestamps in the limiter cache store
 * (`cache.limiter`, else the default store), pruned to the window on every check —
 * a true rolling window. Both subjects are locked (cache locks, fixed order) for
 * the check-and-record, so the two limits are enforced together atomically as long
 * as the store's locks are shared by every worker (database, Redis or memcached in
 * production; a file store only on a single host). If a lock cannot be obtained in
 * time, or the store has no locks, the attempt is refused (fail closed).
 *
 * Cache keys hold an HMAC (app key) of the IP / normalised address, never the raw
 * value.
 */
final class SendToFriendRateLimiter
{
    public const IP_LIMIT = 3;

    public const IP_WINDOW_SECONDS = 600;

    public const RECIPIENT_LIMIT = 10;

    public const RECIPIENT_WINDOW_SECONDS = 86400;

    /** Shown on HTTP 429 — names no recipient and no limit details beyond "try later". */
    public const REFUSED_MESSAGE = 'عفوا ، تم تجاوز الحد المسموح لإرسال المواد. من فضلك حاول مرة اخرى لاحقا';

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    /** Allowed → the attempt is recorded against both quotas; refused → nothing is recorded. */
    public function attempt(string $clientIp, string $recipientEmail): bool
    {
        $store = $this->store();

        if (! $store->getStore() instanceof LockProvider) {
            return false;
        }

        $subjects = [
            ['key' => self::ipKey($clientIp), 'limit' => self::IP_LIMIT, 'window' => self::IP_WINDOW_SECONDS],
            ['key' => self::recipientKey($recipientEmail), 'limit' => self::RECIPIENT_LIMIT, 'window' => self::RECIPIENT_WINDOW_SECONDS],
        ];

        $lockKeys = array_column($subjects, 'key');
        sort($lockKeys); // fixed order — no deadlock between concurrent requests

        $locks = [];

        try {
            foreach ($lockKeys as $key) {
                $lock = $store->getStore()->lock($key.':lock', self::LOCK_SECONDS);
                $lock->block(self::LOCK_WAIT_SECONDS);
                $locks[] = $lock;
            }

            $now = Carbon::now()->getTimestamp();
            $logs = [];

            foreach ($subjects as $i => $subject) {
                $log = $this->recentAttempts($store, $subject['key'], $now, $subject['window']);

                if (count($log) >= $subject['limit']) {
                    return false;
                }

                $logs[$i] = $log;
            }

            foreach ($subjects as $i => $subject) {
                $logs[$i][] = $now;
                $store->put($subject['key'], $logs[$i], $subject['window']);
            }

            return true;
        } catch (LockTimeoutException) {
            return false;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Trim; lower-case the domain only. The local part is kept exactly as typed —
     * no dot or "+tag" folding, which is provider-specific and could merge
     * different mailboxes.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        return substr($email, 0, $at).'@'.strtolower(substr($email, $at + 1));
    }

    public static function ipKey(string $clientIp): string
    {
        return 'send-to-friend:ip:'.self::digest($clientIp);
    }

    public static function recipientKey(string $recipientEmail): string
    {
        return 'send-to-friend:to:'.self::digest(self::normalizeEmail($recipientEmail));
    }

    private static function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.limiter'));
    }

    /** @return list<int> */
    private function recentAttempts(Repository $store, string $key, int $now, int $window): array
    {
        $log = $store->get($key, []);

        if (! is_array($log)) {
            return [];
        }

        return array_values(array_filter($log, fn ($t) => is_int($t) && $t > $now - $window));
    }
}
