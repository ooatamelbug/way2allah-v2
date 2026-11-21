<?php

namespace App\Support\Downloads\Telemetry;

use App\Support\Performance\RequestMetrics;
use Illuminate\Http\Request;
use Throwable;

/**
 * The request-path telemetry recorder (Phase 3A). Turns download lifecycle moments into versioned events in the
 * bounded local spool — nothing else:
 *
 *  - OFF unless BOTH `DOWNLOAD_TELEMETRY_EVENTS=on` and the spool itself is enabled;
 *  - never touches the database, never throws, never blocks beyond the spool writer's lock budget; the spool's
 *    capacity limits, breaker and drop ledger apply unchanged;
 *  - bounded, minimal payload: method, HEAD and Range flags, the IP address Laravel resolved, whether an
 *    X-Forwarded-For header was present (not its value), a KEYED hash of the User-Agent (HMAC with the app key; the raw
 *    User-Agent is not stored) and a coarse User-Agent family, the request id and a random event id. Never cookies,
 *    authorization headers, tokens, query strings or bodies.
 */
final class DownloadTelemetry
{
    private ?SpoolWriter $writer = null;

    private readonly bool $enabled;

    public function __construct(?SpoolWriter $writer = null, ?bool $enabled = null)
    {
        $this->enabled = $enabled ?? (strtolower(trim((string) config('download_telemetry.events', 'off'))) === 'on'
            && SpoolConfig::fromLaravel()->enabled);
        $this->writer = $writer;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** A tracker for one download (exactly one terminal outcome). */
    public function transfer(Request $request, DownloadTelemetryContext $context): TransferTelemetry
    {
        return new TransferTelemetry($this, $request, $context);
    }

    /** @param array<string, bool|int|float|string|null> $fields */
    public function emit(Request $request, string $event, DownloadTelemetryContext $context, array $fields = []): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $userAgent = (string) $request->userAgent();
            $payload = array_filter([
                't' => microtime(true),
                'ev' => $event,
                'id' => bin2hex(random_bytes(8)),
                'rq' => self::requestId(),
                'mod' => $context->module,
                'rt' => $context->resourceType,
                'rid' => $context->resourceId,
                'var' => $context->variant,
                'vid' => $context->variantId,
                'm' => $request->getMethod(),
                'head' => $request->isMethod('HEAD'),
                'rng' => $request->headers->has('Range'),
                'ip' => (string) $request->ip(),
                'xff' => $request->headers->has('X-Forwarded-For'),
                'uah' => $userAgent === '' ? '' : substr(hash_hmac('sha256', $userAgent, (string) config('app.key')), 0, 32),
                'uaf' => self::userAgentFamily($userAgent),
            ] + $fields, fn ($v) => $v !== null && $v !== '');

            ($this->writer ??= SpoolWriter::fromLaravel())->write($payload);
        } catch (Throwable) {
            // Telemetry must never affect a download.
        }
    }

    /** Coarse family only: bot, tool (download managers, scripts), browser, other or none. */
    public static function userAgentFamily(string $userAgent): string
    {
        return match (true) {
            $userAgent === '' => 'none',
            preg_match('/bot|crawl|spider|slurp|semrush|ahrefs|dataforseo|mj12|bingpreview|facebookexternalhit|petal/i', $userAgent) === 1 => 'bot',
            preg_match('/idm|internet download manager|wget|curl|aria2|axel|go-http|python|okhttp|java\/|libwww|httpclient/i', $userAgent) === 1 => 'tool',
            preg_match('/mozilla|opera/i', $userAgent) === 1 => 'browser',
            default => 'other',
        };
    }

    private static function requestId(): ?string
    {
        try {
            return app(RequestMetrics::class)->requestId();
        } catch (Throwable) {
            return null;
        }
    }
}
