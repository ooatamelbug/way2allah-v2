<?php

/**
 * The site's ONE global download-capacity pool (DownloadCapacityGuard).
 *
 * It caps how many long-lived PHP file transfers — downloads whose bytes pass
 * through PHP (ProxiedDownloadStreamer), each holding a PHP-CGI process and
 * an Apache worker for the whole transfer — may run at once, across every
 * protected endpoint combined, and refuses the rest at once with 503 instead
 * of letting them exhaust MaxRequestWorkers. Today the protected endpoints
 * are /khotab-download-* and /khotab-mirror-*. Redirect-only downloads,
 * static files and external links are not counted: they hold nothing.
 *
 * Every value comes from `.env` and is read only here, so it survives
 * `config:cache`. Each generic variable falls back to the variable the
 * earlier Khotab-only guard read (`KHOTAB_DOWNLOAD_*`), so an existing `.env`
 * keeps working unchanged. An empty value counts as unset.
 */
$setting = function (string $generic, string $legacy, mixed $default): mixed {
    foreach ([$generic, $legacy] as $name) {
        $value = env($name);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
};

return [
    // Kill switch. false = no slot is taken and no download is ever refused
    // for capacity. Off unless enabled.
    'guard' => (bool) $setting('DOWNLOAD_CAPACITY_GUARD', 'KHOTAB_DOWNLOAD_CONCURRENCY_GUARD', false),

    // Maximum simultaneous protected downloads, all endpoints COMBINED.
    'max_concurrent' => (int) $setting('DOWNLOAD_CAPACITY_MAX_CONCURRENT', 'KHOTAB_DOWNLOAD_MAX_CONCURRENT', 100),

    // Retry-After (seconds) sent with the 503 — also the number of seconds
    // the Arabic refusal message tells the visitor to wait. Nothing retries or
    // refreshes automatically: the visitor retries by hand.
    'retry_after' => (int) $setting('DOWNLOAD_CAPACITY_RETRY_AFTER', 'KHOTAB_DOWNLOAD_RETRY_AFTER', 60),

    // Write one `download_rejections` row per refused download (operational
    // telemetry, see DownloadRejectionRecorder). The structured warning in the
    // application log is written either way. A failure to store the row never
    // changes the refusal itself.
    'rejection_log' => (bool) $setting('DOWNLOAD_REJECTION_LOG', 'KHOTAB_DOWNLOAD_REJECTION_LOG', true),

    // Where the slot lock files live. Must be on the same host (flock is per
    // host) and writable by PHP. The files are reusable placeholders: a slot
    // is occupied only while a process holds its flock.
    //
    // The default deliberately keeps the earlier Khotab guard's directory
    // ("…/khotab"): downloads already running under that guard and new ones
    // under this one must lock the SAME files, or the server could briefly
    // carry both limits at once. Renaming it is a separate, controlled change.
    'slot_directory' => (string) $setting('DOWNLOAD_CAPACITY_SLOT_DIRECTORY', 'KHOTAB_DOWNLOAD_SLOT_DIRECTORY', storage_path('app/download-concurrency/khotab')),
];
