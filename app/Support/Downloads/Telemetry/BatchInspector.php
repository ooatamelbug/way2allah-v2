<?php

namespace App\Support\Downloads\Telemetry;

/**
 * Reads one spool file and classifies every line — the contract Phase 2's
 * aggregation will rely on:
 *
 *  - `valid`: a complete v1 record;
 *  - `malformed`: not JSON, wrong types, unknown fields — including an
 *    isolated torn fragment (the writer's repair turns it into its own line);
 *  - `unsupported`: a record with a schema version newer than this code
 *    (written by newer code before a rollback) — the whole batch must be
 *    quarantined, never misread and never discarded;
 *  - `trailing_fragment`: the file does not end in "\n" (a writer was killed
 *    while writing the file's last record) — that fragment is malformed;
 *  - `empty`: blank lines (harmless; can only come from a repair after an
 *    unreadable last byte).
 */
final class BatchInspector
{
    /**
     * @return array{bytes: int, sha256: string, lines: int, valid: int, malformed: int, unsupported: int, empty: int, trailing_fragment: bool, max_version: int}
     */
    public static function inspect(string $path, ?callable $onValid = null): array
    {
        $stats = ['bytes' => 0, 'sha256' => '', 'lines' => 0, 'valid' => 0, 'malformed' => 0, 'unsupported' => 0,
            'empty' => 0, 'trailing_fragment' => false, 'max_version' => 0];

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Spool batch cannot be read.');
        }

        $hash = hash_init('sha256');

        try {
            while (($line = fgets($handle)) !== false) {
                hash_update($hash, $line);
                $stats['bytes'] += strlen($line);

                if (! str_ends_with($line, "\n")) {
                    $stats['trailing_fragment'] = true;
                    $stats['lines']++;
                    $stats['malformed']++;

                    continue;
                }

                $line = substr($line, 0, -1);
                if ($line === '') {
                    $stats['empty']++;

                    continue;
                }

                $stats['lines']++;
                $decoded = TelemetryEvent::decode($line);
                $stats['max_version'] = max($stats['max_version'], (int) $decoded['version']);

                match ($decoded['status']) {
                    'ok' => $stats['valid']++,
                    'unsupported' => $stats['unsupported']++,
                    default => $stats['malformed']++,
                };

                if ($decoded['status'] === 'ok' && $onValid !== null && $decoded['event'] !== null) {
                    $onValid($decoded['event']);
                }
            }
        } finally {
            fclose($handle);
        }

        $stats['sha256'] = hash_final($hash);

        return $stats;
    }
}
