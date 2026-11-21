<?php

namespace App\Support\Downloads\Archive;

use Carbon\CarbonImmutable;
use Generator;
use JsonException;

/**
 * The archive format (Phase 3D; day segments since 3D.1):
 *
 * One archive RUN produces one SEGMENT per UTC calendar day of the records' `created_at` — `{run_id}-{YYYYmmdd}`. All
 * records of a segment share ONE retention deadline (ArchiveFiles::deadline), so a whole segment can be expired at
 * exactly that moment without rewriting any file and without keeping or dropping any record early or late.
 *
 *   archives/{segment_id}.ndjson.gz       gzip of NDJSON — one record per line, ordered by (`created_at`, `id`); every
 *                                         column of the row at archive time, in the table's column order; every
 *                                         non-null value as a JSON string (a value that is not valid UTF-8 — e.g. a
 *                                         raw User-Agent — as {"$b64": "<base64>"}), so the bytes round-trip exactly
 *   archives/{segment_id}.manifest.json   format + version, archive_id (the segment), run_id, day, table, columns,
 *                                         rows, id and created_at ranges, cutoff, high_id, SHA-256 of the uncompressed
 *                                         NDJSON (content) and of the .gz file, file size, created_at, expires_at
 *
 * Publication is atomic: data and manifest are written and fsync'ed under tmp/, verified, then renamed into
 * archives/ — data first, manifest last. A manifest in archives/ therefore means "published and verified".
 */
final class ArchiveFiles
{
    public const FORMAT = 'w2a-download-rejections-archive';

    public const VERSION = 2;

    /** A segment id: run id + the records' UTC day. */
    public const SEGMENT_PATTERN = '/^(rej-[0-9TZ]+-[0-9a-f]{8})-(\d{8})$/';

    /** @param array<string, mixed> $row  @param list<string> $columns */
    public static function encode(array $row, array $columns): string
    {
        $out = [];
        foreach ($columns as $column) {
            $v = $row[$column] ?? null;
            if ($v === null) {
                $out[$column] = null;

                continue;
            }
            $v = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
            $out[$column] = mb_check_encoding($v, 'UTF-8') ? $v : ['$b64' => base64_encode($v)];
        }

        return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string|null> */
    public static function decode(string $line): array
    {
        $row = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($row)) {
            throw new ArchiveAborted('archive record is not an object');
        }
        foreach ($row as $k => $v) {
            if (is_array($v)) {
                $row[$k] = base64_decode((string) ($v['$b64'] ?? ''), true);
                if ($row[$k] === false) {
                    throw new ArchiveAborted('archive record has an invalid encoded value');
                }
            }
        }

        return $row;
    }

    /**
     * Stream the lines of a .gz archive (each without its newline), with a running count and content hash.
     *
     * @return Generator<int, string>
     */
    public static function lines(string $path): Generator
    {
        $gz = @gzopen($path, 'rb');
        if ($gz === false) {
            throw new ArchiveAborted('archive file cannot be opened');
        }
        try {
            $buffer = '';
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 1 << 16);
                if ($chunk === false) {
                    throw new ArchiveAborted('archive file cannot be decompressed');
                }
                $buffer .= $chunk;
                while (($nl = strpos($buffer, "\n")) !== false) {
                    yield substr($buffer, 0, $nl);
                    $buffer = substr($buffer, $nl + 1);
                }
            }
            if ($buffer !== '') {
                throw new ArchiveAborted('archive file ends with an incomplete record');
            }
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Integrity of an archive file on its own: decompresses fully; checks record count and content SHA-256 (and the
     * file SHA-256 when given) against the expectation.
     *
     * @return list<string> problems (empty = intact)
     */
    public static function check(string $path, int $rows, ?string $contentSha, ?string $fileSha): array
    {
        if (! is_file($path)) {
            return ['archive file missing'];
        }
        $problems = [];
        if ($fileSha !== null && hash_file('sha256', $path) !== $fileSha) {
            $problems[] = 'file checksum mismatch';
        }
        try {
            $hash = hash_init('sha256');
            $count = 0;
            foreach (self::lines($path) as $line) {
                hash_update($hash, $line."\n");
                self::decode($line);
                $count++;
            }
            if ($count !== $rows) {
                $problems[] = "record count {$count} ≠ {$rows}";
            }
            if ($contentSha !== null && hash_final($hash) !== $contentSha) {
                $problems[] = 'content checksum mismatch';
            }
        } catch (\Throwable $e) {
            $problems[] = 'unreadable: '.($e instanceof ArchiveAborted ? $e->getMessage() : $e::class);
        }

        return $problems;
    }

    /** @return array<string, mixed>|null */
    public static function manifest(ArchiveConfig $config, string $archiveId): ?array
    {
        $path = $config->archivesDir().'/'.$archiveId.'.manifest.json';
        try {
            $m = json_decode((string) @file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($m) && ($m['format'] ?? null) === self::FORMAT ? $m : null;
    }

    /** Published segment ids (those with a manifest), optionally of one run, sorted. @return list<string> */
    public static function published(ArchiveConfig $config, ?string $runId = null): array
    {
        $ids = [];
        foreach (@scandir($config->archivesDir()) ?: [] as $name) {
            if (preg_match('/^(rej-[0-9TZ]+-[0-9a-f]{8}-\d{8})\.manifest\.json$/', $name, $m) === 1
                && ($runId === null || str_starts_with($m[1], $runId.'-'))) {
                $ids[] = $m[1];
            }
        }
        sort($ids);

        return $ids;
    }

    /**
     * The run's digest: SHA-256 over its published segment manifests (id + manifest-file SHA-256, in order). A backup
     * confirmation names this value, so it authorizes exactly these files and nothing else.
     *
     * @param  list<string>  $segmentIds
     */
    public static function digest(ArchiveConfig $config, array $segmentIds): ?string
    {
        if ($segmentIds === []) {
            return null;
        }
        $parts = [];
        foreach ($segmentIds as $id) {
            $path = $config->archivesDir()."/{$id}.manifest.json";
            if (! is_file($path)) {
                return null;
            }
            $parts[] = $id.':'.hash_file('sha256', $path);
        }

        return hash('sha256', implode("\n", $parts));
    }

    public static function dataPath(ArchiveConfig $config, string $archiveId): string
    {
        return $config->archivesDir().'/'.$archiveId.'.ndjson.gz';
    }

    /**
     * A record's retention deadline (Phase 3D.1): 00:00 UTC on (its UTC creation DATE + N calendar months), the month
     * clamped to its last day (Oct 31 + 4 → Feb 28, or Feb 29 in a leap year; Jan 31 + 4 → May 31). It depends only on
     * the creation date, so every record of a day segment has the same deadline. A record is therefore kept AT MOST N
     * calendar months (less by the time of day it was created, and by any month-end clamp) — never longer.
     */
    public static function deadline(int $createdAt, int $months): int
    {
        return CarbonImmutable::createFromTimestampUTC($createdAt)->startOfDay()->addMonthsNoOverflow($months)->getTimestamp();
    }

    public static function ensureDir(ArchiveConfig $config, string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, $config->dirMode, true) && ! is_dir($dir)) {
            throw new ArchiveAborted('archive directory cannot be created');
        }
        @chmod($dir, $config->dirMode);
        if (! is_writable($dir)) {
            throw new ArchiveAborted('archive directory is not writable');
        }
    }

    /** Write a small file atomically: temp + fsync + rename, mode applied. */
    public static function atomicWrite(ArchiveConfig $config, string $path, string $contents): void
    {
        self::ensureDir($config, dirname($path));
        $tmp = $path.'.tmp-'.bin2hex(random_bytes(4));
        $h = @fopen($tmp, 'xb');
        if ($h === false) {
            throw new ArchiveAborted('archive state cannot be written');
        }
        $ok = fwrite($h, $contents) === strlen($contents) && fflush($h) && fsync($h);
        fclose($h);
        @chmod($tmp, $config->fileMode);
        if (! $ok || ! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new ArchiveAborted('archive state cannot be written');
        }
        self::syncDir(dirname($path));
    }

    public static function fsyncFile(string $path): bool
    {
        $h = @fopen($path, 'r+b');
        if ($h === false) {
            return false;
        }
        $ok = fsync($h);
        fclose($h);

        return $ok;
    }

    public static function syncDir(string $dir): void
    {
        $h = @fopen($dir, 'r');
        if ($h !== false) {
            @fsync($h);
            fclose($h);
        }
    }

    /** One JSON line per destructive or operator action (expiry, abandon, backup confirmation, restore). */
    public static function audit(ArchiveConfig $config, array $entry): void
    {
        self::ensureDir($config, $config->directory);
        $line = json_encode(['at' => gmdate('c')] + $entry, JSON_UNESCAPED_SLASHES)."\n";
        $h = @fopen($config->auditLogPath(), 'ab');
        if ($h === false || fwrite($h, $line) !== strlen($line) || ! fflush($h) || ! fsync($h)) {
            if ($h !== false) {
                fclose($h);
            }
            throw new ArchiveAborted('archive audit log cannot be written');
        }
        fclose($h);
        @chmod($config->auditLogPath(), $config->fileMode);
    }
}
