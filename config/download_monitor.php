<?php

/*
 * Phase 3B — read-only download monitoring (`php artisan downloads:monitor`). The command is NOT scheduled: it runs
 * only when an operator (or a monitoring agent the owner configures later) invokes it. Thresholds only.
 */

return [
    'thresholds' => [
        // Slots (pool limit = downloads.max_concurrent)
        'slot_occupancy_warn' => 0.8,
        'slot_occupancy_critical' => 0.95,
        'slot_held_warn_seconds' => 2 * 3600,      // no total-duration limit exists: long but steady transfers are legitimate
        'slot_held_critical_seconds' => 12 * 3600,

        // Capacity rejections in the window
        'rejections_warn' => 1,
        'rejections_critical' => 100,

        // Stream outcomes in the window (from rolled-up telemetry)
        'stream_failures_min' => 10,
        'stream_failure_ratio_warn' => 0.05,
        'stream_failure_ratio_critical' => 0.25,
        'client_disconnect_ratio_warn' => 0.6,

        // Spool and pipeline
        'spool_used_warn' => 0.5,
        'spool_used_critical' => 0.8,
        'drops_warn' => 1,
        'drops_critical' => 1000,
        'pending_batches_warn' => 5,
        'pending_batches_critical' => 20,
        'pending_age_warn_seconds' => 600,
        'pending_age_critical_seconds' => 3600,
        'rollup_lag_warn_seconds' => 900,
        'rollup_lag_critical_seconds' => 3600,
    ],
];
