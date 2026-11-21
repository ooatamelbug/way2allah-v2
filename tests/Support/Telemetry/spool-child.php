<?php

/*
 * Child process for the telemetry spool's multi-process tests and benchmark.
 * Pure PHP + Composer autoload (the spool needs no Laravel), driven by a JSON spec:
 *
 *   role      writer | sealer | bench
 *   config    SpoolConfig::fromArray() input
 *   id        this child's id
 *   barrier   directory for deterministic synchronisation (see SpoolProcess)
 *   pause     protocol points to stop at once: "<point>" — the child touches
 *             "{id}.{point}.reached" and waits for "{id}.{point}.release"
 *   gate      wait for "{barrier}/go" before starting (simultaneous start)
 *   count     events to write (writer)
 *   minute    fixed clock minute for the writer (null = real time)
 *   pad       bytes of User-Agent padding per event
 *   no_xfsz   ignore SIGXFSZ (so RLIMIT_FSIZE makes writes fail instead of killing the process)
 *   rate, duration   events/second for this child and seconds (bench)
 *
 * Prints one JSON object (the result) on stdout.
 */

use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolSealer;
use App\Support\Downloads\Telemetry\SpoolWriter;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$spec = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$config = SpoolConfig::fromArray($spec['config']);
$id = (string) $spec['id'];
$barrier = $spec['barrier'] ?? null;
$pauses = array_flip($spec['pause'] ?? []);

if (! empty($spec['no_xfsz'])) {
    pcntl_signal(SIGXFSZ, SIG_IGN);
}

$waitFor = static function (string $file, float $timeout = 30.0): void {
    $until = microtime(true) + $timeout;
    while (! file_exists($file)) {
        if (microtime(true) > $until) {
            fwrite(STDERR, "timeout waiting for {$file}\n");
            exit(3);
        }
        usleep(200);
    }
};

$hook = static function (string $point, array $context) use (&$pauses, $barrier, $id, $waitFor): void {
    if ($barrier === null || ! isset($pauses[$point])) {
        return;
    }
    unset($pauses[$point]);   // each point pauses once
    touch("{$barrier}/{$id}.{$point}.reached");
    $waitFor("{$barrier}/{$id}.{$point}.release");
};

if (! empty($spec['gate']) && $barrier !== null) {
    touch("{$barrier}/{$id}.ready");
    $waitFor("{$barrier}/go", (float) ($spec['gate_timeout'] ?? 30.0));
}

$rusage = static function (): int {
    $r = getrusage();

    return (int) (($r['ru_utime.tv_sec'] + $r['ru_stime.tv_sec']) * 1_000_000 + $r['ru_utime.tv_usec'] + $r['ru_stime.tv_usec']);
};

$event = static function (string $eventId, int $pad): array {
    return ['t' => microtime(true), 'ev' => 'capacity_rejected', 'id' => $eventId, 'mod' => 'khotab', 'rt' => 'khotab_item',
        'rid' => random_int(1, 300000), 'var' => 'mirror', 'vid' => random_int(1, 400000), 'm' => 'GET', 'head' => false,
        'ip' => '203.0.113.'.random_int(1, 254), 'ua' => 'Mozilla/5.0 (test) '.str_repeat('x', $pad), 'uaf' => 'browser',
        'pa' => 400, 'pl' => 400];
};

switch ($spec['role']) {
    case 'writer':
        // `minute_step`: the simulated clock advances one minute every N events (a sealer-less spool filling up over time).
        $i = 0;
        $clock = isset($spec['minute'])
            ? static function () use ($spec, &$i): float {
                $step = (int) ($spec['minute_step'] ?? 0);

                return ($spec['minute'] + ($step > 0 ? intdiv(max(0, $i - 1), $step) : 0)) * 60 + 1.0;
            }
        : null;
        $diskFree = isset($spec['disk_free']) ? static fn (string $dir): float => (float) $spec['disk_free'] : null;
        $writer = new SpoolWriter($config, $clock, $hook, $diskFree);
        $written = [];
        $dropped = 0;
        for ($i = 1; $i <= (int) $spec['count']; $i++) {   // $i also drives the simulated clock
            $eventId = "{$id}-{$i}";
            $outcome = $writer->write($event($eventId, (int) ($spec['pad'] ?? 0)));
            if ($outcome === SpoolWriter::WRITTEN) {
                $written[] = $eventId;
            } elseif ($outcome === SpoolWriter::DROPPED) {
                $dropped++;
            }
        }
        // What the shutdown function would do, done explicitly so the result reports the final accounting state.
        $pendingBeforeFlush = DropLedger::pendingEvents();
        (new DropLedger($config))->flushPending();
        echo json_encode(['id' => $id, 'attempted' => (int) $spec['count'], 'written' => $written, 'dropped' => $dropped,
            'stats' => $writer->stats(), 'pending_before_flush' => $pendingBeforeFlush, 'unrecorded' => DropLedger::unrecorded(),
            'ledger_spins' => DropLedger::spins(), 'last_latency_ns' => $writer->lastLatencyNs(), 'survived' => true]);
        break;

    case 'sealer':
        $sealer = new SpoolSealer($config, null, $hook);
        $sealed = [];
        do {
            $report = $sealer->seal((bool) ($spec['all'] ?? true));
            foreach ($report['sealed'] as $batch) {
                $sealed[] = ['batch_id' => $batch['batch_id'], 'bytes' => $batch['bytes'], 'path' => $batch['path']];
            }
            $loop = $barrier !== null && ! empty($spec['loop']) && ! file_exists("{$barrier}/stop");
            if ($loop) {
                usleep((int) ($spec['interval_us'] ?? 2000));
            }
        } while ($loop);
        echo json_encode(['id' => $id, 'sealed' => $sealed, 'last_report' => $report]);
        break;

    case 'bench':
        $writer = new SpoolWriter($config);
        $rate = max(1, (int) $spec['rate']);
        $interval = 1.0 / $rate;
        $end = microtime(true) + (float) $spec['duration'];
        $latencies = [];
        $written = 0;
        $dropped = 0;
        $cpuBefore = $rusage();
        $next = microtime(true);
        $n = 0;
        while (($now = microtime(true)) < $end) {
            if ($now < $next) {
                usleep((int) (($next - $now) * 1_000_000));
            }
            $next += $interval;
            $t0 = hrtime(true);
            $outcome = $writer->write($event("{$id}-".(++$n), (int) ($spec['pad'] ?? 0)));
            $latencies[] = intdiv(hrtime(true) - $t0, 1000);
            $outcome === SpoolWriter::WRITTEN ? $written++ : $dropped++;
        }
        $cpuUs = $rusage() - $cpuBefore;
        $pendingBeforeFlush = DropLedger::pendingEvents();
        (new DropLedger($config))->flushPending();
        echo json_encode(['id' => $id, 'attempted' => $n, 'written' => $written, 'dropped' => $dropped, 'latencies_us' => $latencies,
            'cpu_us' => $cpuUs, 'stats' => $writer->stats(), 'pending_before_flush' => $pendingBeforeFlush,
            'unrecorded' => DropLedger::unrecorded(), 'ledger_spins' => DropLedger::spins()]);
        break;
}
