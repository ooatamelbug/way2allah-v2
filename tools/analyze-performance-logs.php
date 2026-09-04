#!/usr/bin/env php
<?php

/**
 * E-06 operations tooling — correlates the two E-01 log channels to answer
 * "how often does this query actually run, on which routes, and how slow is
 * it really?" from data the application already writes.
 *
 * `performance-*.log` records one line per request (route_uri, duration_ms,
 * query_count, total_query_ms, request_id). `slow-sql-*.log` records one
 * line per query over the threshold (sql, duration_ms, request_id). The
 * shared `request_id` joins them, which is what turns an anonymous slow
 * query into "this query ran 41 times in 24h, always on /khotab-item-*".
 *
 * Read-only: parses logs, changes no application behaviour, and is not
 * loaded by the application at runtime.
 *
 * Usage:
 *   php tools/analyze-performance-logs.php [--days=1] [--grep=<substring>]
 */
$opts = getopt('', ['days::', 'grep::', 'logs::']);
$days = (int) ($opts['days'] ?? 1);
$grep = $opts['grep'] ?? null;
$dir = $opts['logs'] ?? __DIR__.'/../storage/logs';

function records(string $glob, int $days): array
{
    $out = [];
    $cutoff = strtotime("-{$days} days");
    foreach (glob($glob) as $file) {
        if (filemtime($file) < $cutoff) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/(\{.*\})\s*$/', $line, $m)
                && ($row = json_decode($m[1], true)) !== null) {
                $row['_ts'] = preg_match('/^\[([^\]]+)\]/', $line, $t) ? strtotime($t[1]) : null;
                $out[] = $row;
            }
        }
    }

    return $out;
}

$requests = records("$dir/performance-*.log", $days);
$queries = records("$dir/slow-sql-*.log", $days);

$routeById = [];
foreach ($requests as $r) {
    if (isset($r['request_id'])) {
        $routeById[$r['request_id']] = $r['route_uri'] ?? '(none)';
    }
}

/** Group slow queries by normalised shape. */
$families = [];
foreach ($queries as $q) {
    $sql = $q['sql'] ?? '';
    if ($grep !== null && stripos($sql, $grep) === false) {
        continue;
    }
    $key = preg_replace('/\s+/', ' ', substr($sql, 0, 150));
    $families[$key]['durations'][] = $q['duration_ms'];
    $families[$key]['times'][] = $q['_ts'];
    $route = $routeById[$q['request_id'] ?? ''] ?? '(unmatched)';
    $families[$key]['routes'][$route] = ($families[$key]['routes'][$route] ?? 0) + 1;
}

if ($families === []) {
    fwrite(STDERR, "No slow queries matched.\n");
    exit(0);
}

function pct(array $sorted, float $p): float
{
    return $sorted[min(count($sorted) - 1, (int) floor($p * count($sorted)))];
}

uasort($families, fn ($a, $b) => count($b['durations']) <=> count($a['durations']));

printf("Window: last %d day(s)   requests logged: %d   slow queries: %d\n\n", $days, count($requests), count($queries));

foreach ($families as $sql => $f) {
    $d = $f['durations'];
    sort($d);
    $ts = array_values(array_filter($f['times']));
    sort($ts);

    printf("executions: %-5d  p50 %7.1fms  p95 %7.1fms  max %7.1fms  total %6.2fs\n",
        count($d), pct($d, .5), pct($d, .95), max($d), array_sum($d) / 1000);

    if (count($ts) > 1) {
        $gaps = [];
        for ($i = 1; $i < count($ts); $i++) {
            $gaps[] = $ts[$i] - $ts[$i - 1];
        }
        sort($gaps);
        printf("  span %s → %s   median gap between executions: %ds%s\n",
            date('Y-m-d H:i', $ts[0]), date('Y-m-d H:i', end($ts)), pct($gaps, .5),
            // A gap clustering at the cache TTL is the signature of a
            // cache-expiry-driven query rather than an uncached one.
            (pct($gaps, .5) >= 240 && pct($gaps, .5) <= 360) ? '  <-- clusters at the 300s cache TTL' : '');
    }

    arsort($f['routes']);
    $routes = [];
    foreach (array_slice($f['routes'], 0, 3, true) as $r => $n) {
        $routes[] = "$r ($n)";
    }
    printf("  routes: %s\n", implode(', ', $routes));
    printf("  %s\n\n", trim($sql));
}
