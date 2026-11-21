<?php

namespace App\Support\Downloads\Telemetry\Aggregation;

use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\HyperLogLog;
use App\Support\Downloads\Telemetry\TelemetryEvent;

/**
 * One sealed batch, parsed and aggregated in memory — no database. BatchApplier writes the result in one
 * transaction.
 *
 * Every event is bucketed by its OWN timestamp `t` (not the file's minute), so an event that arrives late lands in
 * the period it belongs to. Malformed lines (which have no trustworthy `t`) are attributed to the batch's minute.
 *
 * Definitions: `ip` is one IP address; (ip, ua_hash) is one IP/User-Agent combination — never a person.
 */
final class BatchAggregate
{
    public const MAX_RESOURCES = 64;

    /** @var array{bytes: int, sha256: string, lines: int, valid: int, malformed: int, unsupported: int, empty: int, trailing_fragment: bool, max_version: int} */
    public array $stats;

    public int $events = 0;

    public ?int $minT = null;

    public ?int $maxT = null;

    /** @var array<string, array<string, int|string>> key => row */
    public array $metrics5m = [];

    /** @var array<string, array<string, int|string>> */
    public array $metricsDaily = [];

    /** @var array<string, array{hour: int, ip: string, ua_hash: string, ua_family: string, events: int, capacity_rejected: int, transfers_started: int, transfers_finished: int, redirects: int, head_requests: int, range_requests: int, resources: array<string, true>, first_t: int, last_t: int}> */
    public array $ipUa = [];

    /** @var array<string, HyperLogLog> "day|dimension" => sketch */
    public array $sketches = [];

    /** @var array<string, array{granularity: string, start: int, events: int, malformed: int}> "granularity|start" => counters */
    public array $periods = [];

    private function __construct(public readonly int $batchMinute) {}

    public static function fromFile(string $path, int $batchMinute): self
    {
        $aggregate = new self($batchMinute);
        $aggregate->stats = BatchInspector::inspect($path, fn (TelemetryEvent $e) => $aggregate->add($e->toArray()));

        if ($aggregate->stats['malformed'] > 0) {
            $t = $batchMinute * 60;
            $aggregate->periodAdd('hour', intdiv($t, 3600) * 3600, 'malformed', $aggregate->stats['malformed']);
            $aggregate->periodAdd('day', intdiv($t, 86400) * 86400, 'malformed', $aggregate->stats['malformed']);
        }

        return $aggregate;
    }

    /** @param array<string, bool|int|float|string> $e a validated v1 event */
    private function add(array $e): void
    {
        $t = (int) $e['t'];
        $this->events++;
        $this->minT = $this->minT === null ? $t : min($this->minT, $t);
        $this->maxT = $this->maxT === null ? $t : max($this->maxT, $t);

        $hour = intdiv($t, 3600) * 3600;
        $day = intdiv($t, 86400) * 86400;
        $module = (string) $e['mod'];
        $event = (string) $e['ev'];
        $dims = [
            'module' => $module,
            'event' => $event,
            'outcome' => (string) ($e['st'] ?? $e['k'] ?? ''),
            'variant' => (string) ($e['var'] ?? ''),
            'source_host' => (string) ($e['sh'] ?? ''),
        ];

        self::accumulate($this->metrics5m, ['bucket' => intdiv($t, 300) * 300] + $dims, $e);
        self::accumulate($this->metricsDaily, ['day' => $day] + $dims, $e);

        $this->periodAdd('hour', $hour, 'events', 1);
        $this->periodAdd('day', $day, 'events', 1);

        $ip = (string) ($e['ip'] ?? '');
        if ($ip !== '') {
            $uaHash = (string) ($e['uah'] ?? '');
            $key = $hour.'|'.$ip.'|'.$uaHash;
            $row = $this->ipUa[$key] ?? ['hour' => $hour, 'ip' => $ip, 'ua_hash' => $uaHash, 'ua_family' => '', 'events' => 0,
                'capacity_rejected' => 0, 'transfers_started' => 0, 'transfers_finished' => 0, 'redirects' => 0,
                'head_requests' => 0, 'range_requests' => 0, 'resources' => [], 'first_t' => $t, 'last_t' => $t];
            $row['events']++;
            $row['capacity_rejected'] += $event === 'capacity_rejected' ? 1 : 0;
            $row['transfers_started'] += $event === 'transfer_started' ? 1 : 0;
            $row['transfers_finished'] += $event === 'transfer_finished' ? 1 : 0;
            $row['redirects'] += $event === 'redirect_issued' ? 1 : 0;
            $row['head_requests'] += ! empty($e['head']) ? 1 : 0;
            $row['range_requests'] += ! empty($e['rng']) ? 1 : 0;
            $row['ua_family'] = (string) ($e['uaf'] ?? $row['ua_family']);
            $row['first_t'] = min($row['first_t'], $t);
            $row['last_t'] = max($row['last_t'], $t);
            $resource = self::resourceKey($e);
            if ($resource !== null && count($row['resources']) <= self::MAX_RESOURCES) {
                $row['resources'][$resource] = true;   // one past the cap marks overflow
            }
            $this->ipUa[$key] = $row;

            $this->sketch($day, 'ip')->add($ip);
            $this->sketch($day, 'ip_ua')->add($ip.'|'.$uaHash);
            $this->sketch($day, 'ip@'.$module)->add($ip);
        }

        $resource = self::resourceKey($e);
        if ($resource !== null) {
            $this->sketch($day, 'resource@'.$module)->add($resource);
        }
    }

    /**
     * @param  array<string, array<string, int|string>>  $rows
     * @param  array<string, int|string>  $key
     * @param  array<string, bool|int|float|string>  $e
     */
    private static function accumulate(array &$rows, array $key, array $e): void
    {
        $id = implode('|', $key);
        $row = $rows[$id] ?? $key + self::zeroMetrics();
        $duration = isset($e['d']) ? (int) $e['d'] : null;
        $ttfb = isset($e['ttfb']) ? (int) $e['ttfb'] : null;

        $row['events'] = (int) $row['events'] + 1;
        $row['bytes_sum'] = (int) $row['bytes_sum'] + (int) ($e['b'] ?? 0);
        $row['head_count'] = (int) $row['head_count'] + (! empty($e['head']) ? 1 : 0);
        $row['range_count'] = (int) $row['range_count'] + (! empty($e['rng']) ? 1 : 0);
        if ($duration !== null) {
            $row['duration_ms_sum'] = (int) $row['duration_ms_sum'] + $duration;
            $row['duration_ms_max'] = max((int) $row['duration_ms_max'], $duration);
            $bucket = match (true) {
                $duration < 10_000 => 'd_lt_10s',
                $duration < 60_000 => 'd_lt_1m',
                $duration < 600_000 => 'd_lt_10m',
                $duration < 3_600_000 => 'd_lt_1h',
                default => 'd_ge_1h',
            };
            $row[$bucket] = (int) $row[$bucket] + 1;
        }
        if ($ttfb !== null) {
            $row['ttfb_ms_sum'] = (int) $row['ttfb_ms_sum'] + $ttfb;
            $row['ttfb_ms_max'] = max((int) $row['ttfb_ms_max'], $ttfb);
        }
        $rows[$id] = $row;
    }

    /** @return array<string, int> */
    public static function zeroMetrics(): array
    {
        return ['events' => 0, 'bytes_sum' => 0, 'duration_ms_sum' => 0, 'duration_ms_max' => 0, 'ttfb_ms_sum' => 0,
            'ttfb_ms_max' => 0, 'head_count' => 0, 'range_count' => 0, 'd_lt_10s' => 0, 'd_lt_1m' => 0, 'd_lt_10m' => 0,
            'd_lt_1h' => 0, 'd_ge_1h' => 0];
    }

    /** @param array<string, bool|int|float|string> $e */
    private static function resourceKey(array $e): ?string
    {
        if (! isset($e['rid'])) {
            return null;
        }

        return $e['mod'].':'.$e['rid'].':'.($e['var'] ?? '').':'.($e['vid'] ?? '');
    }

    /** @param 'events'|'malformed' $field */
    private function periodAdd(string $granularity, int $start, string $field, int $n): void
    {
        $key = $granularity.'|'.$start;
        $this->periods[$key] ??= ['granularity' => $granularity, 'start' => $start, 'events' => 0, 'malformed' => 0];
        $this->periods[$key][$field] += $n;
    }

    private function sketch(int $day, string $dimension): HyperLogLog
    {
        return $this->sketches[$day.'|'.$dimension] ??= new HyperLogLog;
    }
}
