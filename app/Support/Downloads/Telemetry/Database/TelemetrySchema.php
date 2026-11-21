<?php

namespace App\Support\Downloads\Telemetry\Database;

use Closure;
use Illuminate\Database\Schema\Blueprint;

/**
 * Phase 2 telemetry tables — defined ONCE, here, and used by both the migration
 * (`2026_11_11_100000_create_download_telemetry_tables`) and the tests, so the
 * two can never drift.
 *
 * All additive, all on the telemetry connection (`main`), no foreign keys,
 * unix-time integers, no change to any existing or legacy table.
 *
 * Definitions used throughout (owner correction B):
 *  - "IP address": one value of the `ip` field as Laravel resolved it;
 *  - "IP/User-Agent combination": one (`ip`, User-Agent hash) pair — NOT a person
 *    and NOT a "unique user"; one person may appear as several, and many people
 *    behind one NAT as one.
 */
final class TelemetrySchema
{
    public const BATCHES = 'download_telemetry_batches';

    public const METRICS_5M = 'download_metrics_5m';

    public const METRICS_DAILY = 'download_metrics_daily';

    public const IP_UA_HOURLY = 'download_ip_ua_hourly';

    public const SKETCHES = 'download_distinct_sketches';

    public const PERIODS = 'download_period_status';

    public const COVERAGE = 'download_telemetry_coverage';

    /** @return array<string, Closure(Blueprint): void> table => definition, in creation order */
    public static function tables(): array
    {
        return [
            self::BATCHES => self::batches(),
            self::METRICS_5M => self::metrics('bucket'),
            self::METRICS_DAILY => self::metrics('day'),
            self::IP_UA_HOURLY => self::ipUaHourly(),
            self::SKETCHES => self::sketches(),
            self::PERIODS => self::periods(),
            self::COVERAGE => self::coverage(),
        ];
    }

    /** Exactly-once ledger: one row per applied batch, written in the same transaction as its aggregates. */
    private static function batches(): Closure
    {
        return function (Blueprint $table) {
            $table->string('batch_id', 64)->primary();
            $table->char('sha256', 64)->unique('uq_dtb_sha256');
            $table->unsignedSmallInteger('schema_version');
            $table->unsignedInteger('batch_minute');
            $table->unsignedInteger('lines');
            $table->unsignedInteger('events');
            $table->unsignedInteger('malformed');
            $table->unsignedInteger('min_t')->nullable();
            $table->unsignedInteger('max_t')->nullable();
            $table->unsignedInteger('late_periods');
            $table->unsignedInteger('applied_at');
            $table->index('applied_at', 'idx_dtb_applied');
        };
    }

    /**
     * Additive counters per time bucket and dimension. Exact for the events that were captured.
     * `bucket` = 5-minute bucket start (unix); `day` = UTC day start (unix).
     */
    private static function metrics(string $timeColumn): Closure
    {
        return function (Blueprint $table) use ($timeColumn) {
            $table->unsignedInteger($timeColumn);
            $table->string('module', 32);
            $table->string('event', 24);
            $table->string('outcome', 32);
            $table->string('variant', 32);
            $table->string('source_host', 128);
            $table->unsignedBigInteger('events');
            $table->unsignedBigInteger('bytes_sum');
            $table->unsignedBigInteger('duration_ms_sum');
            $table->unsignedInteger('duration_ms_max');
            $table->unsignedBigInteger('ttfb_ms_sum');
            $table->unsignedInteger('ttfb_ms_max');
            $table->unsignedBigInteger('head_count');
            $table->unsignedBigInteger('range_count');
            $table->unsignedBigInteger('d_lt_10s');
            $table->unsignedBigInteger('d_lt_1m');
            $table->unsignedBigInteger('d_lt_10m');
            $table->unsignedBigInteger('d_lt_1h');
            $table->unsignedBigInteger('d_ge_1h');
            $table->primary([$timeColumn, 'module', 'event', 'outcome', 'variant', 'source_host'], 'pk_'.($timeColumn === 'bucket' ? 'dm5' : 'dmd'));
        };
    }

    /**
     * One row per (hour, IP address, User-Agent hash) = one IP/User-Agent COMBINATION in that hour.
     * Exact distinct IP addresses in an hour = COUNT(DISTINCT ip); exact combinations = COUNT(*).
     * `resources`: up to 64 distinct resource keys (exact below that; `resources_overflow` = 1 means "≥ 64").
     */
    private static function ipUaHourly(): Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('hour');
            $table->string('ip', 45);
            $table->string('ua_hash', 32);
            $table->string('ua_family', 32);
            $table->unsignedInteger('events');
            $table->unsignedInteger('capacity_rejected');
            $table->unsignedInteger('transfers_started');
            $table->unsignedInteger('transfers_finished');
            $table->unsignedInteger('redirects');
            $table->unsignedInteger('head_requests');
            $table->unsignedInteger('range_requests');
            $table->text('resources');
            $table->boolean('resources_overflow');
            $table->unsignedInteger('first_t');
            $table->unsignedInteger('last_t');
            $table->primary(['hour', 'ip', 'ua_hash'], 'pk_diuh');
            $table->index(['ip', 'hour'], 'idx_diuh_ip');
        };
    }

    /** HyperLogLog registers (p = 12, 4096 bytes) per UTC day and dimension — APPROXIMATE distinct counts (± ~1.6 %). */
    private static function sketches(): Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('day');
            $table->string('dimension', 64);
            $table->binary('registers');
            $table->unsignedInteger('updated_at');
            $table->primary(['day', 'dimension'], 'pk_dds');
        };
    }

    /**
     * Data quality per period (granularity `hour` | `day`): provisional, final, incomplete or corrected — see
     * PeriodFinalizer for exactly when each is assigned.
     */
    private static function periods(): Closure
    {
        return function (Blueprint $table) {
            $table->string('granularity', 8);
            $table->unsignedInteger('period_start');
            $table->string('state', 16);
            $table->unsignedBigInteger('events');
            $table->unsignedInteger('batches');
            $table->unsignedInteger('malformed');
            $table->unsignedInteger('late_batches');
            $table->unsignedInteger('coverage_minutes');
            $table->unsignedBigInteger('dropped_events');
            $table->text('reasons');
            $table->unsignedInteger('finalized_at')->nullable();
            $table->unsignedInteger('corrected_at')->nullable();
            $table->unsignedInteger('updated_at');
            $table->primary(['granularity', 'period_start'], 'pk_dps');
            $table->index(['state', 'granularity', 'period_start'], 'idx_dps_state');
        };
    }

    /**
     * Evidence of coverage: one row per minute in which the rollup observed the spool's state. A minute COUNTS as
     * covered only when the spool was enabled, the writers' own path was writable (a probe in active/ and every
     * existing drop-ledger shard), the breaker was closed, and the spool was below its file and disk limits. The
     * scheduler merely running proves nothing; a period can only be final when every one of its minutes is covered.
     */
    private static function coverage(): Closure
    {
        return function (Blueprint $table) {
            $table->unsignedInteger('minute')->primary();
            $table->boolean('spool_enabled');
            $table->boolean('spool_writable');
            $table->boolean('breaker_open');
            $table->boolean('spool_full');
            $table->unsignedInteger('recorded_at');
        };
    }
}
