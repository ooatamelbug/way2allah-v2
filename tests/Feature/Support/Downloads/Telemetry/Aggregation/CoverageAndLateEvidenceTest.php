<?php

/*
 * Phase 2.1 — coverage means the spool was enabled AND operational, observed by the rollup's own probes (the runner
 * runs once per simulated minute here; nothing is inserted by hand); and late evidence, at any age, reopens final
 * periods instead of being silently ignored.
 */

use App\Support\Downloads\Telemetry\Aggregation\RollupConfig;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use App\Support\Downloads\Telemetry\Database\TelemetrySchema as T;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\SpoolConfig;
use App\Support\Downloads\Telemetry\SpoolStatus;
use App\Support\Downloads\Telemetry\SpoolWriter;
use Illuminate\Support\Facades\DB;
use Tests\Support\InMemoryConnection;

const CLE_MINUTE = 29_379_840;              // 2025-11-10 16:00 UTC
const CLE_HOUR = CLE_MINUTE * 60;
const CLE_FINAL_AT = CLE_HOUR + 3600 + 900 + 5;

function cleSpool(object $test, array $o = []): array
{
    return $o + ['spool' => 'on', 'root' => $test->root, 'ledger_shards' => 2, 'ledger_slots' => 10080, 'disk_reserve_bytes' => 0,
        'seal_grace_minutes' => 0, 'aggregate' => 'on', 'connection' => 'main'];
}

/** The scheduled rollup, once per minute in [$from, $to] (except $skip), at second 30 of each minute. */
function cleRunMinutes(object $test, int $from, int $to, array $settings = [], array $skip = [], ?Closure $before = null): void
{
    $s = cleSpool($test, $settings);
    for ($m = $from; $m <= $to; $m++) {
        if (in_array($m, $skip, true)) {
            continue;
        }
        if ($before !== null) {
            $before($m);
        }
        (new RollupRunner(SpoolConfig::fromArray($s), RollupConfig::fromArray($s), null, fn (): float => $m * 60 + 30.0))->run();
    }
}

function cleFinalize(object $test, int $at, array $settings = []): array
{
    $s = cleSpool($test, $settings);

    return (new RollupRunner(SpoolConfig::fromArray($s), RollupConfig::fromArray($s), null, fn (): float => (float) $at))->run();
}

function cleHour(int $start = CLE_HOUR): object
{
    return DB::connection('main')->table(T::PERIODS)->where('granularity', 'hour')->where('period_start', $start)->first();
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/w2a-cle-'.bin2hex(random_bytes(5));
    InMemoryConnection::setup('main', T::tables());
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
});

afterEach(function () {
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(null);
    if (is_dir($this->root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
            @chmod($f->getPathname(), 0775);
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }
});

// --- Coverage ---------------------------------------------------------------------------------------------------

it('an hour with the rollup running every minute and the spool operational becomes final (genuine probe evidence)', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59);
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('final')->and((int) cleHour()->coverage_minutes)->toBe(60);
});

it('the scheduler running while telemetry is disabled is NOT coverage', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, ['spool' => 'off']);
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('incomplete')->and((int) cleHour()->coverage_minutes)->toBe(0)
        ->and(json_decode(cleHour()->reasons))->toBe(['spool_disabled']);
});

it('a minute in which the scheduler did not run has no evidence: the hour cannot be final', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, [], [CLE_MINUTE + 17]);
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('incomplete')->and((int) cleHour()->coverage_minutes)->toBe(59)
        ->and(json_decode(cleHour()->reasons))->toBe(['coverage_gap']);
});

it('the writers\' own directory being unwritable is NOT coverage', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, [], [], function (int $m) {
        if ($m === CLE_MINUTE + 20) {
            chmod($this->root.'/active', 0555);
        } elseif ($m === CLE_MINUTE + 21) {
            chmod($this->root.'/active', 0775);
        }
    });
    if (posix_geteuid() === 0) {
        $this->markTestSkipped('root ignores directory permissions');
    }
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('incomplete')->and((int) cleHour()->coverage_minutes)->toBe(59)
        ->and(json_decode(cleHour()->reasons))->toContain('spool_unwritable');
});

it('spool capacity exhausted (disk reserve) is NOT coverage', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, ['disk_reserve_bytes' => PHP_INT_MAX >> 1]);
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('incomplete')->and(json_decode(cleHour()->reasons))->toContain('spool_full');
});

it('spool capacity exhausted (global file limit) is NOT coverage', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, ['max_files' => 1], [], function (int $m) {
        if ($m === CLE_MINUTE) {
            @mkdir($this->root.'/quarantine', 0775, true);
            file_put_contents($this->root.'/quarantine/202501010000-1-00000000.ndjson', '{"v":9}'."\n");   // fills the 1-file spool
        }
    });
    cleFinalize($this, CLE_FINAL_AT, ['max_files' => 1]);

    expect(json_decode(cleHour()->reasons))->toContain('spool_full')->and(cleHour()->state)->toBe('incomplete');
});

it('an open circuit breaker is NOT coverage', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59, [], [], function (int $m) {
        if ($m === CLE_MINUTE + 40) {
            touch($this->root.'/breaker', ($m * 60) + 45);   // open until 15 s after this run
        }
    });
    cleFinalize($this, CLE_FINAL_AT);

    expect(json_decode(cleHour()->reasons))->toBe(['breaker_open'])->and((int) cleHour()->coverage_minutes)->toBe(59);
});

it('a delayed batch keeps the hour provisional; once applied, the hour can become final', function () {
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 59);
    // A batch for 16:10 is sealed late (its writer's minute file was only sealed after the hour).
    $writer = new SpoolWriter(SpoolConfig::fromArray(cleSpool($this)), fn (): float => (CLE_MINUTE + 10) * 60 + 1.0);
    $writer->write(['t' => CLE_HOUR + 610.0, 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'ip' => '203.0.113.1']);
    $s = cleSpool($this, ['rollup_max_batches' => 1]);
    // Finalization while it waits in sealed/: a failing apply keeps it there.
    (new RollupRunner(SpoolConfig::fromArray($s), RollupConfig::fromArray($s), function (string $p) {
        if ($p === 'before_transaction') {
            throw new RuntimeException('database briefly unavailable');
        }
    }, fn (): float => (float) CLE_FINAL_AT))->run();
    expect(cleHour()->state)->toBe('provisional');

    cleFinalize($this, CLE_FINAL_AT + 60);   // applied now
    expect(cleHour()->state)->toBe('final')->and((int) cleHour()->events)->toBe(1);
});

it('reboot and recovery: the outage minutes have no evidence and the ledger flags the reboot window', function () {
    $s = SpoolConfig::fromArray(cleSpool($this));
    DropLedger::overrideBootIdForTesting(str_repeat('a', 32));
    cleRunMinutes($this, CLE_MINUTE, CLE_MINUTE + 29);
    (new DropLedger($s))->record(CLE_MINUTE + 29, ['dropped_events' => 1, 'reason' => 2]);
    DropLedger::resetProcessStateForTesting();
    DropLedger::overrideBootIdForTesting(str_repeat('b', 32));                    // machine rebooted; back at 16:36
    (new DropLedger($s))->record(CLE_MINUTE + 36, ['dropped_events' => 1, 'reason' => 2]);
    cleRunMinutes($this, CLE_MINUTE + 36, CLE_MINUTE + 59);
    cleFinalize($this, CLE_FINAL_AT);

    expect(cleHour()->state)->toBe('incomplete')
        ->and(json_decode(cleHour()->reasons))->toContain('coverage_gap', 'ledger_uncertain', 'dropped_events');
});

// --- Late evidence ----------------------------------------------------------------------------------------------

it('a drop recorded for an hour long after its 24-hour re-check window still reopens it (and its day)', function () {
    $day = intdiv(CLE_HOUR, 86400) * 86400;
    $rows = [];
    for ($m = intdiv($day, 60); $m < intdiv($day, 60) + 1440; $m++) {
        $rows[] = ['minute' => $m, 'spool_enabled' => true, 'spool_writable' => true, 'breaker_open' => false, 'spool_full' => false, 'recorded_at' => $m * 60];
    }
    foreach (array_chunk($rows, 300) as $chunk) {
        DB::connection('main')->table(T::COVERAGE)->insert($chunk);
    }
    cleFinalize($this, $day + 86400 + 7200 + 5);
    expect(cleHour()->state)->toBe('final')
        ->and(DB::connection('main')->table(T::PERIODS)->where('granularity', 'day')->value('state'))->toBe('final');

    // Three days later a drop for 16:20 that day is recorded (e.g. a stuck process finally flushing).
    $later = $day + 4 * 86400;
    (new DropLedger(SpoolConfig::fromArray(cleSpool($this))))->record(CLE_MINUTE + 20, ['dropped_events' => 2, 'reason' => 2]);
    expect(SpoolStatus::collect(SpoolConfig::fromArray(cleSpool($this)), 60, (float) $later)['accounting_uncertainty'])->toHaveKey('late_drop_evidence_pending');

    $report = cleFinalize($this, $later);

    expect($report['finalizer']['late_evidence_hours'])->toBe(1)
        ->and(cleHour()->state)->toBe('incomplete')->and(json_decode(cleHour()->reasons))->toContain('late_drop_evidence')
        ->and(DB::connection('main')->table(T::PERIODS)->where('granularity', 'day')->value('state'))->toBe('incomplete')
        ->and((new DropLedger(SpoolConfig::fromArray(cleSpool($this))))->lateEvidence()['min_minute'])->toBe(0);   // handled, cleared
});

it('a drop for a minute whose ring slot already holds a newer minute never overwrites it, and still reopens the old hour', function () {
    $settings = ['ledger_slots' => 120, 'ledger_shards' => 1];
    $s = SpoolConfig::fromArray(cleSpool($this, $settings));
    $ledger = new DropLedger($s);
    $ledger->record(CLE_MINUTE + 125, ['dropped_events' => 7, 'reason' => 2]);   // slot (M+125) % 120 = slot of M+5
    DB::connection('main')->table(T::PERIODS)->insert(['granularity' => 'hour', 'period_start' => CLE_HOUR, 'state' => 'final', 'events' => 3,
        'batches' => 1, 'malformed' => 0, 'late_batches' => 0, 'coverage_minutes' => 60, 'dropped_events' => 0, 'reasons' => '[]',
        'finalized_at' => CLE_FINAL_AT, 'corrected_at' => null, 'updated_at' => CLE_FINAL_AT]);

    $ledger->record(CLE_MINUTE + 5, ['dropped_events' => 1, 'reason' => 2]);       // late, for the older minute
    $late = $ledger->lateEvidence();
    $newer = array_values(array_filter((new DropLedger($s))->snapshot()['entries'], fn ($e) => $e['minute'] === CLE_MINUTE + 125));

    expect($late)->toMatchArray(['min_minute' => CLE_MINUTE + 5, 'unslotted_events' => 1])
        ->and($newer[0]['dropped_events'])->toBe(7);                              // the newer minute is intact

    cleFinalize($this, CLE_HOUR + 3 * 3600, $settings);
    expect(cleHour()->state)->toBe('incomplete')->and(json_decode(cleHour()->reasons))->toContain('late_drop_evidence');
});

it('late-evidence flags raised while the finalizer handles earlier ones are kept for the next run', function () {
    $s = SpoolConfig::fromArray(cleSpool($this, ['ledger_shards' => 1]));
    $ledger = new DropLedger($s);
    $ledger->record(CLE_MINUTE + 100, ['dropped_events' => 1, 'reason' => 2]);
    $ledger->acknowledge(CLE_MINUTE + 100);
    $ledger->record(CLE_MINUTE + 10, ['dropped_events' => 1, 'reason' => 2]);    // late
    $seen = $ledger->lateEvidence();
    $ledger->record(CLE_MINUTE + 11, ['dropped_events' => 1, 'reason' => 2]);    // another late drop meanwhile

    $ledger->clearLate($seen['shards']);

    expect($ledger->lateEvidence())->toMatchArray(['min_minute' => CLE_MINUTE + 10, 'max_minute' => CLE_MINUTE + 11, 'events' => 2]);
});
