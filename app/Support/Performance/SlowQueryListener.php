<?php

namespace App\Support\Performance;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enhancement Batch E-01 — DB query aggregation + slow-query logging.
 *
 * Subscribed to `QueryExecuted` (Laravel's own event, fired for every
 * connection) only when monitoring is enabled, so a disabled deployment
 * registers no listener and does no work at all.
 *
 * Two responsibilities, deliberately in one listener so a query is only
 * inspected once:
 *  1. accumulate per-request totals into `RequestMetrics` (every query,
 *     every connection);
 *  2. write a slow-SQL line for queries at/over the configured threshold.
 *
 * **Never logs bindings.** `QueryExecuted::$sql` is already the
 * parameterised template (`... where `id` = ?`) with values held
 * separately in `$bindings`, which this class never reads — so user
 * search text, emails, tokens and ids cannot reach the log through the
 * normal query path. Placeholders are preserved exactly as Laravel
 * produced them; nothing is interpolated back in.
 *
 * This listener issues no queries of its own (it only writes to a file
 * log channel), so it cannot recurse into itself.
 */
class SlowQueryListener
{
    public function __construct(
        private readonly RequestMetrics $metrics,
        private readonly int $slowQueryMs,
        private readonly int $maxSqlLength,
    ) {}

    public function handle(QueryExecuted $event): void
    {
        try {
            $this->metrics->recordQuery($event->time);

            if ($event->time < $this->slowQueryMs) {
                return;
            }

            Log::channel('slow-sql')->warning('Slow SQL query', [
                'request_id' => $this->metrics->requestId(),
                'duration_ms' => round($event->time, 2),
                'connection' => $event->connectionName,
                // Template only — see the class docblock on bindings.
                'sql' => $this->normalize($event->sql),
            ]);
        } catch (Throwable) {
            // Observability must never break the request that triggered
            // it (nor recurse by logging its own logging failure).
        }
    }

    /**
     * Collapses the whitespace Laravel's query builder emits and bounds
     * the length. Purely cosmetic/defensive — the string is already
     * value-free before it gets here.
     */
    private function normalize(string $sql): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $sql));

        return mb_strlen($normalized) > $this->maxSqlLength
            ? mb_substr($normalized, 0, $this->maxSqlLength).'…'
            : $normalized;
    }
}
