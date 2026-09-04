<?php

namespace App\Providers;

use App\Support\Performance\RequestMetrics;
use App\Support\Performance\SlowQueryListener;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Enhancement Batch E-01 — wires the performance observability pieces.
 *
 * `scoped()` rather than `singleton()`: identical behaviour under
 * classic PHP-FPM, but a scoped binding is discarded between requests by
 * Octane and between jobs by queue workers, so per-request counters can
 * never leak across a long-running worker's boundary.
 *
 * The query listener is registered only when monitoring is enabled, so a
 * disabled deployment carries no listener and does no per-query work.
 */
class PerformanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RequestMetrics::class);
    }

    public function boot(): void
    {
        if (! config('performance.enabled', false)) {
            return;
        }

        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            // Resolved lazily, per event, so the listener always talks to
            // the current request's scoped RequestMetrics instance rather
            // than capturing a stale one at boot time.
            $listener = new SlowQueryListener(
                $this->app->make(RequestMetrics::class),
                (int) config('performance.slow_query_ms', 200),
                (int) config('performance.max_sql_length', 500),
            );

            $listener->handle($event);
        });
    }
}
