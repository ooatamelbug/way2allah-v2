<?php

/*
 * Phase 3D — archiving of `download_rejections` (owner-approved retention, 2026-11-11). The archive is DISABLED by
 * default and nothing schedules it: every command below exits without touching anything while `enabled` is off.
 *
 *   active retention  ≤ 20 days   rows normally never older than this in the live table
 *   frequency         7 days      a run is due 7 days after the last completed one
 *   cutoff            13 days     rows created before (run time − 13 days) are archived; 13 + 7 = 20
 *                                 (a run is due 1 hour before the 7 days elapse, so daily scheduling never slips a day)
 *   archive retention 4 calendar months, counted from each record's own created_at (the archive's newest record)
 *
 * A run that is delayed or fails lets the live table exceed 20 days until the next successful run (monitored).
 */
$value = static function (string $name, mixed $default): mixed {
    $raw = env($name);

    return $raw === null || $raw === '' ? $default : $raw;
};

return [
    'archive' => [
        'enabled' => filter_var($value('DOWNLOAD_REJECTIONS_ARCHIVE', false), FILTER_VALIDATE_BOOL),

        // Outside the public web root; created 0750, files 0640.
        'directory' => (string) $value('DOWNLOAD_REJECTIONS_ARCHIVE_DIRECTORY', storage_path('app/download-rejections-archive')),

        'frequency_days' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_FREQUENCY_DAYS', 7),
        'active_retention_days' => (int) $value('DOWNLOAD_REJECTIONS_ACTIVE_RETENTION_DAYS', 20),
        'cutoff_days' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_CUTOFF_DAYS', 13),
        'retention_months' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_RETENTION_MONTHS', 4),

        'chunk_rows' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_CHUNK_ROWS', 5000),
        'max_rows_per_run' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_MAX_ROWS_PER_RUN', 2_000_000),
        'delete_pause_ms' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_DELETE_PAUSE_MS', 20),
        'min_free_bytes' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_MIN_FREE_BYTES', 512 * 1024 * 1024),
        'expire_max_archives_per_run' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_EXPIRE_MAX', 100),

        // Phase 3D.1: ON by default. A verified archive run is NOT deleted from the live table until an operator confirms
        // that THIS run's files were backed up, naming the run AND its manifest digest:
        //   downloads:rejections-archive --confirm-backup=<run_id> --digest=<sha256> [--reference="<backup id>"]
        // The digest is re-checked against the manifests on disk before any deletion. Turn off only with owner approval.
        'require_backup_confirmation' => filter_var($value('DOWNLOAD_REJECTIONS_ARCHIVE_REQUIRE_BACKUP', true), FILTER_VALIDATE_BOOL),

        'dir_mode' => (string) $value('DOWNLOAD_REJECTIONS_ARCHIVE_DIR_MODE', '0750'),
        'file_mode' => (string) $value('DOWNLOAD_REJECTIONS_ARCHIVE_FILE_MODE', '0640'),

        // Monitoring (downloads:monitor → rejections_archive).
        'warn_after_days' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_WARN_AFTER_DAYS', 8),
        'critical_after_days' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_CRITICAL_AFTER_DAYS', 14),
        'stale_run_hours' => (int) $value('DOWNLOAD_REJECTIONS_ARCHIVE_STALE_RUN_HOURS', 24),
    ],
];
