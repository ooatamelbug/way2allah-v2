<?php

/*
 * Telemetry spool writer benchmark (Phase 1 performance validation). Not part of the test suite. Run manually:
 *
 *   php tests/benchmarks/spool-writer-bench.php [seconds=60] [runs=3] [results.jsonl] [configs=A,B,C,D]
 *
 * Compares writer configurations (lock budget × file-size cap, total budget ≈ 1 GiB) at 1,000 and 5,000
 * events/s. Each run: an isolated temporary spool; N paced writer processes writing realistic events (~331 bytes);
 * a sealer behaving as in production (seals only minutes that have ended, checks every second). Runs start on a
 * minute boundary and last a full minute by default, so the per-minute file cap is exercised as in production.
 *
 * Measured per run: write() latency p50/p95/p99/max; attempted/written/dropped; drops per reason; ledger accounting
 * failures; time spent updating the drop ledger; writer CPU; spool disk use (bytes and allocated blocks).
 * Everything is created under the system temp directory and removed afterwards.
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once dirname(__DIR__).'/Support/Telemetry/SpoolProcess.php';

use Tests\Support\Telemetry\SpoolProcess;

$seconds = (int) ($argv[1] ?? 60);
$runs = (int) ($argv[2] ?? 3);
$resultsFile = $argv[3] ?? null;
$only = isset($argv[4]) ? explode(',', $argv[4]) : null;

$mib = 1024 * 1024;
$configs = [
    'A' => ['lock_budget_us' => 2000, 'file_max_bytes' => 8 * $mib, 'max_files' => 128],
    'B' => ['lock_budget_us' => 5000, 'file_max_bytes' => 8 * $mib, 'max_files' => 128],
    'C' => ['lock_budget_us' => 2000, 'file_max_bytes' => 32 * $mib, 'max_files' => 32],
    'D' => ['lock_budget_us' => 5000, 'file_max_bytes' => 32 * $mib, 'max_files' => 32],
];
$rates = [1000 => 8, 5000 => 16];   // total events/s => writer processes

$percentile = static fn (array $sorted, float $p): int => $sorted === [] ? 0 : $sorted[(int) min(count($sorted) - 1, floor($p / 100 * count($sorted)))];

$remove = static function (string $dir): void {
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
};

$diskUse = static function (string $dir): array {
    $bytes = $blocks = $files = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $stat = stat($f->getPathname());
            $bytes += $stat['size'];
            $blocks += $stat['blocks'] * 512;
            $files += str_ends_with($f->getFilename(), '.ndjson') ? 1 : 0;
        }
    }

    return ['bytes' => $bytes, 'allocated' => $blocks, 'spool_files' => $files];
};

fprintf(STDERR, "PHP %s, %s, %d CPUs, temp %s, %d s × %d runs\n", PHP_VERSION, php_uname('s').' '.php_uname('r'), (int) shell_exec('nproc'), sys_get_temp_dir(), $seconds, $runs);

// Resume: scenarios already recorded in the results file are skipped.
$done = [];
foreach ($resultsFile !== null && is_file($resultsFile) ? file($resultsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    $row = json_decode($line, true);
    if (is_array($row)) {
        $done["{$row['config']}:{$row['rate']}:{$row['run']}"] = true;
    }
}

foreach ($configs as $name => $tuning) {
    if ($only !== null && ! in_array($name, $only, true)) {
        continue;
    }
    foreach ($rates as $rate => $processes) {
        for ($run = 1; $run <= $runs; $run++) {
            if (isset($done["{$name}:{$rate}:{$run}"])) {
                continue;
            }
            $root = sys_get_temp_dir().'/w2a-spool-bench-'.bin2hex(random_bytes(4));
            $barrier = $root.'-barrier';
            mkdir($barrier, 0775, true);
            // Shipped defaults for everything not under comparison (Phase 1.1: 16 ledger shards, breaker on health faults ≥ 50/min).
            $config = $tuning + ['spool' => 'on', 'root' => $root, 'disk_reserve_bytes' => 0, 'ledger_lock_budget_us' => 50_000,
                'rotation_retries' => 3, 'ledger_slots' => 10080, 'ledger_shards' => 16, 'slow_write_us' => 50_000, 'breaker_fault_threshold' => 50,
                'breaker_open_seconds' => 300, 'seal_grace_minutes' => 0, 'seal_lock_budget_us' => 2_000_000];

            $children = [];
            for ($i = 0; $i < $processes; $i++) {
                $children[] = SpoolProcess::start(['role' => 'bench', 'id' => "b{$i}", 'config' => $config, 'rate' => intdiv($rate, $processes),
                    'duration' => $seconds, 'pad' => 100, 'gate' => true, 'gate_timeout' => 180], $barrier);
            }
            $sealer = SpoolProcess::start(['role' => 'sealer', 'id' => 'sealer', 'config' => $config, 'loop' => true, 'all' => false,
                'interval_us' => 1_000_000, 'gate' => true, 'gate_timeout' => 180], $barrier);
            foreach ([...$children, $sealer] as $p) {
                $p->waitReady();
            }
            if ($seconds >= 60) {
                time_sleep_until((floor(microtime(true) / 60) + 1) * 60 + 0.5);   // start on a minute boundary
            }
            SpoolProcess::go($barrier);

            $lat = [];
            $sum = ['attempted' => 0, 'written' => 0, 'dropped' => 0, 'cpu_us' => 0, 'spins' => 0, 'rotations' => 0, 'unrecorded' => 0,
                'ledger_calls' => 0, 'ledger_ns_total' => 0, 'ledger_ns_max' => 0, 'ledger_failed' => 0, 'ledger_spins' => 0, 'breaker_trips' => 0];
            $reasons = [];
            foreach ($children as $child) {
                $r = $child->wait(900);
                array_push($lat, ...$r['latencies_us']);
                foreach (['attempted', 'written', 'dropped', 'cpu_us', 'unrecorded'] as $k) {
                    $sum[$k] += $r[$k];
                }
                $sum['spins'] += $r['stats']['lock_spins'];
                $sum['rotations'] += $r['stats']['rotations'];
                $sum['ledger_calls'] += $r['stats']['ledger_calls'];
                $sum['ledger_ns_total'] += $r['stats']['ledger_ns_total'];
                $sum['ledger_ns_max'] = max($sum['ledger_ns_max'], $r['stats']['ledger_ns_max']);
                $sum['ledger_failed'] += $r['stats']['ledger_failed'] ?? 0;
                $sum['breaker_trips'] += $r['stats']['breaker_trips'] ?? 0;
                $sum['ledger_spins'] += $r['ledger_spins'] ?? 0;
                foreach ($r['stats']['dropped_by_reason'] as $reason => $n) {
                    $reasons[$reason] = ($reasons[$reason] ?? 0) + $n;
                }
            }
            touch($barrier.'/stop');
            $sealer->wait(120);
            sort($lat);
            $disk = $diskUse($root);

            $row = ['config' => $name, 'rate' => $rate, 'run' => $run, 'processes' => $processes, 'seconds' => $seconds] + $tuning + [
                'attempted' => $sum['attempted'], 'written' => $sum['written'], 'dropped' => $sum['dropped'],
                'drop_pct' => $sum['attempted'] > 0 ? round(100 * $sum['dropped'] / $sum['attempted'], 3) : 0,
                'reasons' => $reasons, 'ledger_failures' => $sum['unrecorded'],
                'p50_us' => $percentile($lat, 50), 'p95_us' => $percentile($lat, 95), 'p99_us' => $percentile($lat, 99), 'max_us' => end($lat) ?: 0,
                'ledger_calls' => $sum['ledger_calls'], 'ledger_avg_us' => $sum['ledger_calls'] > 0 ? round($sum['ledger_ns_total'] / $sum['ledger_calls'] / 1000, 1) : 0,
                'ledger_max_us' => intdiv($sum['ledger_ns_max'], 1000),
                'ledger_failed_updates' => $sum['ledger_failed'], 'ledger_shard_lock_retries' => $sum['ledger_spins'], 'breaker_trips' => $sum['breaker_trips'],
                'cpu_us_per_event' => $sum['attempted'] > 0 ? round($sum['cpu_us'] / $sum['attempted'], 1) : 0,
                'writer_cpu_pct_of_one_core' => round(100 * $sum['cpu_us'] / ($seconds * 1e6), 1),
                'lock_spins_per_event' => $sum['attempted'] > 0 ? round($sum['spins'] / $sum['attempted'], 3) : 0, 'rotations' => $sum['rotations'],
                'disk_bytes' => $disk['bytes'], 'disk_allocated' => $disk['allocated'], 'spool_files' => $disk['spool_files'],
                'load' => sys_getloadavg()[0]];

            echo json_encode($row), "\n";
            if ($resultsFile !== null) {
                file_put_contents($resultsFile, json_encode($row)."\n", FILE_APPEND);
            }

            $remove($root);
            $remove($barrier);
        }
    }
}
