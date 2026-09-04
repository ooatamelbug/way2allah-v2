<?php

namespace App\Http\Middleware;

use App\Support\Performance\PerformanceThresholds;
use App\Support\Performance\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Enhancement Batch E-01 — slow-request detection.
 *
 * Terminable: `handle()` only starts the clock (so the measured window
 * covers the whole downstream stack), and `terminate()` does the
 * classification and logging *after* the response has been sent to the
 * client — so even the logging itself costs the visitor nothing.
 *
 * Requests faster than the `warning_ms` threshold produce no log line at
 * all; the goal is a signal, not a request journal.
 *
 * Every step is wrapped so a failure here (unwritable log directory, a
 * misconfigured channel) can never turn a working page into a 500 — see
 * `terminate()`.
 */
class MonitorsRequestPerformance
{
    public function __construct(private readonly RequestMetrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->enabledFor($request)) {
            // Fresh id + zeroed counters for this request, so a reused
            // container instance (Octane, a worker, a test issuing
            // several requests) never inherits the previous one's state.
            $this->metrics->reset();

            // `LARAVEL_START` (public/index.php) is the earliest, most
            // honest start point — it includes framework bootstrap, which
            // a middleware-local timer would silently omit. Falls back to
            // "now" wherever that constant doesn't exist (tests, Octane).
            $this->metrics->start(
                defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true)
            );
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->enabledFor($request) || $this->metrics->startedAt() === null) {
                return;
            }

            $durationMs = $this->metrics->durationMs(microtime(true));

            if ($durationMs === null) {
                return;
            }

            $thresholds = PerformanceThresholds::fromConfig(
                (array) config('performance', [])
            );

            $severity = $thresholds->severityFor($durationMs);

            if ($severity === null) {
                return;
            }

            Log::channel('performance')->log(
                $thresholds->logLevelFor($severity),
                'Slow request',
                $this->context($request, $response, $durationMs, $severity)
            );
        } catch (Throwable) {
            // Never let observability break a request that already
            // succeeded, and never recurse by logging the log failure.
        }
    }

    /**
     * Only route-shaped, non-sensitive facts. Deliberately excluded:
     * cookies, authorization/session headers, the request body, uploaded
     * files, and arbitrary query parameters — any of which can carry
     * credentials or personal data. The raw URI is used only when a
     * route template genuinely isn't available (and even then it is the
     * path alone, never the query string).
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, Response $response, float $durationMs, string $severity): array
    {
        $route = $request->route();

        return [
            'request_id' => $this->metrics->requestId(),
            'severity' => $severity,
            'method' => $request->getMethod(),
            'route_name' => $route?->getName(),
            'route_uri' => $route?->uri() ?? $request->path(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query_count' => $this->metrics->queryCount(),
            'total_query_ms' => $this->metrics->totalQueryMs(),
            'slowest_query_ms' => round($this->metrics->slowestQueryMs(), 2),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    private function enabledFor(Request $request): bool
    {
        if (! config('performance.enabled', false)) {
            return false;
        }

        $excluded = (array) config('performance.excluded_routes', []);

        return $excluded === [] || ! $request->is(...$excluded);
    }
}
