<?php

/**
 * Download telemetry — Phase 1: the bounded local spool (see
 * docs/operations/download-telemetry-spool.md).
 *
 * The spool is OFF by default and nothing in the application writes to it yet:
 * Phase 1 only provides the writer, the sealer and their guarantees. Every
 * value is read from `.env` here only, so it survives `config:cache`; an empty
 * or invalid value falls back to the default (SpoolConfig validates them).
 *
 * Hard storage bound (writer-enforced, independent of any scheduler):
 *   files in active/ + sealed/ + quarantine/  <=  max_files × file_max_bytes
 * plus the fixed-size drop ledger (64 + 32 × ledger_slots bytes) and three
 * empty lock/marker files.
 */
$value = static function (string $name, mixed $default): mixed {
    $raw = env($name);

    return $raw === null || $raw === '' ? $default : $raw;
};

return [
    // off: write() returns immediately and touches nothing. Any other value
    // enables the writer (Phase 2 will distinguish `shadow` and `on`).
    'spool' => (string) $value('DOWNLOAD_TELEMETRY_SPOOL', 'off'),

    // Phase 3A request-path recorder (Khotab proxied downloads). Emits ONLY when this is `on` AND the spool is enabled.
    // Never writes to the database; a failed or dropped event never affects the download.
    'events' => strtolower((string) $value('DOWNLOAD_TELEMETRY_EVENTS', 'off')),

    'root' => (string) $value('DOWNLOAD_TELEMETRY_SPOOL_ROOT', storage_path('app/download-telemetry')),

    // Largest single spool file (one per minute). A write that would exceed it is dropped and counted.
    // 32 MiB ≈ 1,690 events/s sustained over a minute at ~331-byte events (Phase 1 performance validation).
    'file_max_bytes' => (int) $value('DOWNLOAD_TELEMETRY_SPOOL_FILE_MAX_BYTES', 32 * 1024 * 1024),

    // Most spool files across active/, sealed/ and quarantine/. A new file is refused (events dropped and counted) at the limit.
    // 32 × 32 MiB = 1 GiB hard bound.
    'max_files' => (int) $value('DOWNLOAD_TELEMETRY_SPOOL_MAX_FILES', 32),

    // A new spool file is only created while at least this much disk space is free.
    // Phase 3B.1 — spool permissions (octal strings). Spool files hold raw IP addresses, so "other" never gets access.
    // 0750/0640 assumes the web PHP process and the scheduled sealer/rollup run as the SAME user (cPanel suEXEC/CGI).
    // If they run as different users sharing a group, use 0770/0660.
    'dir_mode' => (string) $value('DOWNLOAD_TELEMETRY_SPOOL_DIR_MODE', '0750'),
    'file_mode' => (string) $value('DOWNLOAD_TELEMETRY_SPOOL_FILE_MODE', '0640'),

    'disk_reserve_bytes' => (int) $value('DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES', 2 * 1024 * 1024 * 1024),

    // The longest a writer waits for any lock (microseconds) before dropping the event.
    'lock_budget_us' => (int) $value('DOWNLOAD_TELEMETRY_WRITER_LOCK_BUDGET_US', 2000),

    // The longest an update of the drop ledger may wait (microseconds). Only drops and slow writes update it.
    'ledger_lock_budget_us' => (int) $value('DOWNLOAD_TELEMETRY_LEDGER_LOCK_BUDGET_US', 50_000),

    // How often a writer reopens after finding its file sealed under it.
    'rotation_retries' => (int) $value('DOWNLOAD_TELEMETRY_WRITER_ROTATION_RETRIES', 3),

    // Drop-ledger shards: drops are recorded in whichever shard is free first (less lock contention).
    // Fixed size on disk: shards × (64 + 40 × ledger_slots) bytes (16 × 7 days ≈ 6.2 MiB).
    'ledger_shards' => (int) $value('DOWNLOAD_TELEMETRY_DROP_LEDGER_SHARDS', 16),

    // Drop-ledger ring size in minutes (10080 = 7 days). Fixed once the ledger files exist.
    'ledger_slots' => (int) $value('DOWNLOAD_TELEMETRY_DROP_LEDGER_SLOTS', 10080),

    // A write slower than this (microseconds) is recorded as slow, and counts as a fault.
    'slow_write_us' => (int) $value('DOWNLOAD_TELEMETRY_SLOW_WRITE_US', 50_000),

    // Circuit breaker: HEALTH faults only (I/O errors, writes slower than slow_write_us) within one minute that open
    // it, and for how long. Lock contention and capacity limits never count (DropReason classification policy).
    'breaker_fault_threshold' => (int) $value('DOWNLOAD_TELEMETRY_BREAKER_FAULTS', 50),
    'breaker_open_seconds' => (int) $value('DOWNLOAD_TELEMETRY_BREAKER_OPEN_SECONDS', 300),

    // The sealer seals active files whose minute ended at least this many minutes ago.
    'seal_grace_minutes' => (int) $value('DOWNLOAD_TELEMETRY_SEAL_GRACE_MINUTES', 2),

    // The longest the sealer waits for a writer to release a file (microseconds).
    'seal_lock_budget_us' => (int) $value('DOWNLOAD_TELEMETRY_SEAL_LOCK_BUDGET_US', 2_000_000),

    // ---------------------------------------------------------------------------------------------------
    // Phase 2 — idempotent database aggregation (downloads:telemetry-rollup). OFF by default.
    // off: the rollup does nothing. dry-run: it reads and aggregates sealed batches and reports, but writes
    // nothing to the database and moves no file. on: it applies batches exactly once.
    // ---------------------------------------------------------------------------------------------------
    'aggregate' => (string) $value('DOWNLOAD_TELEMETRY_AGGREGATE', 'off'),
    'connection' => (string) $value('DOWNLOAD_TELEMETRY_DB_CONNECTION', 'main'),

    // One rollup run stops starting new batches after this many seconds / batches (it catches up on later runs).
    'rollup_time_budget_seconds' => (int) $value('DOWNLOAD_TELEMETRY_ROLLUP_TIME_BUDGET_SECONDS', 50),
    'rollup_max_batches' => (int) $value('DOWNLOAD_TELEMETRY_ROLLUP_MAX_BATCHES', 120),

    // Applied batches kept in done/ for replay and debugging: deleted after this many hours, and oldest-first
    // beyond the size cap. The batch ledger (database) outlives them, so a deleted file can never be re-applied.
    'done_retention_hours' => (int) $value('DOWNLOAD_TELEMETRY_DONE_RETENTION_HOURS', 24),
    'done_max_bytes' => (int) $value('DOWNLOAD_TELEMETRY_DONE_MAX_BYTES', 256 * 1024 * 1024),

    // Finalization: an hour (day) is evaluated this many seconds after it ends; a period still waiting for
    // unprocessed batches after `finalize_max_wait_hours` is marked incomplete; periods older than the
    // look-back window are not re-evaluated.
    'finalize_hour_grace_seconds' => (int) $value('DOWNLOAD_TELEMETRY_FINALIZE_HOUR_GRACE_SECONDS', 900),
    'finalize_day_grace_seconds' => (int) $value('DOWNLOAD_TELEMETRY_FINALIZE_DAY_GRACE_SECONDS', 7200),
    'finalize_max_wait_hours' => (int) $value('DOWNLOAD_TELEMETRY_FINALIZE_MAX_WAIT_HOURS', 24),
    'finalize_lookback_hours' => (int) $value('DOWNLOAD_TELEMETRY_FINALIZE_LOOKBACK_HOURS', 192),

    // Phase 2.2 — defence in depth: a database advisory lock (GET_LOCK) held on a dedicated connection for the whole
    // rollup, in addition to rollup.lock. Applies to MySQL/MariaDB connections (SQLite, used only in tests, has none).
    'rollup_db_lock' => filter_var($value('DOWNLOAD_TELEMETRY_ROLLUP_DB_LOCK', true), FILTER_VALIDATE_BOOLEAN),

    // Phase 2.2 — retention (days). Pruning is OFF by default and never scheduled automatically.
    'prune' => (string) $value('DOWNLOAD_TELEMETRY_PRUNE', 'off'),
    'retention_metrics_5m_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_5M_DAYS', 35),
    'retention_metrics_daily_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_DAILY_DAYS', 400),
    'retention_ip_ua_hourly_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_IP_UA_DAYS', 14),
    'retention_sketches_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_SKETCH_DAYS', 400),
    'retention_coverage_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_COVERAGE_DAYS', 400),
    // Phase 3B.1 — quarantined spool files (they hold raw IPs) are deleted after this many days, by the prune
    // command only (prune is OFF by default and not scheduled).
    'retention_quarantine_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_QUARANTINE_DAYS', 14),
    'retention_ledger_days' => (int) $value('DOWNLOAD_TELEMETRY_RETENTION_LEDGER_DAYS', 400),
    'prune_batch_rows' => (int) $value('DOWNLOAD_TELEMETRY_PRUNE_BATCH_ROWS', 5000),
    'prune_max_rows_per_table' => (int) $value('DOWNLOAD_TELEMETRY_PRUNE_MAX_ROWS_PER_TABLE', 200_000),

    // Phase 2.2 — stale-batch guard: a batch with any event older than this many days is never applied (it is
    // quarantined as `stale`). Must stay BELOW the shortest retention above, so nothing pruned can be recreated.
    'stale_batch_max_age_days' => (int) $value('DOWNLOAD_TELEMETRY_STALE_BATCH_MAX_AGE_DAYS', 13),

    // Phase 2.2 — growth monitoring of download_ip_ua_hourly (read-only; warnings only, never deletes).
    'ip_ua_warn_rows_per_hour' => (int) $value('DOWNLOAD_TELEMETRY_IP_UA_WARN_ROWS_PER_HOUR', 20_000),
    'ip_ua_critical_rows_per_hour' => (int) $value('DOWNLOAD_TELEMETRY_IP_UA_CRITICAL_ROWS_PER_HOUR', 100_000),
    'ip_ua_growth_ratio' => (int) $value('DOWNLOAD_TELEMETRY_IP_UA_GROWTH_RATIO', 5),
    'ip_ua_avg_row_bytes' => (int) $value('DOWNLOAD_TELEMETRY_IP_UA_AVG_ROW_BYTES', 1000),
    'ip_ua_projected_warn_bytes' => (int) $value('DOWNLOAD_TELEMETRY_IP_UA_PROJECTED_WARN_BYTES', 2 * 1024 * 1024 * 1024),
];
