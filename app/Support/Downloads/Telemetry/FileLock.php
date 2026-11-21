<?php

namespace App\Support\Downloads\Telemetry;

/**
 * An exclusive `flock()` that never waits longer than a budget: non-blocking
 * attempts with short sleeps, so a download request can never be held up by
 * telemetry beyond the configured time.
 */
final class FileLock
{
    /**
     * @param  resource  $handle
     * @param  int  $spins  incremented once per failed attempt (contention measure)
     */
    public static function acquire(mixed $handle, int $budgetUs, int &$spins = 0): bool
    {
        $deadline = hrtime(true) + max(0, $budgetUs) * 1000;

        while (true) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return true;
            }
            $spins++;

            $remainingUs = intdiv($deadline - hrtime(true), 1000);
            if ($remainingUs <= 0) {
                return false;
            }
            usleep(min(50, $remainingUs));
        }
    }

    /**
     * A QUEUED exclusive lock with a time limit — for background processes only (the sealer), never on the
     * request path. Polling with LOCK_NB can starve against a stream of short writer locks; a blocking flock()
     * waits in the kernel's queue instead. The wait is bounded by SIGALRM (whole seconds, at least one),
     * installed without SA_RESTART so the interrupted flock() returns; the previous handler and alarm are
     * restored. Without pcntl it falls back to acquire().
     *
     * @param  resource  $handle
     */
    public static function acquireQueued(mixed $handle, int $budgetUs): bool
    {
        if (! function_exists('pcntl_alarm') || ! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return self::acquire($handle, $budgetUs);
        }

        if (flock($handle, LOCK_EX | LOCK_NB)) {
            return true;
        }
        if ($budgetUs <= 0) {
            return false;
        }

        $previousAsync = pcntl_async_signals(true);
        $previousHandler = pcntl_signal_get_handler(SIGALRM);
        pcntl_signal(SIGALRM, static function (): void {}, false);
        $previousAlarm = pcntl_alarm((int) max(1, ceil($budgetUs / 1_000_000)));

        try {
            return flock($handle, LOCK_EX);   // EINTR on the alarm → false
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);
            if ($previousAlarm > 0) {
                pcntl_alarm($previousAlarm);
            }
        }
    }
}
