<?php

namespace App\Support\Downloads;

use App\Support\Downloads\Telemetry\DownloadTelemetry;
use App\Support\Downloads\Telemetry\DownloadTelemetryContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The site's ONE global download-capacity pool (config/downloads.php).
 *
 * It protects long-lived PHP file transfers, not routes named "download":
 * every endpoint that sends a file's bytes through PHP holds one PHP-CGI
 * process and one Apache worker for the whole transfer, and enough of them
 * exhaust MaxRequestWorkers. Each such endpoint takes a slot here before it
 * streams (ProxiedDownloadStreamer keeps it for the whole transfer), and every
 * one of them competes for the SAME slots. Redirect-only endpoints, static
 * files and external links never come here — they hold nothing while the
 * file moves. Today the protected endpoints are /khotab-download-* and
 * /khotab-mirror-*.
 *
 * How: N lock files `slot-001.lock` … `slot-N.lock`. A request tries each one
 * with a NON-blocking exclusive `flock()` and keeps the first it gets for the
 * life of the transfer (DownloadSlot). If every slot is locked it is refused
 * at once with 503 + Retry-After — it never waits, never queues, and the
 * worker is freed immediately. The file names and the directory are the ones
 * the earlier Khotab-only guard used, so the two always share one pool.
 *
 * The OS lock is the only source of truth. A slot file existing means
 * nothing; there is no counter to drift; a crashed or killed process loses
 * its lock automatically, so capacity cannot leak.
 *
 * Failure policy: if the slot directory or every slot file cannot be opened
 * (misconfiguration, permissions), the guard fails OPEN — the download
 * proceeds and a warning is logged. A broken guard must never turn into a
 * download outage. Telemetry is a different matter: it runs only after the
 * decision to refuse, and nothing it does can undo that decision.
 */
final class DownloadCapacityGuard
{
    /** The pool every protected endpoint shares. */
    public const POOL = 'global';

    /** The refusal text; `%d` is the configured retry interval in seconds. */
    public const MESSAGE = 'عذرًا، يوجد ضغط حاليًا على خدمة التحميل. يرجى المحاولة مرة أخرى بعد %d ثانية.';

    /** Slots found locked by the last refused tryAcquire() — the downloads in progress. */
    private int $activeAtRefusal = 0;

    public function __construct(private readonly DownloadRejectionRecorder $recorder) {}

    public static function retryAfter(): int
    {
        return max(1, (int) config('downloads.retry_after'));
    }

    public static function limit(): int
    {
        return max(1, (int) config('downloads.max_concurrent'));
    }

    public static function message(int $retryAfter): string
    {
        return sprintf(self::MESSAGE, $retryAfter);
    }

    /**
     * A held slot, an unguarded pass (guard disabled, or failing open), or
     * null when every slot is taken.
     */
    public function tryAcquire(): ?DownloadSlot
    {
        if (! config('downloads.guard')) {
            return DownloadSlot::unguarded();
        }

        $max = self::limit();
        $directory = (string) config('downloads.slot_directory');

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            self::warnFailingOpen('slot directory unavailable', $directory);

            return DownloadSlot::unguarded();
        }

        // Start at a random slot so concurrent requests do not all contend
        // for slot-001 first.
        $start = random_int(0, $max - 1);
        $openFailures = 0;

        for ($i = 0; $i < $max; $i++) {
            $number = (($start + $i) % $max) + 1;
            $path = sprintf('%s/slot-%03d.lock', $directory, $number);
            $handle = @fopen($path, 'c');

            if ($handle === false) {
                $openFailures++;

                continue;
            }

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                // Phase 3B monitoring: the slot file's mtime marks when this slot was taken, so a read-only monitor
                // can report long-running holders. Best effort; it never affects acquiring or holding the slot.
                @touch($path);

                return DownloadSlot::held($handle);
            }

            fclose($handle);
        }

        if ($openFailures === $max) {
            self::warnFailingOpen('no slot file could be opened', $directory);

            return DownloadSlot::unguarded();
        }

        // Every slot that could be opened was locked by another download.
        $this->activeAtRefusal = $max - $openFailures;

        return null;
    }

    /**
     * Record a refused download, then refuse it (see rejection()). Called
     * only after tryAcquire() returned null, and before any counter, file or
     * network work. The context carries only what the caller already loaded —
     * nothing is fetched to fill it.
     */
    public function refuse(Request $request, DownloadRejectionContext $context): Response
    {
        $this->recorder->record($request, $context, $this->activeAtRefusal, self::limit(), self::retryAfter());

        // Phase 3A telemetry (off by default; never throws, never touches the database).
        try {
            app(DownloadTelemetry::class)->emit($request, 'capacity_rejected',
                new DownloadTelemetryContext($context->module, $context->resourceType, $context->resourceId, $context->variant, $context->variantId),
                ['st' => 'rejected', 'pa' => $this->activeAtRefusal, 'pl' => self::limit()]);
        } catch (Throwable) {
            // telemetry must never affect the refusal
        }

        return $this->rejection();
    }

    /** The immediate refusal: 503, Retry-After, never cached, no automatic retry or refresh. */
    public function rejection(): Response
    {
        $retryAfter = self::retryAfter();

        return new Response(self::message($retryAfter), 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Retry-After' => (string) $retryAfter,
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private static function warnFailingOpen(string $problem, string $directory): void
    {
        try {
            Log::warning("Download capacity guard: {$problem} — failing open", [
                'event' => 'DOWNLOAD_CAPACITY_GUARD_FAIL_OPEN',
                'directory' => $directory,
            ]);
        } catch (Throwable) {
            // A broken log channel must not stop the download either.
        }
    }
}
