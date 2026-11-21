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

$seconds = function (string $name, float $default): float {
    $value = env($name);

    return is_numeric($value) && (float) $value > 0 ? (float) $value : $default;
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

    // Remote proxied sources (ProxiedDownloadStreamer), in seconds. Two
    // separate limits — and deliberately no total-duration limit, because
    // large files over slow links legitimately take a long time:
    //  - connect: PHP's http wrapper applies it PER OPERATION — to the TCP/TLS
    //    connect and to EACH read while waiting for the response headers,
    //    again for every redirect hop. It is not an end-to-end deadline: a
    //    slowly answering server or a chain of slow redirects can take longer
    //    in total, and DNS resolution is not covered. A silent source is
    //    bounded;
    //  - idle: the longest the source may send NO bytes mid-transfer before
    //    the transfer is ended and its slot freed (up to twice this over TLS,
    //    where PHP waits up to one timeout to fill a read buffer).
    // Empty, zero, negative or non-numeric values mean the default.
    'remote_connect_timeout' => $seconds('DOWNLOAD_REMOTE_CONNECT_TIMEOUT', 15),
    'remote_idle_timeout' => $seconds('DOWNLOAD_REMOTE_IDLE_TIMEOUT', 60),

    // Application-log volume control for refusals (DownloadRejectionLogThrottle):
    // at most one DOWNLOAD_CAPACITY_FULL warning per this many seconds, each
    // carrying how many were suppressed since the previous one. 0 = log every
    // refusal (the earlier behaviour). The `download_rejections` row above is
    // not affected.
    'rejection_log_interval' => in_array(env('DOWNLOAD_REJECTION_LOG_INTERVAL'), [null, ''], true) ? 60 : (int) env('DOWNLOAD_REJECTION_LOG_INTERVAL'),

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
