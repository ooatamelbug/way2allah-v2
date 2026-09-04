<?php

/**
 * Enhancement Batch E-01 — Performance Observability Foundation.
 *
 * Every value here comes from `.env` so thresholds can be tuned on a
 * running deployment without a code change (and survives `config:cache`,
 * since `env()` is only ever called from this file — never from a runtime
 * class, per Laravel's own caching contract).
 *
 * Thresholds are calibrated against this application's own measured
 * behavior at audit time, not generic defaults: the framework floor is
 * ~45ms (a zero-query static page), healthy content pages land at
 * 30-120ms, and the slowest pages measured 2.4-2.9s. Once the known
 * database findings are repaired these should be tightened — see
 * `docs/operations/performance-monitoring.md`.
 */
return [
    /*
     * Master switch. When false, no listener is registered, no timing is
     * collected, and nothing is written — the application behaves exactly
     * as it did before this batch.
     */
    'enabled' => env('PERFORMANCE_MONITORING_ENABLED', true),

    /*
     * Request-duration buckets, in milliseconds. A request faster than
     * `warning_ms` is never logged at all. Ordering is enforced at use
     * (a request is classified by the highest bucket it exceeds).
     */
    'warning_ms' => (int) env('PERFORMANCE_WARNING_MS', 400),
    'slow_ms' => (int) env('PERFORMANCE_SLOW_MS', 1000),
    'critical_ms' => (int) env('PERFORMANCE_CRITICAL_MS', 2500),

    /*
     * Individual queries at or above this duration (milliseconds) are
     * logged to the slow-SQL channel. 200ms sits well above this app's
     * normal query time (~1-20ms) and well below the known offenders
     * (700-860ms), so it surfaces real problems without noise.
     */
    'slow_query_ms' => (int) env('PERFORMANCE_SLOW_QUERY_MS', 200),

    /*
     * Route URI patterns excluded from request monitoring, matched with
     * `Request::is()` (so `admincp/*` style wildcards work). Deliberately
     * empty by default: broad visibility first. A genuinely noisy,
     * always-fast endpoint (e.g. the `/up` health check) is the intended
     * use — not silencing a route because it is slow.
     */
    'excluded_routes' => [],

    /*
     * Retention for both performance log channels, in days.
     */
    'log_retention_days' => (int) env('PERFORMANCE_LOG_RETENTION_DAYS', 14),

    /*
     * Longest SQL template written to the slow-query log. Templates are
     * already binding-free; this only bounds pathological generated SQL.
     */
    'max_sql_length' => (int) env('PERFORMANCE_MAX_SQL_LENGTH', 500),
];
