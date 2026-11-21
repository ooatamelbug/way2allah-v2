<?php

namespace App\Support\Downloads;

use Throwable;

/**
 * Caps how often the DOWNLOAD_CAPACITY_FULL warning reaches the application
 * log (production incident, 2026-11-10: ~5.5 million refusals, a 5.3 GB
 * `laravel.log`). At most one warning per `downloads.rejection_log_interval`
 * seconds; the warning that opens a new window reports how many were
 * suppressed in between. The `download_rejections` row is NOT throttled: every
 * refusal is still recorded there (when `downloads.rejection_log` is on).
 *
 * State is one tiny file next to the slot locks (same host, same permissions),
 * `"<window start> <suppressed>"`, updated under an exclusive `flock` — no
 * database, no cache store, nothing that adds load to an overloaded server.
 *
 * Fails toward visibility: an interval of 0 (or less), or a state file that
 * cannot be used, means every refusal is logged, as before.
 */
final class DownloadRejectionLogThrottle
{
    public const STATE_FILE = 'rejection-log-throttle.state';

    public static function interval(): int
    {
        return max(0, (int) config('downloads.rejection_log_interval'));
    }

    /**
     * Whether this refusal's warning should be written now.
     *
     * @return int|null null = suppress it; an int = write it, carrying how many
     *                  warnings were suppressed since the previous one written
     */
    public function admit(?int $now = null): ?int
    {
        $interval = self::interval();
        if ($interval === 0) {
            return 0;
        }

        $now ??= time();
        $handle = false;

        try {
            $directory = (string) config('downloads.slot_directory');
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return 0;
            }

            $handle = @fopen($directory.'/'.self::STATE_FILE, 'c+');
            if ($handle === false || ! flock($handle, LOCK_EX)) {
                return 0;
            }

            [$windowStart, $suppressed] = array_map('intval', array_pad(explode(' ', trim((string) stream_get_contents($handle))), 2, '0'));

            // Inside the current window (a clock that went backwards opens a new one).
            if ($windowStart > 0 && $now >= $windowStart && $now - $windowStart < $interval) {
                self::write($handle, $windowStart.' '.($suppressed + 1));

                return null;
            }

            self::write($handle, $now.' 0');

            return max(0, $suppressed);
        } catch (Throwable) {
            return 0;
        } finally {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
        }
    }

    /** @param resource $handle */
    private static function write(mixed $handle, string $state): void
    {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $state);
        fflush($handle);
    }
}
