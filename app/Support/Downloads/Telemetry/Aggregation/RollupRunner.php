<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One scheduled rollup run (Phase 2). All database work of the telemetry pipeline happens here — never on a request.
 *
 *   0. Exclusive, NON-blocking `rollup.lock`: a second concurrent worker exits at once. Then (MySQL/MariaDB) the
 *      database advisory lock on a dedicated connection (DatabaseRollupLock), held for the whole run — not acquired:
 *      the run does nothing; ownership lost: it rolls back the current batch and writes nothing further.
 *   1. Coverage evidence: record that, in this minute, the spool was enabled / writable / breaker state.
 *   2. Seal finished minutes (Phase 1 sealer).
 *   3. Apply sealed batches oldest-first, within the time and batch budget. A batch that fails (its transaction
 *      rolls back) ends this run's applying, but finalization and retention still run. Per batch:
 *        sealed/{id}.ndjson ──inspect──▶ unsupported version → quarantine/   (never applied, never deleted)
 *                           ──apply (ONE transaction: ledger + aggregates)──▶ commit ──▶ rename to done/{id}.ndjson
 *        already applied (same id + same SHA-256) or same content under another id → done/ (nothing applied again)
 *        same id, DIFFERENT content → quarantine/ (a conflict is never applied)
 *      A crash before COMMIT leaves the file in sealed/ with no trace in the database; after COMMIT, the ledger row
 *      makes the next run move it without re-applying.
 *   4. Finalize periods (PeriodFinalizer) and acknowledge the drop ledger.
 *   5. done/ retention: delete files older than `done_retention_hours`, then oldest-first beyond `done_max_bytes`.
 *
 * Dry-run mode does 1–3 in memory only: no database write, no file moved, no acknowledgement.
 */
final class RollupRunner
{
    /**
     * @param  (Closure(string, array<string, mixed>): void)|null  $hook  crash-injection points (tests only)
     * @param  bool  $exclusive  false skips rollup.lock — tests only, to prove the ledger alone prevents double application
     */
    public function __construct(
        private readonly SpoolConfig $spool,
        private readonly RollupConfig $config,
        private readonly ?Closure $hook = null,
        private readonly ?Closure $clock = null,
        private readonly bool $exclusive = true,
    ) {}

    public static function fromLaravel(?Closure $hook = null): self
    {
        return new self(SpoolConfig::fromLaravel(), RollupConfig::fromLaravel(), $hook);
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $report = ['mode' => $this->config->mode, 'ran' => false, 'busy' => false, 'sealed' => 0, 'applied' => 0, 'already_applied' => 0,
            'duplicate_content' => 0, 'conflicts' => 0, 'quarantined' => 0, 'late_periods' => 0, 'events' => 0, 'deferred' => 0,
            'errors' => [], 'finalizer' => null, 'done_deleted' => 0, 'dry_run' => [], 'stale' => 0, 'db_lock' => null];

        if ($this->config->mode === RollupConfig::OFF) {
            return $report;
        }
        foreach ([$this->spool->root, $this->spool->activeDir(), $this->spool->sealedDir(), $this->spool->doneDir(), $this->spool->quarantineDir()] as $dir) {
            if (! is_dir($dir) && ! @mkdir($dir, $this->spool->dirMode, true) && ! is_dir($dir)) {
                $report['errors'][] = 'cannot create '.$dir;

                return $report;
            }
        }

        $lock = @fopen($this->spool->rollupLockPath(), 'c');
        if ($lock === false) {
            $report['errors'][] = 'cannot open rollup.lock';

            return $report;
        }
        if ($this->exclusive && ! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $report['busy'] = true;

            return $report;
        }

        $dbLock = null;

        try {
            $dryRun = $this->config->mode === RollupConfig::DRY_RUN;
            $db = DB::connection($this->config->connection);
            $started = microtime(true);

            // Defence in depth (Phase 2.2): the database advisory lock, on a dedicated connection, for the whole run.
            if ($dryRun) {
                $report['db_lock'] = 'not_needed';
            } elseif (! $this->config->dbLock) {
                $report['db_lock'] = 'disabled';
            } elseif (! DatabaseRollupLock::supported($db)) {
                $report['db_lock'] = 'not_supported';   // SQLite (tests): its own database-level write lock serializes
            } else {
                $dbLock = new DatabaseRollupLock($this->config->connection, $this->hook);
                if (! $dbLock->acquire()) {
                    $report['db_lock'] = 'busy';
                    $report['busy'] = true;
                    $dbLock = null;

                    return $report;   // never proceed without it
                }
                $report['db_lock'] = 'held';
            }
            $owned = fn (): bool => $dbLock === null || $dbLock->owned();
            $report['ran'] = true;

            if (! $dryRun) {
                $this->recordCoverage($db);
            }

            $seal = (new SpoolSealer($this->spool, $this->clock))->seal();
            $report['sealed'] = count($seal['sealed']);
            $report['quarantined'] += count($seal['quarantined']);   // unsupported versions found while sealing

            $batches = glob($this->spool->sealedDir().'/*.ndjson') ?: [];
            sort($batches);   // batch ids start with the minute: oldest first
            $applier = new BatchApplier($db, $this->hook, $owned);
            $lockLost = false;

            foreach ($batches as $i => $path) {
                if ($i >= $this->config->maxBatches || microtime(true) - $started > $this->config->timeBudgetSeconds) {
                    $report['deferred'] = count($batches) - $i;

                    break;
                }
                if (! $owned()) {
                    $report['errors'][] = 'database advisory lock ownership lost: stopped before applying further batches';
                    $report['deferred'] = count($batches) - $i;
                    $lockLost = true;

                    break;
                }
                try {
                    $this->processBatch($path, $applier, $dryRun, $report);
                } catch (Throwable $e) {
                    // This batch stays in sealed/ (its transaction rolled back) and is retried next run. Stop applying
                    // further batches this run, but still finalize periods and enforce retention below.
                    $report['errors'][] = basename($path, '.ndjson').': '.self::describe($e);
                    $report['deferred'] = count($batches) - $i;
                    $lockLost = $dbLock !== null && ! $dbLock->owned();

                    break;
                }
            }

            if (! $dryRun && ($lockLost || ! $owned())) {
                // Ownership is uncertain: do not write anything else (no finalization, no acknowledgement).
                if (! $lockLost) {
                    $report['errors'][] = 'database advisory lock ownership lost: finalization skipped';
                }
            } elseif (! $dryRun) {
                (new DropLedger($this->spool))->stampBoot(intdiv($this->now(), 60));
                $this->recordCoverage($db);   // the run may have crossed a minute boundary
                $report['finalizer'] = (new PeriodFinalizer($db, $this->spool, $this->config))->run($this->now());
                $report['done_deleted'] = $this->enforceDoneRetention();
            }
        } catch (Throwable $e) {
            $report['errors'][] = self::describe($e);
        } finally {
            $dbLock?->release();
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $report;
    }

    /**
     * An error as it may appear in the report, command output, cron mail or logs (Phase 3C privacy review): the class
     * and code only — plus the SQLSTATE for database errors. Never the message: a QueryException message embeds the
     * SQL WITH its bound values (IP addresses, User-Agent hashes), and a driver message can quote a duplicate key.
     */
    public static function describe(Throwable $e): string
    {
        if ($e instanceof RollupAborted) {
            return $e::class.': '.$e->getMessage();   // our own fixed text, no event data
        }
        // QueryException extends PDOException.
        $sqlState = $e instanceof \PDOException
            ? ' SQLSTATE '.(is_array($e->errorInfo) && isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : (string) $e->getCode())
            : '';

        return $e::class.' (code '.(string) $e->getCode().')'.$sqlState;
    }

    /** @param array<string, mixed> $report */
    private function processBatch(string $path, BatchApplier $applier, bool $dryRun, array &$report): void
    {
        $batchId = basename($path, '.ndjson');
        $minute = SpoolConfig::minuteFromKey(substr($batchId, 0, 12)) ?? intdiv($this->now(), 60);

        try {
            $aggregate = BatchAggregate::fromFile($path, $minute);
        } catch (Throwable $e) {
            $report['errors'][] = "unreadable batch {$batchId}";

            return;   // stays in sealed/, retried next run
        }

        if ($aggregate->stats['unsupported'] > 0) {
            if (! $dryRun && @rename($path, $this->spool->quarantineDir().'/'.$batchId.'.ndjson')) {
                $report['quarantined']++;
            }

            return;
        }

        // Stale-batch guard (Phase 2.2): a batch with any event older than the safe replay boundary is never applied —
        // its ledger row or its aggregate rows may already have been pruned, so applying it could double-count or
        // partially recreate expired rows. It is kept in quarantine/ as `.stale` for inspection.
        $boundary = $this->config->staleBoundary($this->now());
        if (($aggregate->minT !== null && $aggregate->minT < $boundary) || $minute * 60 < $boundary) {
            if (! $dryRun && @rename($path, $this->spool->quarantineDir().'/'.$batchId.'.stale.ndjson')) {
                $report['stale']++;
            }

            return;
        }

        if ($dryRun) {
            $report['dry_run'][] = ['batch_id' => $batchId, 'events' => $aggregate->events, 'malformed' => $aggregate->stats['malformed'],
                'metrics_5m_rows' => count($aggregate->metrics5m), 'metrics_daily_rows' => count($aggregate->metricsDaily),
                'ip_ua_rows' => count($aggregate->ipUa), 'sketches' => count($aggregate->sketches), 'periods' => count($aggregate->periods)];
            $report['events'] += $aggregate->events;

            return;
        }

        $result = $applier->apply($batchId, $aggregate, $this->now());
        $this->checkpoint('after_commit', ['batch_id' => $batchId, 'outcome' => $result['outcome']]);

        switch ($result['outcome']) {
            case 'applied':
                $report['applied']++;
                $report['events'] += $aggregate->events;
                $report['late_periods'] += $result['late_periods'];
                @rename($path, $this->spool->doneDir().'/'.$batchId.'.ndjson');
                break;
            case 'already_applied':
            case 'duplicate_content':
                $report[$result['outcome']]++;
                @rename($path, $this->spool->doneDir().'/'.$batchId.'.ndjson');
                break;
            default: // conflict: same batch id, different content
                $report['conflicts']++;
                @rename($path, $this->spool->quarantineDir().'/'.$batchId.'.conflict-'.substr($aggregate->stats['sha256'], 0, 12).'.ndjson');
                $report['errors'][] = "conflict: batch {$batchId} was already applied with different content; quarantined";
        }
    }

    /**
     * One coverage row for the current minute — the spool's OPERATIONAL state as this run observed it, not merely the
     * fact that the scheduler ran:
     *  - spool_enabled:  the writer is switched on in this application's configuration;
     *  - spool_writable: a probe file can be created, written, fsynced and removed in active/ (the writers' own
     *                    directory), and every existing drop-ledger shard can be opened for writing;
     *  - breaker_open:   the circuit breaker is shedding events;
     *  - spool_full:     the global file limit is reached, or free disk is below the reserve (new minutes are refused).
     */
    private function recordCoverage(Connection $db): void
    {
        $writable = false;
        if (is_dir($this->spool->activeDir()) || @mkdir($this->spool->activeDir(), $this->spool->dirMode, true)) {
            $probe = $this->spool->activeDir().'/.probe-'.getmypid();
            $handle = @fopen($probe, 'xb');
            if ($handle !== false) {
                $writable = fwrite($handle, 'ok') === 2 && fflush($handle) && @fsync($handle);
                fclose($handle);
                @unlink($probe);
            }
        }
        for ($shard = 0; $writable && $shard < $this->spool->ledgerShards; $shard++) {
            $path = $this->spool->ledgerShardPath($shard);
            if (is_file($path)) {
                $h = @fopen($path, 'r+b');
                $writable = $h !== false;
                if ($h !== false) {
                    fclose($h);
                }
            }
        }
        clearstatcache(true, $this->spool->breakerPath());
        $until = @filemtime($this->spool->breakerPath());
        $free = @disk_free_space($this->spool->root);
        $full = SpoolWriter::spoolFileCount($this->spool) >= $this->spool->maxFiles
            || ($free !== false && $free < $this->spool->diskReserveBytes);

        $db->table(T::COVERAGE)->upsert([[
            'minute' => intdiv($this->now(), 60),
            'spool_enabled' => $this->spool->enabled,
            'spool_writable' => $writable,
            'breaker_open' => $until !== false && $until > $this->now(),
            'spool_full' => $full,
            'recorded_at' => $this->now(),
        ]], ['minute'], ['spool_enabled', 'spool_writable', 'breaker_open', 'spool_full', 'recorded_at']);
    }

    /** done/: delete files past the retention period, then oldest-first beyond the size cap. Returns files deleted. */
    private function enforceDoneRetention(): int
    {
        $files = [];
        foreach (glob($this->spool->doneDir().'/*.ndjson') ?: [] as $path) {
            $files[$path] = ['mtime' => (int) @filemtime($path), 'size' => (int) @filesize($path)];
        }
        uasort($files, fn (array $a, array $b) => $a['mtime'] <=> $b['mtime']);

        $deleted = 0;
        $cutoff = $this->now() - $this->config->doneRetentionHours * 3600;
        $total = array_sum(array_column($files, 'size'));
        foreach ($files as $path => $info) {
            if ($info['mtime'] < $cutoff || $total > $this->config->doneMaxBytes) {
                if (@unlink($path)) {
                    $deleted++;
                    $total -= $info['size'];
                }
            }
        }

        return $deleted;
    }

    private function now(): int
    {
        return (int) ($this->clock !== null ? ($this->clock)() : time());
    }

    /** @param array<string, mixed> $context */
    private function checkpoint(string $point, array $context): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $context);
        }
    }
}
