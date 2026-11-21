<?php

namespace App\Support\Downloads;

use App\Support\Downloads\Telemetry\TransferTelemetry;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Sends a file's bytes THROUGH PHP — the one long-lived transfer the global
 * download-capacity pool exists for. Moved from
 * `KhotabItemController::streamFile()` so every endpoint that proxies a file
 * goes through one place, and none can stream without a slot.
 *
 * INVARIANT: any endpoint that proxies a large or long-lived file through PHP
 * must take a slot from DownloadCapacityGuard and stream through this class,
 * and so joins the same global pool. A redirect-only endpoint must not.
 *
 * Slot lifetime: the caller acquired `$slot` before counting anything. The
 * StreamedResponse callback captures it, so it stays held for the whole
 * transfer — Symfony runs the callback in `send()`, after the controller and
 * the middleware stack have returned — and releases it, and closes the source
 * handle, in `finally` when the transfer ends for ANY reason. A request that
 * 404s, or whose source is rejected, before streaming releases the slot here
 * too; if the process dies, or PHP aborts the request on a client disconnect
 * (where `finally` does not run), request shutdown closes both descriptors.
 *
 * ------------------------------------------------------------------
 * Source validation before the response is committed (2026-11-10)
 * ------------------------------------------------------------------
 * The source is opened in `stream()` — before Symfony sends the status line
 * and headers — so a bad source becomes a real HTTP 500 "Error reading file"
 * (as legacy `download_khotab()` returned), never a 200 "download" of nothing
 * or of an error page:
 *
 * - remote: the FINAL response (after redirects) must be a 2xx other than
 *   204/205/206, and must not declare `Content-Type: text/html`. There is
 *   deliberately no MIME allowlist;
 * - remote `Content-Length` is passed to the visitor only when trustworthy:
 *   exactly one header on the final response, a plain non-negative integer,
 *   no `Transfer-Encoding`, and no `Content-Encoding` other than `identity`;
 * - local: the size comes from `fstat()` of the opened regular file. The size
 *   stored in the database is never used.
 *
 * With a declared size the copy never sends more than it (a longer source, or
 * a local file that grew, is cut at the declared size), and a source that ends
 * early is logged once as DOWNLOAD_SOURCE_INCOMPLETE; the visitor's client then
 * sees a body shorter than its Content-Length, i.e. a failed download rather
 * than a silently truncated "complete" file.
 *
 * HEAD requests are unchanged: Symfony never runs a HEAD response's body, so
 * the source is not opened and no Content-Length is added.
 *
 * ------------------------------------------------------------------
 * Stalled-source fix (production incident, 2026-11-10)
 * ------------------------------------------------------------------
 * The earlier loop was `while (! feof($h)) echo fread($h, 8192);` with no
 * stream timeouts. When a remote source stops sending without closing the
 * connection, `fread()` returns `false` once per `default_socket_timeout` (or
 * blocks for ever when that is -1) and `feof()` stays false, so the loop never
 * ends: the process kept its slot, its Apache worker and its remote socket
 * (400/400 slots, remote connections idle ~50 h). And because nothing is
 * written while the source is silent, PHP never learns the visitor has left.
 *
 * Two timeouts now apply, and deliberately no total-duration limit, because
 * large files over slow links legitimately take a long time:
 *
 * - connect timeout (`downloads.remote_connect_timeout`): remote links only,
 *   via the http(s) stream context. PHP's http wrapper applies it PER
 *   OPERATION, not as an end-to-end deadline: to the TCP/TLS connect, and to
 *   EACH read while waiting for the response headers, again for every redirect
 *   hop (up to PHP's default of 20). A server that answers slowly but steadily,
 *   or a chain of slow redirects, can therefore take longer in total; DNS
 *   resolution is not covered at all (the system resolver's own timeouts
 *   apply). A source that goes completely silent is bounded;
 * - idle-read timeout (`downloads.remote_idle_timeout`): the longest the
 *   source may go without delivering a single byte. Enforced twice: a socket
 *   read timeout (`stream_set_timeout`), so one blocking read cannot outlast
 *   it, and a wall-clock "time since the last byte" check, so repeated empty
 *   or timed-out reads cannot either. Over TLS, PHP waits up to one timeout to
 *   fill a read buffer, so a stall is detected after up to twice the setting.
 *
 * Read results: data → written and flushed; `''` / timed-out `false` → no
 * progress (the idle clock keeps running); `false` without a timeout and
 * without EOF → stream error, stop; EOF → done (or incomplete, see above). A
 * stream exception ends the transfer as a read failure. Read warnings are
 * suppressed and replaced by one structured log line (DOWNLOAD_SOURCE_*):
 * after the headers are out, an exception can only append an error page to the
 * visitor's file and a stack trace to the log.
 *
 * Client disconnection: each chunk is flushed (as legacy `download_khotab()`
 * did), so the write that follows a disconnect reaches the SAPI, fails, and
 * PHP aborts the request (`ignore_user_abort` off, PHP's default). With
 * `ignore_user_abort` on, `connection_aborted()` is checked after every write
 * and the loop stops. While the SOURCE is silent nothing can be written, so
 * PHP cannot notice the visitor leaving; the idle-read timeout bounds that.
 *
 * ------------------------------------------------------------------
 * G-01 fix (Migration Gap Register)
 * ------------------------------------------------------------------
 * Legacy `download_khotab()` (`khotab/functions.php:993-1032`) never checks
 * the link exists before opening it — it calls `@fopen($_link, 'rb')` for any
 * non-empty link, local or remote. So `is_file()` is skipped only for
 * `http://`/`https://` links; every other non-empty link (local paths,
 * garbled/placeholder strings) must still be a real file, or it 404s.
 *
 * ------------------------------------------------------------------
 * MariaDB connection-exhaustion fix (production incident, 2026-09-27)
 * ------------------------------------------------------------------
 * The loop advances at the client's download speed — a 1.26 GiB archive.org
 * mirror measured 20-60+ minutes. Laravel keeps the request's PDO handle open
 * for the whole request, so every in-flight download pinned one idle MariaDB
 * connection (287 downloads against `max_connections = 300`). So the
 * connection is released **before** the copy loop starts: the record is
 * already loaded, the counter already incremented, and `$link` is a plain
 * string captured by the closure. Nothing inside the callback touches the
 * database (the timeouts are read before it; logging goes to files), so
 * nothing reconnects. A REMOTE source is opened and validated before the
 * response is committed, so the connection is released before that open too:
 * a slow or dead remote host must not pin a connection for its connect
 * timeout (2026-09-27 again). `$connectionToRelease` comes from the caller's model
 * (`Model::getConnectionName()`), never hardcoded.
 *
 * Releasing the database does NOT free the Apache worker or the PHP process:
 * the whole file still passes through PHP — which is why the slot is held.
 *
 * ------------------------------------------------------------------
 * Telemetry (Phase 3A, off by default)
 * ------------------------------------------------------------------
 * An optional TransferTelemetry records the lifecycle: head_answered |
 * source_open_failed | source_rejected | transfer_started → transfer_finished.
 * It is called AFTER the slot is released in every path, holds neither the
 * slot nor the handle, never throws and never touches the database. Timeouts,
 * capacity and the response itself are identical with or without it.
 */
final class ProxiedDownloadStreamer
{
    public const CHUNK_BYTES = 8192;

    public const DEFAULT_CONNECT_TIMEOUT = 15.0;

    public const DEFAULT_IDLE_TIMEOUT = 60.0;

    public const OUTCOME_COMPLETE = 'complete';

    public const OUTCOME_CLIENT_ABORTED = 'client_aborted';

    public const OUTCOME_SOURCE_STALLED = 'source_stalled';

    public const OUTCOME_SOURCE_READ_FAILED = 'source_read_failed';

    public const OUTCOME_SOURCE_INCOMPLETE = 'source_incomplete';

    /** Successful statuses that still cannot be a whole file. */
    private const NOT_A_FILE_STATUSES = [204, 205, 206];

    /** Pause after an empty read that neither timed out nor reached EOF, so a misbehaving stream cannot spin a CPU. */
    private const EMPTY_READ_BACKOFF_MICROSECONDS = 20_000;

    /** @var Closure(): bool */
    private readonly Closure $clientAborted;

    /** @param (Closure(): bool)|null $clientAborted test seam; defaults to PHP's connection_aborted() */
    public function __construct(?Closure $clientAborted = null)
    {
        $this->clientAborted = $clientAborted ?? static fn (): bool => connection_aborted() === 1;
    }

    public static function connectTimeout(): float
    {
        return self::positiveSeconds(config('downloads.remote_connect_timeout'), self::DEFAULT_CONNECT_TIMEOUT);
    }

    public static function idleTimeout(): float
    {
        return self::positiveSeconds(config('downloads.remote_idle_timeout'), self::DEFAULT_IDLE_TIMEOUT);
    }

    /**
     * HEAD for a proxied download (Phase 3B HEAD correction, owner-approved 2026-11-11): answered WITHOUT a slot,
     * without counting, and without contacting the source. Same 404 rules and the same headers as the previous HEAD
     * answer (status 200, `Content-Disposition: attachment; filename=…`, no Content-Length). Emits `head_answered`.
     */
    public function head(?string $link, ?TransferTelemetry $telemetry = null): StreamedResponse
    {
        $isRemote = $link !== null && (str_starts_with($link, 'http://') || str_starts_with($link, 'https://'));

        if ($link === null || $link === '' || (! $isRemote && ! is_file($link))) {
            $telemetry?->sourceFailed('source_open_failed', 'not_found', null, null);
            abort(404, 'File not found');
        }

        $telemetry?->head();

        return response()->streamDownload(static function (): void {}, basename($link), DownloadMime::headers($link, $isRemote));
    }

    public function stream(DownloadSlot $slot, ?string $link, ?string $connectionToRelease = null, ?TransferTelemetry $telemetry = null): StreamedResponse
    {
        $isRemote = $link !== null && (str_starts_with($link, 'http://') || str_starts_with($link, 'https://'));

        if ($link === null || $link === '' || (! $isRemote && ! is_file($link))) {
            $slot->release();
            $telemetry?->sourceFailed('source_open_failed', 'not_found', null, null);
            abort(404, 'File not found');
        }

        // HEAD, unchanged: Symfony never runs a HEAD response's body, so the
        // source is never opened and no Content-Length is added. The slot is
        // freed when the response is destroyed at the end of the request.
        if (request()->isMethod('HEAD')) {
            $telemetry?->head();

            return response()->streamDownload(static function () use ($slot) {
                $slot->release();
            }, basename($link), DownloadMime::headers($link, $isRemote));
        }

        // Plain values, read now: nothing in the callback may need the container's config later.
        $connectTimeout = self::connectTimeout();
        $idleTimeout = self::idleTimeout();

        // Remote: release the database BEFORE opening the source as well, so a
        // slow or dead host (up to the connect timeout per connect / header
        // read / redirect hop) never pins a MariaDB connection. Local files
        // open at once; their release stays in the callback, as before. The
        // callback releases again in case anything later reconnected.
        if ($isRemote && $connectionToRelease !== null) {
            DB::disconnect($connectionToRelease);
        }

        // Opened and validated BEFORE any status line or header is sent.
        $openedAt = microtime(true);
        [$handle, $declaredLength, $sourceStatus] = $this->openValidated($slot, $link, $isRemote, $connectTimeout, $telemetry);
        $openMs = (int) round((microtime(true) - $openedAt) * 1000);

        return response()->streamDownload(function () use ($handle, $declaredLength, $link, $connectionToRelease, $slot, $idleTimeout, $telemetry, $isRemote, $sourceStatus, $openMs) {
            $outcome = null;
            try {
                // Release the database BEFORE the long transfer. Everything
                // needed below is already a plain local.
                if ($connectionToRelease !== null) {
                    DB::disconnect($connectionToRelease);
                }

                $telemetry?->started(self::sourceHost($link, $isRemote), $sourceStatus, $declaredLength, $openMs);
                $outcome = $this->pump($handle, $link, $idleTimeout, $declaredLength, $telemetry);
            } finally {
                if (is_resource($handle)) {
                    @fclose($handle);
                }
                $slot->release();
                $telemetry?->finished(self::telemetryOutcome($outcome));
            }
        }, basename($link), DownloadMime::headers($link, $isRemote) + ($declaredLength === null ? [] : ['Content-Length' => (string) $declaredLength]));
    }

    /**
     * Copy the source to the output until EOF, the declared length, a stall, a
     * read error or a client disconnect. Never sends more than `$declaredLength`
     * bytes. Never throws: the response headers are already sent. Does not
     * close `$handle` (the caller owns it).
     *
     * @param  resource  $handle
     * @return self::OUTCOME_*
     */
    public function pump(mixed $handle, string $link, float $idleTimeout, ?int $declaredLength = null, ?TransferTelemetry $telemetry = null): string
    {
        $seconds = (int) floor($idleTimeout);
        @stream_set_timeout($handle, $seconds, (int) round(($idleTimeout - $seconds) * 1_000_000));

        $started = microtime(true);
        $lastByteAt = $started;
        $bytes = 0;

        try {
            while (true) {
                $remaining = $declaredLength === null ? null : $declaredLength - $bytes;
                if ($remaining === 0) {
                    return self::OUTCOME_COMPLETE;   // anything the source sends beyond its declared size is not sent
                }

                $chunk = @fread($handle, $remaining === null ? self::CHUNK_BYTES : min(self::CHUNK_BYTES, $remaining));

                if (is_string($chunk) && $chunk !== '') {
                    echo $chunk;
                    flush();
                    $bytes += strlen($chunk);
                    $lastByteAt = microtime(true);
                    if ($telemetry !== null) {
                        $telemetry->bytes = $bytes;
                    }

                    if (($this->clientAborted)()) {
                        return self::OUTCOME_CLIENT_ABORTED;
                    }

                    continue;
                }

                if (feof($handle)) {
                    if ($remaining !== null) {
                        self::report('DOWNLOAD_SOURCE_INCOMPLETE', $link, self::progress($bytes, $started, $declaredLength));

                        return self::OUTCOME_SOURCE_INCOMPLETE;
                    }

                    return self::OUTCOME_COMPLETE;
                }

                $timedOut = stream_get_meta_data($handle)['timed_out'];

                if ($chunk === false && ! $timedOut) {
                    self::report('DOWNLOAD_SOURCE_READ_FAILED', $link, self::progress($bytes, $started, $declaredLength));

                    return self::OUTCOME_SOURCE_READ_FAILED;
                }

                if (microtime(true) - $lastByteAt >= $idleTimeout) {
                    self::report('DOWNLOAD_SOURCE_STALLED', $link, self::progress($bytes, $started, $declaredLength) + ['idle_timeout_seconds' => $idleTimeout]);

                    return self::OUTCOME_SOURCE_STALLED;
                }

                if (! $timedOut) {
                    usleep(self::EMPTY_READ_BACKOFF_MICROSECONDS);
                }
            }
        } catch (Throwable $e) {
            self::report('DOWNLOAD_SOURCE_READ_FAILED', $link, self::progress($bytes, $started, $declaredLength) + [
                'exception' => $e::class,
                'exception_message' => self::redactMessage($e->getMessage()),
            ]);

            return self::OUTCOME_SOURCE_READ_FAILED;
        }
    }

    /**
     * Open the source and decide whether it may be sent, before anything is
     * committed to the visitor. On failure: close what was opened, free the
     * slot, log once, and answer a real 500 "Error reading file".
     *
     * @return array{0: resource, 1: int|null, 2: int|null} the open handle, the trustworthy length (if any) and the final remote status
     */
    private function openValidated(DownloadSlot $slot, string $link, bool $isRemote, float $connectTimeout, ?TransferTelemetry $telemetry = null): array
    {
        $handle = false;
        $event = null;
        $context = [];
        $declaredLength = null;

        try {
            $handle = $this->open($link, $isRemote, $connectTimeout);

            if ($handle === false) {
                $event = 'DOWNLOAD_SOURCE_OPEN_FAILED';
                $context = ['connect_timeout_seconds' => $isRemote ? $connectTimeout : null];
            } elseif ($isRemote) {
                $response = self::finalResponse($handle);
                $context = ['status' => $response['status'], 'content_type' => $response['content_type']];
                $reason = self::rejection($response);

                if ($reason !== null) {
                    $event = 'DOWNLOAD_SOURCE_REJECTED';
                    $context['reason'] = $reason;
                } else {
                    $declaredLength = self::trustedLength($response['headers']);
                }
            } else {
                $declaredLength = self::localSize($handle);
            }
        } catch (Throwable $e) {
            $event = 'DOWNLOAD_SOURCE_OPEN_FAILED';
            $context = ['exception' => $e::class];
        }

        if ($event !== null || ! is_resource($handle)) {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            $slot->release();
            self::report($event ?? 'DOWNLOAD_SOURCE_OPEN_FAILED', $link, $context);
            $telemetry?->sourceFailed(
                $event === 'DOWNLOAD_SOURCE_REJECTED' ? 'source_rejected' : 'source_open_failed',
                isset($context['reason']) ? (string) $context['reason'] : (isset($context['exception']) ? 'exception' : 'open_failed'),
                isset($context['status']) ? (int) $context['status'] : null,
                self::sourceHost($link, $isRemote),
            );
            abort(500, 'Error reading file');
        }

        return [$handle, $declaredLength, isset($context['status']) ? (int) $context['status'] : null];
    }

    /** Telemetry outcome names; null means the callback ended without a pump result (an exception before or around it). */
    public static function telemetryOutcome(?string $outcome): string
    {
        return match ($outcome) {
            self::OUTCOME_COMPLETE => 'completed',
            self::OUTCOME_SOURCE_INCOMPLETE => 'incomplete',
            self::OUTCOME_SOURCE_STALLED => 'stalled',
            self::OUTCOME_SOURCE_READ_FAILED => 'read_failed',
            self::OUTCOME_CLIENT_ABORTED => 'client_aborted',
            default => 'interrupted',
        };
    }

    /** Host only (lowercased) — never the path or query string, which may carry tokens. `local` for a local file. */
    private static function sourceHost(string $link, bool $isRemote): string
    {
        if (! $isRemote) {
            return 'local';
        }
        $host = parse_url($link, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : 'unknown';
    }

    /**
     * The source handle, or false. Remote links get the connect timeout (see
     * the class notes: per connect and per header read, per redirect hop);
     * every other PHP default (redirects, TLS verification, user agent) is
     * unchanged. Warnings are suppressed — the caller logs one structured line
     * instead of an ErrorException with a stack trace.
     *
     * @return resource|false
     */
    private function open(string $link, bool $isRemote, float $connectTimeout): mixed
    {
        if (! $isRemote) {
            return @fopen($link, 'rb');
        }

        return @fopen($link, 'rb', false, stream_context_create(['http' => ['timeout' => $connectTimeout]]));
    }

    /**
     * The status and headers of the LAST response PHP's http wrapper received
     * (its `wrapper_data` lists every redirect hop in order).
     *
     * @param  resource  $handle
     * @return array{status: int|null, content_type: string|null, headers: array<string, list<string>>}
     */
    private static function finalResponse(mixed $handle): array
    {
        $lines = stream_get_meta_data($handle)['wrapper_data'];
        $status = null;
        $headers = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})\b#', $line, $match) === 1) {
                $status = (int) $match[1];
                $headers = [];

                continue;
            }
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $headers[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, $colon + 1));
            }
        }

        $contentType = $headers['content-type'][0] ?? null;

        return ['status' => $status, 'content_type' => $contentType === null ? null : mb_substr($contentType, 0, 100), 'headers' => $headers];
    }

    /** @param array{status: int|null, content_type: string|null, headers: array<string, list<string>>} $response */
    private static function rejection(array $response): ?string
    {
        $status = $response['status'];
        if ($status === null) {
            return 'no_http_status';
        }
        if ($status < 200 || $status > 299 || in_array($status, self::NOT_A_FILE_STATUSES, true)) {
            return 'status';
        }
        foreach ($response['headers']['content-type'] ?? [] as $type) {
            if (strtolower(trim(explode(';', $type, 2)[0])) === 'text/html') {
                return 'html';
            }
        }

        return null;
    }

    /**
     * The final response's Content-Length, only when it describes exactly the
     * bytes PHP will read: one header, a plain non-negative integer, no
     * Transfer-Encoding (it overrides Content-Length), no Content-Encoding
     * other than identity.
     *
     * @param  array<string, list<string>>  $headers
     */
    private static function trustedLength(array $headers): ?int
    {
        $lengths = $headers['content-length'] ?? [];
        if (count($lengths) !== 1 || preg_match('/^\d{1,18}$/', $lengths[0]) !== 1 || isset($headers['transfer-encoding'])) {
            return null;
        }
        foreach ($headers['content-encoding'] ?? [] as $encoding) {
            if (strtolower($encoding) !== 'identity') {
                return null;
            }
        }

        return (int) $lengths[0];
    }

    /**
     * The opened local file's own size — a regular file on the local filesystem
     * only (any other stream wrapper reports no trustworthy size).
     *
     * @param  resource  $handle
     */
    private static function localSize(mixed $handle): ?int
    {
        if (stream_get_meta_data($handle)['wrapper_type'] !== 'plainfile') {
            return null;
        }
        $stat = @fstat($handle);
        if (! is_array($stat) || ($stat['mode'] & 0170000) !== 0100000) {
            return null;
        }

        return $stat['size'] >= 0 ? $stat['size'] : null;
    }

    /** @return array{bytes_sent: int, elapsed_seconds: float, declared_bytes: int|null} */
    private static function progress(int $bytes, float $started, ?int $declaredLength): array
    {
        return ['bytes_sent' => $bytes, 'elapsed_seconds' => round(microtime(true) - $started, 1), 'declared_bytes' => $declaredLength];
    }

    /** @param array<string, mixed> $context */
    private static function report(string $event, string $link, array $context): void
    {
        try {
            Log::warning('Proxied download source problem', ['event' => $event, 'link' => self::redactLink($link)] + $context);
        } catch (Throwable) {
            // A broken log channel must not break the transfer's cleanup.
        }
    }

    /**
     * The link as it may appear in a log (Phase 3B.1): scheme, host, port and path only. A query string (signed URLs,
     * tokens) is replaced by `?[redacted]`; userinfo and fragment are dropped. Local paths are logged unchanged.
     */
    public static function redactLink(string $link): string
    {
        if (! str_starts_with($link, 'http://') && ! str_starts_with($link, 'https://')) {
            return mb_substr($link, 0, 2048);
        }
        $parts = parse_url($link);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '[unparseable remote link]';
        }

        return mb_substr(($parts['scheme'] ?? 'http').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '').(isset($parts['query']) ? '?[redacted]' : ''), 0, 2048);
    }

    /** An exception message with every http(s) URL in it redacted like redactLink() (stream errors can quote the URL). */
    public static function redactMessage(string $message): string
    {
        return mb_substr((string) preg_replace_callback('#https?://[^\s\'"<>()]+#i', fn (array $m) => self::redactLink($m[0]), $message), 0, 300);
    }

    private static function positiveSeconds(mixed $value, float $default): float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : $default;
    }
}
