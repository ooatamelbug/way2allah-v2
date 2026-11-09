<?php

namespace App\Support\Downloads;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a file's bytes THROUGH PHP — the one long-lived transfer the global
 * download-capacity pool exists for. Moved unchanged from
 * `KhotabItemController::streamFile()` so every endpoint that proxies a file
 * goes through one place, and none can stream without a slot.
 *
 * INVARIANT: any endpoint that proxies a large or long-lived file through PHP
 * must take a slot from DownloadCapacityGuard and stream through this class,
 * and so joins the same global pool. A redirect-only endpoint must not.
 *
 * Slot lifetime: the caller acquired `$slot` before counting anything. The
 * StreamedResponse callback captures it, so it stays held for the whole
 * transfer — Symfony runs the callback in `send()`, after the controller and
 * the middleware stack have returned — and releases it in `finally` when the
 * transfer ends, even on error. If the client disconnects, PHP aborts on its
 * next write and request shutdown closes the lock's descriptor; if the
 * process dies, the OS does. A request that 404s before streaming releases
 * the slot here too.
 *
 * ------------------------------------------------------------------
 * G-01 fix (Migration Gap Register)
 * ------------------------------------------------------------------
 * Legacy `download_khotab()` (`khotab/functions.php:993-1032`) never checks
 * the link exists before opening it — it calls `@fopen($_link, 'rb')` for any
 * non-empty link, local or remote. So `is_file()` is skipped only for
 * `http://`/`https://` links; every other non-empty link (local paths,
 * garbled/placeholder strings) must still be a real file, or it 404s.
 *
 * ------------------------------------------------------------------
 * MariaDB connection-exhaustion fix (production incident, 2026-09-27)
 * ------------------------------------------------------------------
 * The loop advances at the client's download speed — a 1.26 GiB archive.org
 * mirror measured 20-60+ minutes. Laravel keeps the request's PDO handle open
 * for the whole request, so every in-flight download pinned one idle MariaDB
 * connection (287 downloads against `max_connections = 300`). So the
 * connection is released **before** the loop starts: the record is already
 * loaded, the counter already incremented, and `$link` is a plain string
 * captured by the closure. Nothing inside the callback touches the database,
 * so nothing reconnects. It is done inside the callback because that is
 * provably the last moment before `fopen()`. `$connectionToRelease` comes from
 * the caller's model (`Model::getConnectionName()`), never hardcoded.
 *
 * Releasing the database does NOT free the Apache worker or the PHP process:
 * the whole file still passes through PHP — which is why the slot is held.
 */
final class ProxiedDownloadStreamer
{
    public function stream(DownloadSlot $slot, ?string $link, ?string $connectionToRelease = null): StreamedResponse
    {
        $isRemote = $link !== null && (str_starts_with($link, 'http://') || str_starts_with($link, 'https://'));

        if ($link === null || $link === '' || (! $isRemote && ! is_file($link))) {
            $slot->release();
            abort(404, 'File not found');
        }

        return response()->streamDownload(function () use ($link, $connectionToRelease, $slot) {
            try {
                // Release the database BEFORE the long transfer. Everything
                // needed below is already a plain local.
                if ($connectionToRelease !== null) {
                    DB::disconnect($connectionToRelease);
                }

                $handle = fopen($link, 'rb');
                abort_if($handle === false, 500, 'Error reading file');

                while (! feof($handle)) {
                    echo fread($handle, 8192);
                }

                fclose($handle);
            } finally {
                $slot->release();
            }
        }, basename($link));
    }
}
