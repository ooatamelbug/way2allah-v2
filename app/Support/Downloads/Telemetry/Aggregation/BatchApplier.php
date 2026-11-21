<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\HyperLogLog;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Applies one batch EXACTLY ONCE: the ledger row and every aggregate change commit in ONE transaction.
 *
 * Transaction:
 *   1. INSERT the ledger row (unique on batch_id AND sha256). A duplicate aborts the whole transaction — nothing of
 *      this batch is applied twice. The outcome is then classified (see apply()).
 *   2. Read-merge-write every aggregate the batch touches: 5-minute and daily metrics, IP/User-Agent hourly rows,
 *      daily HyperLogLog sketches, and the period-status rows (late-batch transitions included).
 *   3. COMMIT. Only after the commit does the caller move the file to done/.
 *
 * Aggregates are merged in PHP and written back with portable upserts (plain column replacement), because the
 * additive-upsert syntax differs between MySQL, MariaDB and SQLite. This is safe because only one rollup runs at a
 * time (rollup.lock) and, even if two did, the ledger's unique keys make the second transaction fail before it
 * changes anything; rows are read FOR UPDATE where the database supports it.
 *
 * A crash anywhere before COMMIT leaves no trace (rollback); after COMMIT, the ledger row proves the batch applied.
 */
final class BatchApplier
{
    /**
     * @param  (Closure(string, array<string, mixed>): void)|null  $hook  crash-injection points (tests only)
     * @param  (Closure(): bool)|null  $stillOwned  checked inside the transaction immediately before COMMIT (Phase 2.2):
     *                                              false → the transaction is rolled back (advisory lock lost)
     */
    public function __construct(private readonly Connection $db, private readonly ?Closure $hook = null, private readonly ?Closure $stillOwned = null) {}

    /**
     * @return array{outcome: 'applied'|'already_applied'|'duplicate_content'|'conflict', late_periods: int}
     */
    public function apply(string $batchId, BatchAggregate $aggregate, int $now): array
    {
        $this->checkpoint('before_transaction', ['batch_id' => $batchId]);

        try {
            $late = $this->db->transaction(function () use ($batchId, $aggregate, $now): int {
                $this->db->table(T::BATCHES)->insert([
                    'batch_id' => $batchId,
                    'sha256' => $aggregate->stats['sha256'],
                    'schema_version' => max(1, $aggregate->stats['max_version']),
                    'batch_minute' => $aggregate->batchMinute,
                    'lines' => $aggregate->stats['lines'],
                    'events' => $aggregate->events,
                    'malformed' => $aggregate->stats['malformed'],
                    'min_t' => $aggregate->minT,
                    'max_t' => $aggregate->maxT,
                    'late_periods' => 0,
                    'applied_at' => $now,
                ]);
                $this->checkpoint('after_ledger_insert', ['batch_id' => $batchId]);

                $this->mergeMetrics(T::METRICS_5M, 'bucket', $aggregate->metrics5m);
                $this->mergeMetrics(T::METRICS_DAILY, 'day', $aggregate->metricsDaily);
                $this->mergeIpUa($aggregate->ipUa);
                $this->mergeSketches($aggregate->sketches, $now);
                $late = $this->mergePeriods($aggregate->periods, $now);

                if ($late > 0) {
                    $this->db->table(T::BATCHES)->where('batch_id', $batchId)->update(['late_periods' => $late]);
                }
                $this->checkpoint('before_commit', ['batch_id' => $batchId]);
                if ($this->stillOwned !== null && ! ($this->stillOwned)()) {
                    throw new RollupAborted('database advisory lock ownership lost before COMMIT; rolled back');
                }

                return $late;
            });
        } catch (UniqueConstraintViolationException) {
            return ['outcome' => $this->classifyDuplicate($batchId, $aggregate->stats['sha256']), 'late_periods' => 0];
        }

        return ['outcome' => 'applied', 'late_periods' => $late];
    }

    /** @return 'already_applied'|'duplicate_content'|'conflict' */
    private function classifyDuplicate(string $batchId, string $sha256): string
    {
        $byId = $this->db->table(T::BATCHES)->where('batch_id', $batchId)->value('sha256');
        if ($byId !== null) {
            return $byId === $sha256 ? 'already_applied' : 'conflict';   // same id, different content: never apply
        }

        return 'duplicate_content';                                          // same content under another id
    }

    /** @param array<string, array<string, int|string>> $rows */
    private function mergeMetrics(string $table, string $timeColumn, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $keyColumns = [$timeColumn, 'module', 'event', 'outcome', 'variant', 'source_host'];
        $byTime = [];
        foreach ($rows as $row) {
            $byTime[(int) $row[$timeColumn]][] = $row;
        }

        $merged = [];
        foreach ($byTime as $time => $newRows) {
            $existing = [];
            foreach ($this->query($table)->where($timeColumn, $time)->get() as $old) {
                $old = (array) $old;
                $existing[self::key($old, $keyColumns)] = $old;
            }
            foreach ($newRows as $row) {
                $old = $existing[self::key($row, $keyColumns)] ?? null;
                if ($old !== null) {
                    foreach (array_keys(BatchAggregate::zeroMetrics()) as $column) {
                        $row[$column] = str_ends_with($column, '_max')
                            ? max((int) $old[$column], (int) $row[$column])
                            : (int) $old[$column] + (int) $row[$column];
                    }
                }
                $merged[] = $row;
            }
        }

        $this->upsert($table, $merged, $keyColumns, array_keys(BatchAggregate::zeroMetrics()));
    }

    /** @param array<string, array{hour: int, ip: string, ua_hash: string, ua_family: string, events: int, capacity_rejected: int, transfers_started: int, transfers_finished: int, redirects: int, head_requests: int, range_requests: int, resources: array<string, true>, first_t: int, last_t: int}> $rows */
    private function mergeIpUa(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $counters = ['events', 'capacity_rejected', 'transfers_started', 'transfers_finished', 'redirects', 'head_requests', 'range_requests'];
        $byHour = [];
        foreach ($rows as $row) {
            $byHour[$row['hour']][] = $row;
        }

        $out = [];
        foreach ($byHour as $hour => $newRows) {
            foreach (array_chunk($newRows, 500) as $chunk) {
                $existing = [];
                $ips = array_values(array_unique(array_column($chunk, 'ip')));
                foreach ($this->query(T::IP_UA_HOURLY)->where('hour', $hour)->whereIn('ip', $ips)->get() as $old) {
                    $existing[$old->ip.'|'.$old->ua_hash] = (array) $old;
                }
                foreach ($chunk as $row) {
                    $old = $existing[$row['ip'].'|'.$row['ua_hash']] ?? null;
                    $resources = $row['resources'];
                    $overflow = count($resources) > BatchAggregate::MAX_RESOURCES;
                    if ($old !== null) {
                        foreach ($counters as $c) {
                            $row[$c] += (int) $old[$c];
                        }
                        $row['first_t'] = min($row['first_t'], (int) $old['first_t']);
                        $row['last_t'] = max($row['last_t'], (int) $old['last_t']);
                        $row['ua_family'] = $row['ua_family'] !== '' ? $row['ua_family'] : (string) $old['ua_family'];
                        foreach ((array) json_decode((string) $old['resources'], true) as $key) {
                            $resources[(string) $key] = true;
                        }
                        $overflow = $overflow || (bool) $old['resources_overflow'] || count($resources) > BatchAggregate::MAX_RESOURCES;
                    }
                    $keys = array_keys($resources);
                    sort($keys);
                    $out[] = [
                        'hour' => $row['hour'], 'ip' => $row['ip'], 'ua_hash' => $row['ua_hash'], 'ua_family' => $row['ua_family'],
                        'events' => $row['events'], 'capacity_rejected' => $row['capacity_rejected'],
                        'transfers_started' => $row['transfers_started'], 'transfers_finished' => $row['transfers_finished'],
                        'redirects' => $row['redirects'], 'head_requests' => $row['head_requests'], 'range_requests' => $row['range_requests'],
                        'resources' => json_encode(array_slice($keys, 0, BatchAggregate::MAX_RESOURCES)),
                        'resources_overflow' => $overflow,
                        'first_t' => $row['first_t'], 'last_t' => $row['last_t'],
                    ];
                }
            }
        }

        $this->upsert(T::IP_UA_HOURLY, $out, ['hour', 'ip', 'ua_hash'],
            ['ua_family', ...$counters, 'resources', 'resources_overflow', 'first_t', 'last_t']);
    }

    /** @param array<string, HyperLogLog> $sketches "day|dimension" => sketch */
    private function mergeSketches(array $sketches, int $now): void
    {
        $out = [];
        foreach ($sketches as $key => $sketch) {
            [$day, $dimension] = explode('|', $key, 2);
            $old = $this->query(T::SKETCHES)->where('day', (int) $day)->where('dimension', $dimension)->value('registers');
            if (is_string($old) && strlen($old) === HyperLogLog::REGISTERS) {
                $sketch->merge(new HyperLogLog($old));
            }
            $out[] = ['day' => (int) $day, 'dimension' => $dimension, 'registers' => $sketch->registers(), 'updated_at' => $now];
        }
        $this->upsert(T::SKETCHES, $out, ['day', 'dimension'], ['registers', 'updated_at']);
    }

    /**
     * Counters and late-batch transitions for every period the batch touches:
     *  - a period not seen before → provisional;
     *  - final or corrected → corrected (counts updated, `corrected_at` set) — a stale "final" is never kept;
     *  - incomplete → stays incomplete (counts updated).
     * Returns how many already-finalized periods this batch changed.
     *
     * @param  array<string, array{granularity: string, start: int, events: int, malformed: int}>  $periods
     */
    private function mergePeriods(array $periods, int $now): int
    {
        $late = 0;
        $out = [];
        foreach ($periods as $p) {
            $old = $this->query(T::PERIODS)->where('granularity', $p['granularity'])->where('period_start', $p['start'])->first();
            $row = $old !== null ? (array) $old : ['granularity' => $p['granularity'], 'period_start' => $p['start'], 'state' => PeriodFinalizer::PROVISIONAL,
                'events' => 0, 'batches' => 0, 'malformed' => 0, 'late_batches' => 0, 'coverage_minutes' => 0, 'dropped_events' => 0,
                'reasons' => '[]', 'finalized_at' => null, 'corrected_at' => null, 'updated_at' => $now];

            $row['events'] = (int) $row['events'] + $p['events'];
            $row['malformed'] = (int) $row['malformed'] + $p['malformed'];
            $row['batches'] = (int) $row['batches'] + 1;
            $row['updated_at'] = $now;

            if (in_array($row['state'], [PeriodFinalizer::FINAL, PeriodFinalizer::CORRECTED, PeriodFinalizer::INCOMPLETE], true)) {
                $row['late_batches'] = (int) $row['late_batches'] + 1;
                $late++;
                if ($row['state'] !== PeriodFinalizer::INCOMPLETE) {
                    $row['state'] = PeriodFinalizer::CORRECTED;
                    $row['corrected_at'] = $now;
                }
                if ($p['malformed'] > 0) {
                    $row['state'] = PeriodFinalizer::INCOMPLETE;   // a late batch with lost lines: no longer trustworthy
                    $row['reasons'] = PeriodFinalizer::addReason((string) $row['reasons'], 'malformed_lines');
                }
            }
            $out[] = $row;
        }
        $this->upsert(T::PERIODS, $out, ['granularity', 'period_start'],
            ['state', 'events', 'batches', 'malformed', 'late_batches', 'reasons', 'corrected_at', 'updated_at']);

        return $late;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $update
     */
    private function upsert(string $table, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, 300) as $chunk) {
            $this->db->table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }

    private function query(string $table): Builder
    {
        $query = $this->db->table($table);

        return $this->db->getDriverName() === 'sqlite' ? $query : $query->lockForUpdate();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private static function key(array $row, array $columns): string
    {
        return implode('|', array_map(fn (string $c) => (string) $row[$c], $columns));
    }

    /** @param array<string, mixed> $context */
    private function checkpoint(string $point, array $context): void
    {
        if ($this->hook !== null) {
            ($this->hook)($point, $context);
        }
    }
}
